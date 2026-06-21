# BambuJab — découverte LAN des imprimantes BambuLab
#
# Les imprimantes Bambu émettent des annonces SSDP (multicast UDP 239.255.255.250:2021)
# contenant leur n° de série, modèle (DevModel) et nom. On écoute passivement et on
# émet aussi un M-SEARCH pour accélérer la réponse.
#
# Usage autonome (debug) :  python -m bambu.discovery   ou   python bambu/discovery.py
# Retour : liste de dicts {ip, serial, model, name}.

import json
import logging
import socket
import sys
import time

LOGGER = logging.getLogger(__name__)

SSDP_ADDR = "239.255.255.250"
SSDP_PORT = 2021
MSEARCH = (
    "M-SEARCH * HTTP/1.1\r\n"
    "HOST: 239.255.255.250:2021\r\n"
    "MAN: \"ssdp:discover\"\r\n"
    "MX: 1\r\n"
    "ST: urn:bambulab-com:device:3dprinter:1\r\n\r\n"
).encode("utf-8")


def _parse_ssdp(payload, src_ip):
    """Parse un paquet SSDP texte en dict {ip, serial, model, name}."""
    info = {"ip": src_ip, "serial": None, "model": None, "name": None}
    for line in payload.splitlines():
        if ":" not in line:
            continue
        key, _, value = line.partition(":")
        key = key.strip().lower()
        value = value.strip()
        if key == "usn":
            info["serial"] = value
        elif key in ("devmodel.bambu.com", "devmodel"):
            info["model"] = value
        elif key in ("devname.bambu.com", "devname"):
            info["name"] = value
        elif key == "location" and not info["ip"]:
            info["ip"] = value
    return info


def discover(timeout=3):
    """Écoute les annonces SSDP Bambu pendant `timeout` secondes.

    Retourne une liste dédupliquée par n° de série.
    """
    found = {}
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM, socket.IPPROTO_UDP)
    sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    try:
        sock.bind(("", SSDP_PORT))
    except OSError as e:
        LOGGER.warning("discovery.py: bind %d impossible (%s) — écoute best-effort", SSDP_PORT, e)
        try:
            sock.bind(("", 0))
        except OSError:
            sock.close()
            return []

    # Adhésion au groupe multicast (réception des annonces périodiques)
    try:
        mreq = socket.inet_aton(SSDP_ADDR) + socket.inet_aton("0.0.0.0")
        sock.setsockopt(socket.IPPROTO_IP, socket.IP_ADD_MEMBERSHIP, mreq)
    except OSError as e:
        LOGGER.debug("discovery.py: multicast join impossible (%s)", e)

    try:
        sock.sendto(MSEARCH, (SSDP_ADDR, SSDP_PORT))
    except OSError as e:
        LOGGER.debug("discovery.py: M-SEARCH non envoyé (%s)", e)

    sock.settimeout(0.5)
    end = time.time() + timeout
    while time.time() < end:
        try:
            data, addr = sock.recvfrom(2048)
        except socket.timeout:
            continue
        except OSError:
            break
        info = _parse_ssdp(data.decode("utf-8", "ignore"), addr[0])
        if info.get("serial"):
            found[info["serial"]] = info
    sock.close()
    return list(found.values())


if __name__ == "__main__":
    logging.basicConfig(level=logging.INFO)
    result = discover(timeout=int(sys.argv[1]) if len(sys.argv) > 1 else 3)
    print(json.dumps(result, ensure_ascii=False, indent=2))
