# BambuJab — capture d'une image de la caméra (best-effort, P1/A1)
#
# Les imprimantes P1/A1 exposent un flux d'images "chambre" sur le port 6000 en TLS.
# Handshake : paquet d'auth de 80 octets (magic + user 'bblp' + access code, paddés
# à 32 octets). Chaque frame = en-tête 16 octets (taille payload en LE) + JPEG.
# On récupère UNE image et on l'écrit dans le fichier de sortie. Best-effort :
# en cas d'échec (modèle non supporté, firmware, timeout), sortie en erreur propre.
#
# Secrets via env BAMBU_IP / BAMBU_CODE (jamais en CLI/log).
# Usage : python camera_tool.py <fichier_sortie.jpg>

import json
import os
import socket
import ssl
import struct
import sys

CAM_PORT = 6000
USER = "bblp"


def _auth_packet(access_code):
    data = bytearray()
    data += struct.pack("<I", 0x40)
    data += struct.pack("<I", 0x3000)
    data += struct.pack("<I", 0)
    data += struct.pack("<I", 0)
    user = USER.encode("ascii")
    code = access_code.encode("ascii")
    data += user + b"\x00" * (32 - len(user))
    data += code + b"\x00" * (32 - len(code))
    return bytes(data)


def grab(ip, access_code, out_path, timeout=8):
    ctx = ssl._create_unverified_context()
    raw = socket.create_connection((ip, CAM_PORT), timeout=timeout)
    sock = ctx.wrap_socket(raw, server_hostname=ip)
    try:
        sock.sendall(_auth_packet(access_code))
        # Lecture de l'en-tête 16 octets
        header = _recv_exact(sock, 16, timeout)
        if header is None:
            return False, "pas de réponse caméra"
        payload_size = struct.unpack("<I", header[0:4])[0]
        if payload_size <= 0 or payload_size > 8 * 1024 * 1024:
            return False, "taille image invalide"
        jpeg = _recv_exact(sock, payload_size, timeout)
        if jpeg is None or jpeg[:2] != b"\xff\xd8":
            return False, "image non JPEG"
        with open(out_path, "wb") as fh:
            fh.write(jpeg)
        return True, len(jpeg)
    finally:
        try:
            sock.close()
        except Exception:
            pass


def _recv_exact(sock, n, timeout):
    sock.settimeout(timeout)
    buf = b""
    while len(buf) < n:
        try:
            chunk = sock.recv(n - len(buf))
        except socket.timeout:
            return None
        if not chunk:
            return None
        buf += chunk
    return buf


def main():
    ip = os.environ.get("BAMBU_IP", "").strip()
    code = os.environ.get("BAMBU_CODE", "").strip()
    out = sys.argv[1] if len(sys.argv) > 1 else None
    if not ip or not code or not out:
        print(json.dumps({"ok": False, "error": "paramètres manquants"}))
        return
    try:
        ok, info = grab(ip, code, out)
        if ok:
            print(json.dumps({"ok": True, "bytes": info}))
        else:
            print(json.dumps({"ok": False, "error": info}))
    except Exception as e:
        print(json.dumps({"ok": False, "error": str(e)}))


if __name__ == "__main__":
    main()
