# BambuJab — outil CLI d'authentification cloud (invoqué par le PHP)
#
# Secrets via variables d'environnement (jamais en CLI ni en log) :
#   BAMBU_EMAIL, BAMBU_PASSWORD, BAMBU_CODE, BAMBU_TOKEN, BAMBU_REGION
#
# Usage :
#   python cloud_tool.py login    -> {ok, need_code} | {ok, token, username, mqtt_host, devices}
#   python cloud_tool.py verify   -> {ok, token, username, mqtt_host, devices}
#   python cloud_tool.py devices  -> {ok, devices, mqtt_host}

import json
import os
import sys

from bambu import cloud_api


def _out(d):
    print(json.dumps(d, ensure_ascii=False))


def main():
    action = sys.argv[1] if len(sys.argv) > 1 else "login"
    region = os.environ.get("BAMBU_REGION", "global").strip() or "global"
    email = os.environ.get("BAMBU_EMAIL", "").strip()

    if action == "login":
        res = cloud_api.login(email, os.environ.get("BAMBU_PASSWORD", ""), region)
        if res.get("status") == "need_code":
            _out({"ok": True, "need_code": True})
        elif res.get("status") == "ok":
            dev = cloud_api.list_devices(res["token"], region)
            _out({"ok": True, "token": res["token"], "username": res["username"],
                  "mqtt_host": dev.get("mqtt_host", ""), "devices": dev.get("devices", [])})
        else:
            _out({"ok": False, "error": res.get("error", "Échec du login")})

    elif action == "verify":
        res = cloud_api.login_with_code(email, os.environ.get("BAMBU_CODE", ""), region)
        if res.get("status") == "ok":
            dev = cloud_api.list_devices(res["token"], region)
            _out({"ok": True, "token": res["token"], "username": res["username"],
                  "mqtt_host": dev.get("mqtt_host", ""), "devices": dev.get("devices", [])})
        else:
            _out({"ok": False, "error": res.get("error", "Code invalide")})

    elif action == "devices":
        _out(cloud_api.list_devices(os.environ.get("BAMBU_TOKEN", ""), region))

    elif action == "token":
        # Connexion via un jeton d'accès fourni (comptes SSO Google/Apple/Facebook)
        tok = os.environ.get("BAMBU_TOKEN", "").strip()
        if not tok:
            _out({"ok": False, "error": "Jeton manquant"})
            return
        dev = cloud_api.list_devices(tok, region)
        if not dev.get("ok"):
            _out({"ok": False, "error": dev.get("error", "Jeton invalide")})
            return
        _out({"ok": True, "token": tok, "username": cloud_api.mqtt_username_from_token(tok),
              "mqtt_host": dev.get("mqtt_host", ""), "devices": dev.get("devices", [])})

    else:
        _out({"ok": False, "error": "Action inconnue"})


if __name__ == "__main__":
    main()
