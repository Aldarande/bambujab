# BambuJab — authentification au cloud BambuLab (mode Cloud)
#
# Flux (documenté par pybambu / bambu-lab-cloud-api) :
#   1. POST {base}/v1/user-service/user/login  {account, password}
#         -> soit accessToken directement, soit besoin d'un code (2FA email)
#   2. POST {base}/v1/user-service/user/sendemail/code  {email, type:"codeLogin"}
#   3. POST {base}/v1/user-service/user/login  {account, code}  -> accessToken
#   uid MQTT : claim "username" du JWT (déjà sous la forme "u_<id>")
#   Imprimantes : GET {base}/v1/iot-service/api/user/bind  (Bearer token)
#
# Régions : "global" -> api.bambulab.com / us.mqtt.bambulab.com
#           "china"  -> api.bambulab.cn  / cn.mqtt.bambulab.com
#
# Aucune donnée d'authentification n'est journalisée par ce module.

import base64
import json

import requests

TIMEOUT = 20
UA = "bambujab/0.5 (Jeedom)"


def _region(region):
    if (region or "global") == "china":
        return ("https://api.bambulab.cn", "cn.mqtt.bambulab.com")
    return ("https://api.bambulab.com", "us.mqtt.bambulab.com")


def _headers(token=None):
    h = {"User-Agent": UA, "Content-Type": "application/json", "Accept": "application/json"}
    if token:
        h["Authorization"] = "Bearer " + token
    return h


def mqtt_username_from_token(token):
    """Extrait le claim 'username' (forme 'u_<id>') du JWT d'accès (si JWT)."""
    try:
        payload = token.split(".")[1]
        payload += "=" * (-len(payload) % 4)  # padding base64url
        data = json.loads(base64.urlsafe_b64decode(payload).decode("utf-8"))
        return data.get("username") or ""
    except Exception:
        return ""


def resolve_username(token, region="global"):
    """Username MQTT 'u_<uid>'. Le token Bambu n'est pas toujours un JWT : on récupère
    l'uid via l'API utilisateur (fiable), avec repli sur le claim JWT."""
    base, _ = _region(region)
    try:
        r = requests.get(base + "/v1/design-user-service/my/preference",
                        headers=_headers(token), timeout=TIMEOUT)
        if r.ok and r.content:
            uid = (r.json() or {}).get("uid")
            if uid:
                return "u_" + str(uid)
    except Exception:
        pass
    return mqtt_username_from_token(token)


def login(email, password, region="global"):
    """Tente la connexion. Retourne :
       {status:'ok', token, username} | {status:'need_code'} | {status:'error', error}"""
    base, _ = _region(region)
    try:
        r = requests.post(base + "/v1/user-service/user/login",
                          json={"account": email, "password": password},
                          headers=_headers(), timeout=TIMEOUT)
    except Exception as e:
        return {"status": "error", "error": "Connexion au cloud impossible : %s" % e}

    if r.status_code not in (200, 201):
        return {"status": "error", "error": "HTTP %s" % r.status_code}

    data = r.json() if r.content else {}
    token = data.get("accessToken") or ""
    if token:
        return {"status": "ok", "token": token, "refresh": data.get("refreshToken") or "",
                "username": resolve_username(token, region)}

    login_type = (data.get("loginType") or "").lower()
    # Code email requis : on déclenche l'envoi du code
    if login_type in ("verifycode", "verify_code") or data.get("accessToken") == "":
        send = send_code(email, region)
        if not send.get("ok"):
            return {"status": "error", "error": send.get("error", "Envoi du code échoué")}
        return {"status": "need_code"}
    if login_type == "tfa" or data.get("tfaKey"):
        return {"status": "error", "error": "2FA TOTP non prise en charge (utilisez le code email)"}
    return {"status": "error", "error": "Réponse de login inattendue"}


def send_code(email, region="global"):
    base, _ = _region(region)
    try:
        r = requests.post(base + "/v1/user-service/user/sendemail/code",
                          json={"email": email, "type": "codeLogin"},
                          headers=_headers(), timeout=TIMEOUT)
        if r.status_code in (200, 201):
            return {"ok": True}
        return {"ok": False, "error": "HTTP %s" % r.status_code}
    except Exception as e:
        return {"ok": False, "error": str(e)}


def login_with_code(email, code, region="global"):
    base, _ = _region(region)
    try:
        r = requests.post(base + "/v1/user-service/user/login",
                          json={"account": email, "code": str(code)},
                          headers=_headers(), timeout=TIMEOUT)
    except Exception as e:
        return {"status": "error", "error": "Connexion au cloud impossible : %s" % e}
    if r.status_code not in (200, 201):
        return {"status": "error", "error": "HTTP %s" % r.status_code}
    data = r.json() if r.content else {}
    token = data.get("accessToken") or ""
    if not token:
        return {"status": "error", "error": "Code invalide ou expiré"}
    return {"status": "ok", "token": token, "refresh": data.get("refreshToken") or "",
            "username": resolve_username(token, region)}


def check_token(token, region="global"):
    """Valide un jeton d'accès. Retourne True (valide), False (expiré/401), None (indéterminé)."""
    base, _ = _region(region)
    try:
        r = requests.get(base + "/v1/design-user-service/my/preference",
                        headers=_headers(token), timeout=TIMEOUT)
        if r.status_code == 200:
            return True
        if r.status_code in (401, 403):
            return False
        return None
    except Exception:
        return None


def refresh_token(refresh, region="global"):
    """Renouvelle le jeton via le refresh token. Retourne {ok, token, refresh, username}."""
    base, _ = _region(region)
    try:
        r = requests.post(base + "/v1/user-service/user/refreshtoken",
                         json={"refreshToken": refresh}, headers=_headers(), timeout=TIMEOUT)
    except Exception as e:
        return {"ok": False, "error": str(e)}
    if r.status_code not in (200, 201):
        return {"ok": False, "error": "HTTP %s" % r.status_code}
    d = r.json() if r.content else {}
    tok = d.get("accessToken") or ""
    if not tok:
        return {"ok": False, "error": "Réponse sans accessToken"}
    return {"ok": True, "token": tok, "refresh": d.get("refreshToken") or refresh,
            "username": resolve_username(tok, region)}


def list_devices(token, region="global"):
    base, mqtt_host = _region(region)
    try:
        r = requests.get(base + "/v1/iot-service/api/user/bind",
                        headers=_headers(token), timeout=TIMEOUT)
    except Exception as e:
        return {"ok": False, "error": str(e)}
    if r.status_code != 200:
        return {"ok": False, "error": "HTTP %s" % r.status_code}
    data = r.json() if r.content else {}
    devices = []
    for d in data.get("devices", []) or []:
        devices.append({
            "serial": d.get("dev_id", ""),
            "name": d.get("name", ""),
            "model": d.get("dev_product_name", ""),
            "access_code": d.get("dev_access_code", ""),
            "online": bool(d.get("online", False)),
        })
    return {"ok": True, "devices": devices, "mqtt_host": mqtt_host}
