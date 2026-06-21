# BambuJab — mapping AMS -> commandes info dynamiques (monitoring v0.1)
#
# Génère des logicalId dynamiques selon les AMS/slots réellement détectés dans
# le report (le PHP crée les cmds correspondantes à la volée via checkAndUpdateCmd).
#
# logicalId générés (u = index AMS 0..3, s = index slot 0..3) :
#   ams_{u}_{s}_type     (string)  type de filament (PLA, PETG, ABS, …)
#   ams_{u}_{s}_color    (string)  couleur hex #RRGGBB
#   ams_{u}_{s}_remain   (numeric) niveau restant estimé en % (-1 si inconnu)
#   ams_{u}_humidity     (string)  niveau d'humidité de l'unité AMS (1=sec .. 5=humide)
#   ams_{u}_temp         (numeric) température de l'unité AMS en °C
#   ams_active_tray      (string)  identifiant du slot actif (ex. "0-2")
#   vt_tray_type         (string)  filament du slot externe (spool/AMS-lite)
#   vt_tray_color        (string)  couleur hex du slot externe
#
# Source structure : report['print']['ams'] (pybambu/greghesp, OpenBambuAPI).

import logging

LOGGER = logging.getLogger(__name__)


def _norm_color(raw):
    """tray_color Bambu = 'RRGGBBAA' (hex 8 car). Retourne '#RRGGBB' ou None."""
    if not raw or not isinstance(raw, str):
        return None
    raw = raw.strip()
    if len(raw) >= 6:
        return "#" + raw[:6].upper()
    return None


def map_ams(report):
    """Retourne un dict plat {logicalId: value} pour les slots AMS présents.

    Ne renvoie que ce qui est présent (création dynamique côté PHP).
    """
    p = report.get("print", report) if isinstance(report, dict) else {}
    ams_root = p.get("ams")
    out = {}
    if not isinstance(ams_root, dict):
        return out

    # Slot actif global : tray_now est l'index absolu (u*4 + s). 255 = aucun.
    tray_now = ams_root.get("tray_now")
    if tray_now is not None:
        try:
            tn = int(tray_now)
            if tn == 255:
                out["ams_active_tray"] = "Aucun"
            elif tn == 254:
                out["ams_active_tray"] = "Externe"
            else:
                out["ams_active_tray"] = "%d-%d" % (tn // 4, tn % 4)
        except (TypeError, ValueError):
            pass

    units = ams_root.get("ams")
    if isinstance(units, list):
        for unit in units:
            if not isinstance(unit, dict):
                continue
            try:
                u = int(unit.get("id", 0))
            except (TypeError, ValueError):
                continue

            hum = unit.get("humidity")
            if hum is not None:
                out["ams_%d_humidity" % u] = str(hum)
            temp = unit.get("temp")
            if temp is not None:
                try:
                    out["ams_%d_temp" % u] = float(temp)
                except (TypeError, ValueError):
                    pass

            trays = unit.get("tray")
            if isinstance(trays, list):
                for tray in trays:
                    if not isinstance(tray, dict):
                        continue
                    try:
                        s = int(tray.get("id", 0))
                    except (TypeError, ValueError):
                        continue
                    ttype = tray.get("tray_type")
                    if ttype is not None:
                        out["ams_%d_%d_type" % (u, s)] = ttype if ttype != "" else "Vide"
                    color = _norm_color(tray.get("tray_color"))
                    if color is not None:
                        out["ams_%d_%d_color" % (u, s)] = color
                    remain = tray.get("remain")
                    if remain is not None:
                        try:
                            out["ams_%d_%d_remain" % (u, s)] = float(remain)
                        except (TypeError, ValueError):
                            pass

    # Slot externe (spool holder / AMS-lite) : vt_tray
    vt = p.get("vt_tray")
    if isinstance(vt, dict):
        ttype = vt.get("tray_type")
        if ttype is not None:
            out["vt_tray_type"] = ttype if ttype != "" else "Vide"
        color = _norm_color(vt.get("tray_color"))
        if color is not None:
            out["vt_tray_color"] = color

    return out
