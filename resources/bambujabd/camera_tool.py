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
import time

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


def stream(ip, access_code, max_seconds=600, timeout=10):
    """Flux MJPEG : connexion persistante, lecture continue des images (~1 fps A1/P1),
    écrites sur stdout en multipart/x-mixed-replace (boundary=frame). S'arrête quand
    le client se déconnecte (SIGPIPE) ou après max_seconds."""
    ctx = ssl._create_unverified_context()
    raw = socket.create_connection((ip, CAM_PORT), timeout=timeout)
    sock = ctx.wrap_socket(raw, server_hostname=ip)
    out = sys.stdout.buffer
    start = time.time()
    try:
        sock.sendall(_auth_packet(access_code))
        while time.time() - start < max_seconds:
            header = _recv_exact(sock, 16, timeout)
            if header is None:
                break
            size = struct.unpack("<I", header[0:4])[0]
            if size <= 0 or size > 8 * 1024 * 1024:
                break
            jpeg = _recv_exact(sock, size, timeout)
            if jpeg is None or jpeg[:2] != b"\xff\xd8":
                break
            out.write(b"--frame\r\nContent-Type: image/jpeg\r\nContent-Length: "
                      + str(size).encode() + b"\r\n\r\n")
            out.write(jpeg)
            out.write(b"\r\n")
            out.flush()
    except (BrokenPipeError, ConnectionResetError, OSError):
        pass  # client déconnecté ou flux interrompu
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
    arg = sys.argv[1] if len(sys.argv) > 1 else None

    # Mode flux continu (MJPEG sur stdout)
    if arg == "stream":
        if not ip or not code:
            return
        try:
            stream(ip, code)
        except Exception:
            pass
        return

    # Mode capture unique : arg = chemin du fichier de sortie
    out = arg
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
