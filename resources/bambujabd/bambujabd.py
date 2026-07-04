# BambuJab — démon de monitoring des imprimantes BambuLab (LAN-only, v0.1)
#
# Une connexion MQTT par instance (eqLogic). Les états reçus sont aplatis en
# commandes Jeedom (state_map + ams + hms) et remontés au PHP via le callback.
#
# Sécurité : access codes et n° de série ne sont JAMAIS loggués en clair.
# Le contenu de --instances peut contenir des access codes -> reçu via la variable
# d'environnement BAMBUJAB_INSTANCES en priorité (sinon argument --instances en repli).
#
# This file is part of Jeedom (GNU AGPL v3).

import argparse
import json
import logging
import os
import signal
import socket
import sys
import time
import traceback

from jeedom.jeedom import jeedom_socket, jeedom_utils, jeedom_com, JEEDOM_SOCKET_MESSAGE
from bambu.mqtt_client import BambuMqttClient
from bambu import state_map, ams as ams_map, hms as hms_map, commands as cmd_build

# ---------------------------------------------------------------------------
# Paramètres par défaut
# ---------------------------------------------------------------------------
_log_level = "error"
_socket_port = 55152
_socket_host = "localhost"
_pidfile = "/tmp/bambujabd.pid"
_apikey = ""
_callback = ""
_cycle = 0.5
_pushall_interval = 30  # secondes entre deux pushall (état complet)
_hms_lang = "fr"        # langue des messages HMS lisibles (base Bambu)

_clients = {}          # instance_id -> BambuMqttClient
_last_pushall = 0
_last_report = {}      # instance_id -> timestamp du dernier report reçu
_online_pushed = {}    # instance_id -> dernier état 'online' poussé (anti-spam)
_probe_ip = {}         # instance_id -> IP locale à sonder (joignabilité)
_reachable_pushed = {} # instance_id -> dernier état 'reachable' poussé
_last_probe = {}       # instance_id -> timestamp de la dernière sonde
STALE_SECONDS = 300    # sans report au-delà -> imprimante plus "active"
PROBE_INTERVAL = 60    # intervalle entre deux sondes de joignabilité (s)
PROBE_PORTS = (8883, 6000, 990)  # ports BambuLab (MQTT / caméra / FTPS)
jeedom_com_obj = None
jeedom_socket_obj = None


# ---------------------------------------------------------------------------
# Remontée des états vers Jeedom
# ---------------------------------------------------------------------------
def on_report(instance_id, report):
    """Callback MQTT : aplatit le report et envoie les changements au PHP."""
    try:
        severity, messages = hms_map.decode_hms(report, _hms_lang)
        changes = state_map.map_state(report, hms_severity=severity, hms_messages=messages)
        changes.update(ams_map.map_ams(report))
        if not changes:
            return
        changes["online"] = 1
        changes["reachable"] = 1  # reçoit des données -> évidemment joignable
        # Fraîcheur : un report = imprimante active. Sert au watchdog check_freshness().
        _last_report[instance_id] = time.time()
        _online_pushed[instance_id] = True
        _reachable_pushed[instance_id] = True
        # Format consommé par callback.php : {instance_id, data:{logicalId:value}}
        jeedom_com_obj.add_changes("devices::%s" % instance_id, changes)
        logging.debug("bambujabd.py: #%s %d valeur(s) remontée(s)", instance_id, len(changes))
    except Exception as e:
        logging.error("bambujabd.py: on_report #%s erreur %s", instance_id, e)


def on_status(instance_id, online):
    """Callback déconnexion MQTT -> marque hors ligne immédiatement.
    NB : la mise EN ligne n'est PLUS déclenchée par la simple connexion au broker
    (en cloud, on reste connecté même imprimante en veille) — elle vient de la
    réception d'un report réel (on_report). Voir aussi check_freshness()."""
    if not online:
        _online_pushed[instance_id] = False
        jeedom_com_obj.add_changes("devices::%s" % instance_id, {"online": 0})


def _probe_reachable(ip):
    """Teste si l'imprimante répond sur le réseau (TCP sur un port BambuLab).
    Sert à distinguer 'en veille' (joignable, mais muette) d''éteinte' (injoignable).
    Sans IP locale connue, on ne peut pas trancher -> renvoie None."""
    if not ip:
        return None
    for port in PROBE_PORTS:
        try:
            s = socket.create_connection((ip, port), timeout=2)
            s.close()
            return True
        except Exception:
            continue
    return False


def check_freshness():
    """Watchdog : sans report depuis STALE_SECONDS, l'imprimante n'est plus 'active'.
    On sonde alors sa joignabilité réseau pour distinguer veille (joignable) et
    éteinte (injoignable). Évite un affichage figé/trompeur."""
    now = time.time()
    for iid in list(_clients.keys()):
        last = _last_report.get(iid, 0)
        if last <= 0 or (now - last) <= STALE_SECONDS:
            continue
        # Plus de données -> plus "active"
        if _online_pushed.get(iid, False):
            _online_pushed[iid] = False
            jeedom_com_obj.add_changes("devices::%s" % iid, {"online": 0})
            logging.info("bambujabd.py: #%s aucune donnée depuis %ds (veille/éteinte ?)",
                         iid, int(now - last))
        # Sonde de joignabilité périodique
        if now - _last_probe.get(iid, 0) < PROBE_INTERVAL:
            continue
        _last_probe[iid] = now
        r = _probe_reachable(_probe_ip.get(iid, ""))
        if r is None:
            continue  # pas d'IP -> on laisse l'UI supposer "en veille"
        if _reachable_pushed.get(iid) != r:
            _reachable_pushed[iid] = r
            jeedom_com_obj.add_changes("devices::%s" % iid, {"reachable": 1 if r else 0})
            logging.info("bambujabd.py: #%s joignabilité réseau = %s", iid, "oui" if r else "non (éteinte)")


def on_identify(instance_id, serial, model):
    """Découverte du serial/modèle (cas n° de série laissé vide) : remontée pour
    affichage (cmd info 'model') et persistance en config côté PHP (__cfg_*)."""
    changes = {"__cfg_serial": serial}
    if model:
        changes["model"] = model
        changes["__cfg_model"] = model
    jeedom_com_obj.add_changes("devices::%s" % instance_id, changes)


# ---------------------------------------------------------------------------
# Gestion des instances
# ---------------------------------------------------------------------------
def start_instances(instances):
    for inst in instances:
        try:
            iid = int(inst["id"])
        except (KeyError, TypeError, ValueError):
            logging.error("bambujabd.py: instance sans id valide ignorée")
            continue
        mode = (inst.get("mode") or "lan").strip()
        serial = inst.get("serial", "").strip()  # LAN : peut être vide -> découverte auto
        # secret = access code (LAN) ou token cloud
        secret = (inst.get("access_code") or inst.get("token") or "").strip()

        if mode == "cloud":
            host = inst.get("host", "").strip()       # ex. us.mqtt.bambulab.com
            username = inst.get("username", "").strip()  # u_<uid>
            if not host or not username or not secret or not serial:
                logging.warning("bambujabd.py: instance cloud #%s incomplète (host/username/token/serial) — ignorée", iid)
                continue
            client = BambuMqttClient(iid, host, serial, secret, on_report, on_status, on_identify,
                                     host=host, port=int(inst.get("port", 8883)),
                                     username=username, tls_insecure=False)
        else:
            ip = inst.get("ip", "").strip()
            if not ip or not secret:
                logging.warning("bambujabd.py: instance LAN #%s incomplète (ip/access_code requis) — ignorée", iid)
                continue
            client = BambuMqttClient(iid, ip, serial, secret, on_report, on_status, on_identify,
                                     host=ip, port=int(inst.get("port", 8883)),
                                     username="bblp", tls_insecure=True)
        _clients[iid] = client
        _probe_ip[iid] = (inst.get("probe_ip") or "").strip()
        # Amorce le watchdog : la fenêtre de fraîcheur démarre maintenant. Si aucun
        # report n'arrive sous STALE_SECONDS (imprimante en veille), online passera à 0.
        _last_report[iid] = time.time()
        _online_pushed[iid] = True
        client.start()
    logging.info("bambujabd.py: %d imprimante(s) démarrée(s)", len(_clients))


def periodic_pushall():
    """Demande périodiquement l'état complet à chaque imprimante connectée."""
    global _last_pushall
    now = time.time()
    if now - _last_pushall < _pushall_interval:
        return
    _last_pushall = now
    for client in _clients.values():
        client.request_pushall()


# ---------------------------------------------------------------------------
# Socket descendant PHP -> démon (commandes : pour l'instant juste 'pushall')
# ---------------------------------------------------------------------------
def read_socket():
    if JEEDOM_SOCKET_MESSAGE.empty():
        return
    logging.debug("bambujabd.py: message reçu sur le socket")
    try:
        raw = JEEDOM_SOCKET_MESSAGE.get()
        if isinstance(raw, (bytes, bytearray)):
            raw = raw.decode("utf-8", "ignore")
        message = json.loads(jeedom_utils.stripped(raw))
    except (ValueError, TypeError) as e:
        logging.error("bambujabd.py: message socket illisible (%s)", e)
        return
    if message.get("apikey") != _apikey:
        logging.error("bambujabd.py: apikey invalide depuis le socket")
        return
    command = message.get("command")
    if command == "pushall":
        target = message.get("instance_id")
        for iid, client in _clients.items():
            if target in (None, "", iid):
                client.request_pushall()
    elif command == "shutdown":
        shutdown()
    elif command == "control":
        _handle_control(message)
    else:
        logging.debug("bambujabd.py: commande socket inconnue '%s'", command)


def _handle_control(message):
    """Pilotage : publie le payload MQTT correspondant à l'action sur l'imprimante ciblée."""
    try:
        iid = int(message.get("instance_id"))
    except (TypeError, ValueError):
        logging.error("bambujabd.py: control sans instance_id valide")
        return
    client = _clients.get(iid)
    if client is None:
        logging.warning("bambujabd.py: control #%s — imprimante inconnue", iid)
        return
    action = message.get("action")
    payload = cmd_build.build_payload(action, message.get("params"))
    if payload is None:
        logging.warning("bambujabd.py: control #%s — action inconnue '%s'", iid, action)
        return
    if client.publish(payload):
        logging.info("bambujabd.py: control #%s — action '%s' envoyée", iid, action)
    else:
        logging.warning("bambujabd.py: control #%s — action '%s' non envoyée (imprimante non connectée)", iid, action)


def listen():
    jeedom_socket_obj.open()
    try:
        while True:
            time.sleep(0.3)
            read_socket()
            periodic_pushall()
            check_freshness()
    except KeyboardInterrupt:
        shutdown()


# ---------------------------------------------------------------------------
# Arrêt propre
# ---------------------------------------------------------------------------
def handler(signum=None, frame=None):
    logging.debug("bambujabd.py: signal %s reçu, arrêt", signum)
    shutdown()


def shutdown():
    logging.info("bambujabd.py: arrêt du démon")
    for client in _clients.values():
        try:
            client.stop()
        except Exception:
            pass
    try:
        jeedom_socket_obj.close()
    except Exception:
        pass
    try:
        os.remove(_pidfile)
    except Exception:
        pass
    sys.stdout.flush()
    os._exit(0)


# ---------------------------------------------------------------------------
# Chargement des instances (env prioritaire pour ne pas exposer les secrets en CLI)
# ---------------------------------------------------------------------------
def load_instances(arg_value):
    raw = os.environ.get("BAMBUJAB_INSTANCES", arg_value or "[]")
    try:
        data = json.loads(raw)
        if isinstance(data, list):
            return data
    except (ValueError, TypeError) as e:
        logging.error("bambujabd.py: --instances JSON invalide (%s)", e)
    return []


# ---------------------------------------------------------------------------
# Point d'entrée
# ---------------------------------------------------------------------------
if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="BambuJab daemon")
    parser.add_argument("--loglevel", type=str)
    parser.add_argument("--callback", type=str)
    parser.add_argument("--apikey", type=str)
    parser.add_argument("--cycle", type=float)
    parser.add_argument("--pid", type=str)
    parser.add_argument("--socketport", type=int)
    parser.add_argument("--instances", type=str, default="[]")
    parser.add_argument("--lang", type=str, help="Langue des messages HMS (ex. fr, en)")
    parser.add_argument("--discover", action="store_true", help="Découverte LAN puis sortie")
    args = parser.parse_args()

    if args.discover:
        from bambu.discovery import discover
        print(json.dumps(discover(timeout=3), ensure_ascii=False))
        sys.exit(0)

    if args.lang:
        _hms_lang = args.lang.strip()[:2].lower() or "fr"
    if args.loglevel:
        _log_level = args.loglevel
    if args.callback:
        _callback = args.callback
    # apikey : variable d'environnement prioritaire (évite l'exposition en CLI / ps)
    _apikey = os.environ.get("BAMBUJAB_APIKEY", args.apikey or "")
    if args.pid:
        _pidfile = args.pid
    if args.cycle:
        _cycle = float(args.cycle)
    if args.socketport:
        _socket_port = int(args.socketport)

    jeedom_utils.set_log_level(_log_level)
    # SECURITY : urllib3/requests loguent l'URL complète du callback en mode debug,
    # ce qui exposerait l'apikey Jeedom (query string) en clair dans le log. On force
    # ces loggers en WARNING pour ne jamais faire fuiter l'apikey.
    logging.getLogger("urllib3").setLevel(logging.WARNING)
    logging.getLogger("requests").setLevel(logging.WARNING)
    logging.info("bambujabd.py: démarrage (port socket %s, niveau %s)", _socket_port, _log_level)

    signal.signal(signal.SIGINT, handler)
    signal.signal(signal.SIGTERM, handler)

    try:
        jeedom_utils.write_pid(str(_pidfile))
        jeedom_com_obj = jeedom_com(apikey=_apikey, url=_callback, cycle=_cycle)
        if not jeedom_com_obj.test():
            logging.error("bambujabd.py: communication réseau Jeedom KO — vérifier la configuration réseau")
            shutdown()
        jeedom_socket_obj = jeedom_socket(port=_socket_port, address=_socket_host)
        start_instances(load_instances(args.instances))
        listen()
    except Exception as e:
        logging.error("bambujabd.py: erreur fatale %s", e)
        logging.info(traceback.format_exc())
        shutdown()
