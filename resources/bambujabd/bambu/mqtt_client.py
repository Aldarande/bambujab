# BambuJab — client MQTT LAN d'une imprimante BambuLab
#
# En mode LAN, l'imprimante héberge un broker MQTT TLS (port 8883). Auth :
#   username = "bblp"
#   password = access code (8 chiffres affichés sur l'écran de l'imprimante)
# Le certificat est auto-signé -> on désactive la vérification TLS UNIQUEMENT
# pour cette connexion locale point-à-point (IP privée de l'imprimante).
#
# Topics :
#   report  : device/{serial}/report   (souscription, JSON d'état)
#   request : device/{serial}/request  (publication des commandes)
# À la connexion, on publie un "pushall" pour obtenir l'état complet immédiat.

import json
import logging
import ssl
import threading
import time

import paho.mqtt.client as mqtt

from bambu.models import model_from_serial

LOGGER = logging.getLogger(__name__)

MQTT_PORT = 8883
MQTT_USER = "bblp"
PUSHALL = {"pushing": {"sequence_id": "0", "command": "pushall"}}


def _mask(secret):
    """Masque un secret pour les logs (ne garde que les 2 derniers caractères)."""
    if not secret:
        return "***"
    s = str(secret)
    return "***" + s[-2:] if len(s) > 2 else "***"


class BambuMqttClient:
    """Connexion MQTT à une imprimante. `on_report(instance_id, report_dict)` est
    appelé à chaque message d'état reçu."""

    def __init__(self, instance_id, ip, serial, access_code, on_report, on_status=None,
                 on_identify=None, host=None, port=MQTT_PORT, username=MQTT_USER, tls_insecure=True):
        self.instance_id = instance_id
        self.ip = ip
        # host de connexion : IP imprimante (LAN) ou broker cloud (us/cn.mqtt.bambulab.com)
        self.host = host or ip
        self.port = int(port)
        self._username = username           # 'bblp' (LAN) ou 'u_<uid>' (cloud)
        self._tls_insecure = tls_insecure   # True : cert auto-signé LAN ; False : cert valide cloud
        self.serial = (serial or "").strip()
        self._access_code = access_code     # access code (LAN) ou token cloud
        self.on_report = on_report
        self.on_status = on_status      # callback(instance_id, online_bool)
        self.on_identify = on_identify  # callback(instance_id, serial, model) à la découverte
        # Si le n° de série est connu : abonnement ciblé. Sinon : wildcard, le
        # serial est appris à la réception du premier message (topic device/<sn>/report).
        if self.serial:
            self.topic_report = "device/%s/report" % self.serial
            self.topic_request = "device/%s/request" % self.serial
        else:
            self.topic_report = "device/+/report"
            self.topic_request = None
        self._identified = bool(self.serial)
        self._client = None
        self._connected = False
        self._stop = False
        self._thread = None

    # -- cycle de vie ---------------------------------------------------------

    def start(self):
        self._thread = threading.Thread(target=self._run, daemon=True)
        self._thread.start()

    def stop(self):
        self._stop = True
        if self._client is not None:
            try:
                self._client.disconnect()
                self._client.loop_stop()
            except Exception as e:
                LOGGER.debug("mqtt_client.py: stop #%s erreur %s", self.instance_id, e)

    def _build_client(self):
        client = mqtt.Client(
            mqtt.CallbackAPIVersion.VERSION2,
            client_id="bambujab-%s" % self.instance_id,
        )
        client.username_pw_set(self._username, self._access_code)
        if self._tls_insecure:
            client.tls_set(cert_reqs=ssl.CERT_NONE, tls_version=ssl.PROTOCOL_TLSv1_2)
            client.tls_insecure_set(True)  # cert auto-signé de l'imprimante (LAN)
        else:
            client.tls_set(tls_version=ssl.PROTOCOL_TLSv1_2)  # cert valide (cloud)
        client.on_connect = self._on_connect
        client.on_disconnect = self._on_disconnect
        client.on_message = self._on_message
        return client

    def _run(self):
        while not self._stop:
            try:
                self._client = self._build_client()
                LOGGER.info("mqtt_client.py: connexion #%s vers %s:%s (user=%s, serial=%s, secret=%s)",
                            self.instance_id, self.host, self.port, self._username,
                            _mask(self.serial), _mask(self._access_code))
                self._client.connect(self.host, self.port, keepalive=60)
                self._client.loop_forever(retry_first_connection=False)
            except Exception as e:
                self._set_online(False)
                LOGGER.warning("mqtt_client.py: #%s déconnecté/échec (%s) — nouvelle tentative dans 10s",
                               self.instance_id, e)
            if self._stop:
                break
            time.sleep(10)

    # -- callbacks paho -------------------------------------------------------

    def _on_connect(self, client, userdata, flags, reason_code, properties=None):
        if reason_code == 0:
            self._connected = True
            # On NE force PAS online=1 ici : être connecté au broker (surtout en cloud)
            # ne signifie pas que l'imprimante est active. online passe à 1 à la
            # réception d'un report réel (cf. bambujabd.on_report).
            client.subscribe(self.topic_report)
            LOGGER.info("mqtt_client.py: #%s connecté, souscription %s", self.instance_id, self.topic_report)
            # Si le n° de série est déjà connu (saisi), on émet aussi le modèle déduit
            # (sinon il sera émis lors de la découverte du serial via on_identify).
            if self.serial and self.on_identify is not None:
                model = model_from_serial(self.serial)
                try:
                    self.on_identify(self.instance_id, self.serial, model)
                except Exception as e:
                    LOGGER.debug("mqtt_client.py: #%s on_identify (connect) erreur %s", self.instance_id, e)
            self.request_pushall()
        else:
            self._set_online(False)
            LOGGER.error("mqtt_client.py: #%s connexion refusée (code %s) — vérifier IP/access code/Mode LAN",
                         self.instance_id, reason_code)

    def _on_disconnect(self, client, userdata, *args):
        self._connected = False
        self._set_online(False)
        LOGGER.info("mqtt_client.py: #%s déconnecté", self.instance_id)

    def _on_message(self, client, userdata, msg):
        # Découverte du n° de série depuis le topic (device/<serial>/report) si inconnu
        if not self._identified:
            parts = msg.topic.split("/")
            if len(parts) >= 2 and parts[1] and parts[1] != "+":
                self.serial = parts[1]
                self.topic_request = "device/%s/request" % self.serial
                self._identified = True
                model = model_from_serial(self.serial)
                LOGGER.info("mqtt_client.py: #%s imprimante identifiée (serial=%s, modèle=%s)",
                            self.instance_id, _mask(self.serial), model or "inconnu")
                if self.on_identify is not None:
                    try:
                        self.on_identify(self.instance_id, self.serial, model)
                    except Exception as e:
                        LOGGER.debug("mqtt_client.py: #%s on_identify erreur %s", self.instance_id, e)
                self.request_pushall()
        try:
            report = json.loads(msg.payload.decode("utf-8"))
        except (ValueError, UnicodeDecodeError) as e:
            LOGGER.debug("mqtt_client.py: #%s payload illisible (%s)", self.instance_id, e)
            return
        try:
            self.on_report(self.instance_id, report)
        except Exception as e:
            LOGGER.error("mqtt_client.py: #%s erreur traitement report (%s)", self.instance_id, e)

    # -- helpers --------------------------------------------------------------

    def _set_online(self, online):
        if self.on_status is not None:
            try:
                self.on_status(self.instance_id, online)
            except Exception as e:
                LOGGER.debug("mqtt_client.py: #%s on_status erreur %s", self.instance_id, e)

    def request_pushall(self):
        """Demande l'état complet (utile à la connexion et périodiquement)."""
        self.publish(PUSHALL)

    def publish(self, payload):
        """Publie une commande JSON sur le topic request (utilisé en v0.2 pour le
        pilotage). Retourne True si la publication a été acceptée localement."""
        if not self._connected or self._client is None:
            # Transitoire normal (pushall périodique pendant une reconnexion) -> debug
            LOGGER.debug("mqtt_client.py: #%s publish ignoré (non connecté)", self.instance_id)
            return False
        if not self.topic_request:
            # serial pas encore appris (wildcard) : le printer pousse ses reports
            # spontanément, on attend l'identification avant de publier.
            LOGGER.debug("mqtt_client.py: #%s publish différé (serial non encore identifié)", self.instance_id)
            return False
        try:
            self._client.publish(self.topic_request, json.dumps(payload))
            return True
        except Exception as e:
            LOGGER.error("mqtt_client.py: #%s publish erreur %s", self.instance_id, e)
            return False
