# BambuJab — décodage des alertes HMS (Health Management System) Bambu
#
# Le report contient print.hms = [{"attr": <int>, "code": <int>}, ...].
# La sévérité est encodée dans les bits de poids fort de `code` :
#   (code >> 16) & 0xF  ->  1=fatal, 2=serious(serieux), 3=common(commun), 4=info
# Le code complet "HMS_XXXX_YYYY_ZZZZ_WWWW" identifie le message exact.
#
# Mapping texte complet (multilingue) : non embarqué (volumineux, évolutif).
# Source de référence : https://e.bambulab.com/query.php?lang=en (codes HMS)
# et https://github.com/greghesp/ha-bambulab (wiki HMS). v0.1 = sévérité + code
# brut lisible ; le libellé humain complet sera enrichi ultérieurement.

import json
import logging
import os
import threading
import time

import requests

LOGGER = logging.getLogger(__name__)

# --- Base HMS lisible (téléchargée + mise en cache) --------------------------
# Source officielle Bambu : https://e.bambulab.com/query.php?lang=<lang>
# Format : {"data":{"device_hms":{"<lang>":[{"ecode":"16hex","intro":"texte"}]}}}
# Le fichier fait ~800 Ko ; on le met en cache 30 jours dans bambujabd/.
_HMS_DB = None            # dict ecode(hex maj) -> texte
_HMS_LANG = None
_FETCHING = set()
_CACHE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))  # resources/bambujabd/
_CACHE_MAX_AGE = 30 * 86400


def _cache_path(lang):
    return os.path.join(_CACHE_DIR, "hms_db_%s.json" % lang)


def _fetch_db(lang):
    """Télécharge et parse la base HMS -> dict {ecode: intro}. None si échec."""
    try:
        r = requests.get("https://e.bambulab.com/query.php?lang=%s" % lang,
                         headers={"User-Agent": "Mozilla/5.0"}, timeout=25)
        if r.status_code != 200:
            return _fetch_db("en") if lang != "en" else None
        arr = (r.json().get("data", {}).get("device_hms", {}) or {}).get(lang, [])
        db = {}
        for e in arr:
            ec = (e.get("ecode") or "").upper()
            if ec:
                db[ec] = e.get("intro", "")
        if not db and lang != "en":
            return _fetch_db("en")
        LOGGER.info("hms.py: base HMS '%s' chargée (%d codes)", lang, len(db))
        return db
    except Exception as e:
        LOGGER.warning("hms.py: téléchargement base HMS échoué (%s)", e)
        return None


def _fetch_and_cache(lang):
    global _HMS_DB, _HMS_LANG
    try:
        db = _fetch_db(lang)
        if db is not None:
            try:
                with open(_cache_path(lang), "w", encoding="utf-8") as f:
                    json.dump(db, f, ensure_ascii=False)
            except Exception:
                pass
            _HMS_DB = db
            _HMS_LANG = lang
    finally:
        _FETCHING.discard(lang)


def _ensure_db(lang):
    """Retourne la base en mémoire, en la chargeant depuis le cache local (rapide)
    ou en déclenchant un téléchargement ASYNCHRONE (ne bloque pas le report courant)."""
    global _HMS_DB, _HMS_LANG
    if _HMS_DB is not None and _HMS_LANG == lang:
        return _HMS_DB
    path = _cache_path(lang)
    if os.path.isfile(path) and (time.time() - os.path.getmtime(path)) < _CACHE_MAX_AGE:
        try:
            with open(path, encoding="utf-8") as f:
                _HMS_DB = json.load(f)
                _HMS_LANG = lang
                return _HMS_DB
        except Exception:
            pass
    if lang not in _FETCHING:
        _FETCHING.add(lang)
        threading.Thread(target=_fetch_and_cache, args=(lang,), daemon=True).start()
    return None


def describe(ecode, lang="fr"):
    """Message humain d'un code HMS (ecode = 16 hex), '' si inconnu/non chargé."""
    db = _ensure_db(lang)
    if not db:
        return ""
    return db.get(str(ecode).upper(), "")


SEVERITY = {
    1: "Fatal",
    2: "Sérieux",
    3: "Commun",
    4: "Info",
}

# Ordre de gravité décroissant pour calculer la sévérité maximale.
_SEVERITY_RANK = {"Fatal": 4, "Sérieux": 3, "Commun": 2, "Info": 1}


def _format_code(attr, code):
    """Construit la chaîne HMS_XXXX_YYYY_ZZZZ_WWWW à partir de attr/code (ints)."""
    try:
        attr = int(attr)
        code = int(code)
    except (TypeError, ValueError):
        return None
    return "HMS_%04X_%04X_%04X_%04X" % (
        (attr >> 16) & 0xFFFF,
        attr & 0xFFFF,
        (code >> 16) & 0xFFFF,
        code & 0xFFFF,
    )


def decode_hms(report, lang="fr"):
    """Retourne (severity_str, messages_str).

    severity_str = sévérité maximale courante ('Aucune' si pas d'alerte).
    messages_str = lignes lisibles jointes par ' | ' (texte HMS + code), vide si aucune.
    Le texte humain est résolu via la base HMS Bambu (describe()) ; à défaut, le code brut.
    """
    p = report.get("print", report) if isinstance(report, dict) else {}
    hms = p.get("hms")
    if not isinstance(hms, list) or len(hms) == 0:
        return ("Aucune", "")

    messages = []
    max_rank = 0
    max_label = "Aucune"
    for item in hms:
        if not isinstance(item, dict):
            continue
        code = item.get("code")
        attr = item.get("attr")
        if code is None:
            continue
        try:
            sev_bits = (int(code) >> 16) & 0xF
        except (TypeError, ValueError):
            sev_bits = 0
        sev_label = SEVERITY.get(sev_bits, "Inconnu")
        code_str = _format_code(attr, code) or "HMS_?"
        # Résolution en texte lisible (ecode = code sans 'HMS_' ni '_')
        ecode = code_str.replace("HMS_", "").replace("_", "")
        human = describe(ecode, lang)
        if human:
            messages.append("%s : %s (%s)" % (sev_label, human, code_str))
        else:
            messages.append("%s : %s" % (sev_label, code_str))
        rank = _SEVERITY_RANK.get(sev_label, 0)
        if rank > max_rank:
            max_rank = rank
            max_label = sev_label

    return (max_label, " | ".join(messages))
