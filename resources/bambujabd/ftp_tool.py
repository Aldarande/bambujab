# BambuJab — outil FTPS pour les imprimantes BambuLab (LAN)
#
# Les imprimantes Bambu exposent un serveur FTPS *implicite* (port 990, TLS dès la
# connexion). ftplib ne gère que le FTPS explicite : on sous-classe FTP_TLS pour
# wrapper le socket dans TLS immédiatement (ImplicitFTP_TLS).
#
# Auth : user "bblp" / password = access code. Certificat auto-signé -> non vérifié
# (connexion LAN point à point).
#
# Invoqué par le PHP (jamais en direct par l'utilisateur). Secrets passés par
# variables d'environnement BAMBU_IP / BAMBU_CODE (jamais en CLI ni en log).
#
# Usage :
#   python ftp_tool.py list                       -> JSON {ok, files:[{name,size,dir}]}
#   python ftp_tool.py upload <local> <remote>    -> JSON {ok, remote}
#   python ftp_tool.py delete <remote>            -> JSON {ok}

import ftplib
import json
import os
import ssl
import sys

FTP_PORT = 990
FTP_USER = "bblp"
# Dossiers usuels où Bambu range les projets/gcodes
LIST_DIRS = ["/", "/cache", "/model"]


class ImplicitFTP_TLS(ftplib.FTP_TLS):
    """FTP_TLS en mode implicite : le socket est chiffré dès la connexion."""

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self._sock = None

    @property
    def sock(self):
        return self._sock

    @sock.setter
    def sock(self, value):
        if value is not None and not isinstance(value, ssl.SSLSocket):
            value = self.context.wrap_socket(value, server_hostname=self.host)
        self._sock = value


def _connect(ip, code):
    # SECURITY (TLS) : certificat NON vérifié — l'imprimante BambuLab présente un
    # certificat auto-signé sur son serveur FTPS LAN (port 990). Compromis de
    # confiance acceptable car connexion locale point-à-point (IP privée). Le mode
    # Cloud, lui, utilise un certificat valide (vérifié).
    ctx = ssl._create_unverified_context()
    ftp = ImplicitFTP_TLS(context=ctx)
    ftp.connect(host=ip, port=FTP_PORT, timeout=15)
    ftp.login(user=FTP_USER, passwd=code)
    ftp.prot_p()
    return ftp


def _list(ftp):
    files = []
    seen = set()
    for d in LIST_DIRS:
        try:
            entries = list(ftp.mlsd(d, facts=["type", "size"]))
        except Exception:
            try:
                ftp.cwd(d)
                names = ftp.nlst()
                entries = [(n, {"type": "file"}) for n in names]
            except Exception:
                continue
        for name, facts in entries:
            if name in (".", ".."):
                continue
            is_dir = facts.get("type") == "dir"
            base = name.split("/")[-1]
            # On ne remonte que les fichiers imprimables
            if not is_dir and not base.lower().endswith((".3mf", ".gcode", ".gcode.3mf")):
                continue
            full = (d.rstrip("/") + "/" + base) if d != "/" else "/" + base
            if full in seen:
                continue
            seen.add(full)
            files.append({
                "name": base,
                "path": full,
                "size": int(facts.get("size", 0) or 0),
                "dir": is_dir,
            })
    return files


def main():
    ip = os.environ.get("BAMBU_IP", "").strip()
    code = os.environ.get("BAMBU_CODE", "").strip()
    action = sys.argv[1] if len(sys.argv) > 1 else "list"

    if not ip or not code:
        print(json.dumps({"ok": False, "error": "BAMBU_IP/BAMBU_CODE manquants"}))
        return

    try:
        ftp = _connect(ip, code)
    except Exception as e:
        print(json.dumps({"ok": False, "error": "Connexion FTPS impossible : %s" % e}))
        return

    try:
        if action == "list":
            print(json.dumps({"ok": True, "files": _list(ftp)}, ensure_ascii=False))
        elif action == "upload" and len(sys.argv) >= 4:
            local, remote = sys.argv[2], sys.argv[3]
            if not os.path.isfile(local):
                print(json.dumps({"ok": False, "error": "Fichier local introuvable"}))
                return
            with open(local, "rb") as fh:
                ftp.storbinary("STOR " + remote, fh)
            print(json.dumps({"ok": True, "remote": remote}))
        elif action == "delete" and len(sys.argv) >= 3:
            ftp.delete(sys.argv[2])
            print(json.dumps({"ok": True}))
        else:
            print(json.dumps({"ok": False, "error": "Action inconnue ou arguments manquants"}))
    except Exception as e:
        print(json.dumps({"ok": False, "error": str(e)}))
    finally:
        try:
            ftp.quit()
        except Exception:
            pass


if __name__ == "__main__":
    main()
