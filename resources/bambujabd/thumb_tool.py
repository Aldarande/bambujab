# BambuJab — extraction de la vignette (aperçu du plateau) d'une impression
#
# L'aperçu est embarqué dans le .3mf du job (Metadata/plate_1_small.png, petite,
# et plate_1.png, grande). On identifie le .3mf via le nom du job, puis on
# télécharge SEULEMENT le début du fichier (les métadonnées/images sont au début,
# le gcode volumineux à la fin) et on extrait le PNG en lisant les entêtes ZIP
# locaux (pas besoin du sommaire central situé en fin de fichier).
#
# Fonctionne en LAN et en Cloud : dans les deux cas via l'IP locale + access code
# (l'imprimante reste joignable sur le réseau), comme la caméra.
#
# Secrets via env BAMBU_IP / BAMBU_CODE. Job via BAMBU_JOB (ou fichier via BAMBU_FILE).
# Usage : python thumb_tool.py <fichier_sortie.png>

import json
import os
import re
import ssl
import struct
import sys
import zlib

from ftp_tool import ImplicitFTP_TLS, FTP_PORT, FTP_USER

MAX_BYTES = 3 * 1024 * 1024   # plafond de téléchargement partiel (les images sont au
                              # tout début du .3mf ; ce cap évite de tirer le gcode)
WANTED = ["Metadata/plate_1_small.png", "Metadata/plate_1.png",
          "Metadata/plate_no_light_1.png", "Metadata/top_1.png"]


def _norm(s):
    return re.sub(r"[^a-z0-9]", "", (s or "").lower())


def _connect(ip, code):
    ctx = ssl._create_unverified_context()
    ftp = ImplicitFTP_TLS(context=ctx)
    ftp.connect(host=ip, port=FTP_PORT, timeout=15)
    ftp.login(user=FTP_USER, passwd=code)
    ftp.prot_p()
    return ftp


def _mdtm(ftp, path):
    """Date de modification (MDTM) d'un fichier, en entier AAAAMMJJhhmmss ; 0 si indispo.
    Sert à départager plusieurs .3mf correspondant au même job (on prend le plus récent)."""
    try:
        resp = ftp.sendcmd("MDTM " + path)  # "213 20240115103012"
        digits = re.sub(r"[^0-9]", "", resp.split(" ", 1)[-1])
        return int(digits[:14]) if digits else 0
    except Exception:
        return 0


def _find_3mf(ftp, job):
    jn = _norm(job)
    cands = []
    for d in ("/cache", "/model", "/"):
        try:
            ftp.cwd(d)
            names = ftp.nlst()
        except Exception:
            continue
        for n in names:
            base = n.split("/")[-1]
            if base.lower().endswith(".3mf"):
                cands.append(((d.rstrip("/") or "") + "/" + base, base))
    if not jn:
        return None
    # Score de correspondance : 2 = nom identique (hors extension), 1 = job contenu
    # dans le nom (ou préfixe), 0 = sans rapport. On garde le meilleur score, puis
    # on départage par date (le .3mf le plus récent = l'impression réellement lancée).
    matches = []
    for path, base in cands:
        nb = _norm(base)
        stem = _norm(base.rsplit(".", 1)[0])
        if stem == jn:
            score = 2
        elif jn in nb or nb.startswith(jn):
            score = 1
        else:
            continue
        matches.append((score, path))
    if not matches:
        return None
    best_score = max(s for s, _ in matches)
    best = [p for s, p in matches if s == best_score]
    if len(best) == 1:
        return best[0]
    # Plusieurs candidats à égalité : on prend le plus récemment modifié.
    return max(best, key=lambda p: _mdtm(ftp, p))


def _partial_download(ftp, path, max_bytes):
    ftp.voidcmd("TYPE I")
    conn = ftp.transfercmd("RETR " + path)
    buf = b""
    try:
        while len(buf) < max_bytes:
            chunk = conn.recv(65536)
            if not chunk:
                break
            buf += chunk
    finally:
        try:
            conn.close()
        except Exception:
            pass
        try:
            ftp.voidresp()  # peut échouer si on a coupé avant la fin -> ignoré
        except Exception:
            pass
    return buf


_PNG_MAGIC = b"\x89PNG\r\n\x1a\n"


def _read_stored_png(buf, start):
    """Lit un PNG STOCKÉ (non compressé) à partir de `start` jusqu'au marqueur IEND.
    Robuste même si la taille n'est pas dans l'entête local (data descriptor)."""
    if buf[start:start + 8] != _PNG_MAGIC:
        return None
    iend = buf.find(b"IEND", start)
    if iend == -1:
        return None
    return buf[start:iend + 8]  # IEND + CRC (4 octets)


def _extract_png(buf, wanted):
    """Cherche l'entête ZIP local de chaque nom voulu (PK\\x03\\x04) et extrait le PNG.
    Les images du .3mf Bambu sont stockées non compressées -> pas de décompression."""
    found = {}
    pos = 0
    n = len(buf)
    while True:
        idx = buf.find(b"PK\x03\x04", pos)
        if idx == -1 or idx + 30 > n:
            break
        pos = idx + 4
        fn_len = struct.unpack("<H", buf[idx + 26:idx + 28])[0]
        ex_len = struct.unpack("<H", buf[idx + 28:idx + 30])[0]
        name_start = idx + 30
        if name_start + fn_len > n:
            continue
        name = buf[name_start:name_start + fn_len].decode("utf-8", "ignore")
        if name not in wanted:
            continue
        method = struct.unpack("<H", buf[idx + 8:idx + 10])[0]
        data_start = name_start + fn_len + ex_len
        if method == 0:
            png = _read_stored_png(buf, data_start)
        else:
            # PNG deflate (rare pour Bambu) : on tente avec la taille de l'entête si dispo
            comp_size = struct.unpack("<I", buf[idx + 18:idx + 22])[0]
            png = None
            if comp_size and data_start + comp_size <= n:
                try:
                    raw = zlib.decompress(buf[data_start:data_start + comp_size], -15)
                    if raw[:8] == _PNG_MAGIC:
                        png = raw
                except Exception:
                    png = None
        if png:
            found[name] = png
    for w in wanted:
        if w in found:
            return found[w]
    return None


def main():
    ip = os.environ.get("BAMBU_IP", "").strip()
    code = os.environ.get("BAMBU_CODE", "").strip()
    job = os.environ.get("BAMBU_JOB", "").strip()
    forced = os.environ.get("BAMBU_FILE", "").strip()  # chemin .3mf explicite (test)
    out = sys.argv[1] if len(sys.argv) > 1 else None
    if not ip or not code or not out:
        print(json.dumps({"ok": False, "error": "paramètres manquants"}))
        return
    try:
        ftp = _connect(ip, code)
    except Exception as e:
        print(json.dumps({"ok": False, "error": "FTPS: %s" % e}))
        return
    try:
        path = forced or _find_3mf(ftp, job)
        if not path:
            print(json.dumps({"ok": False, "error": "aucun .3mf correspondant au job"}))
            return
        buf = _partial_download(ftp, path, MAX_BYTES)
        png = _extract_png(buf, WANTED)
        if not png:
            print(json.dumps({"ok": False, "error": "vignette non trouvée dans le début du .3mf",
                              "file": path, "downloaded": len(buf)}))
            return
        with open(out, "wb") as f:
            f.write(png)
        print(json.dumps({"ok": True, "file": path, "bytes": len(png), "downloaded": len(buf)}))
    except Exception as e:
        print(json.dumps({"ok": False, "error": str(e)}))
    finally:
        try:
            ftp.quit()
        except Exception:
            pass


if __name__ == "__main__":
    main()
