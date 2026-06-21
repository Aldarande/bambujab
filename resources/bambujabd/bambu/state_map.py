# BambuJab — mapping état MQTT -> commandes info Jeedom (monitoring v0.1, LAN-only)
#
# CONTRAT logicalId consommé par core/class/bambujab.class.php (createCommands).
# Le démon remonte au PHP, via le callback Jeedom, un dict plat {logicalId: value}.
# Le PHP crée/maj une cmd info par logicalId (checkAndUpdateCmd).
#
# ┌────────────────────────┬───────────────────────────────┬───────────┬─────────┐
# │ logicalId              │ libellé                       │ type info │ unité   │
# ├────────────────────────┼───────────────────────────────┼───────────┼─────────┤
# │ online                 │ En ligne                      │ binary    │         │
# │ printer_state          │ État imprimante               │ string    │         │
# │ stage                  │ Étape courante                │ string    │         │
# │ progress               │ Progression                   │ numeric   │ %       │
# │ layer_num              │ Couche courante               │ numeric   │         │
# │ total_layer            │ Couches totales               │ numeric   │         │
# │ remaining_time         │ Temps restant                 │ numeric   │ min     │
# │ job_name               │ Nom du job                    │ string    │         │
# │ nozzle_temp            │ Température buse               │ numeric   │ °C      │
# │ nozzle_target          │ Consigne buse                 │ numeric   │ °C      │
# │ bed_temp               │ Température plateau            │ numeric   │ °C      │
# │ bed_target             │ Consigne plateau              │ numeric   │ °C      │
# │ chamber_temp           │ Température chambre           │ numeric   │ °C      │
# │ fan_speed              │ Ventilateur pièce             │ numeric   │ %       │
# │ aux_fan_speed          │ Ventilateur auxiliaire        │ numeric   │ %       │
# │ chamber_fan_speed      │ Ventilateur chambre           │ numeric   │ %       │
# │ print_speed_lvl        │ Profil de vitesse             │ string    │         │
# │ print_speed_mag        │ Vitesse                       │ numeric   │ %       │
# │ wifi_signal            │ Signal Wi-Fi                  │ numeric   │ dBm     │
# │ light_state            │ Lumière chambre               │ binary    │         │
# │ nozzle_diameter        │ Diamètre buse                 │ string    │ mm      │
# │ nozzle_type            │ Type de buse                  │ string    │         │
# │ hms_severity           │ Gravité alerte HMS            │ string    │         │
# │ hms_messages           │ Alertes HMS                   │ string    │         │
# └────────────────────────┴───────────────────────────────┴───────────┴─────────┘
#
# Les commandes AMS (dynamiques) sont produites séparément par bambu/ams.py.
# Source des champs : protocole report Bambu (pybambu/greghesp, OpenBambuAPI/Doridian).

import logging

LOGGER = logging.getLogger(__name__)

# Mapping gcode_state -> libellé lisible (états haut niveau de l'impression)
GCODE_STATE = {
    "IDLE": "Inactif",
    "PREPARE": "Préparation",
    "RUNNING": "Impression",
    "PAUSE": "En pause",
    "FINISH": "Terminé",
    "FAILED": "Échec",
    "SLICING": "Découpe",
    "UNKNOWN": "Inconnu",
}

# Mapping stg_cur (étape mécanique courante) -> libellé. Liste issue d'OpenBambuAPI.
STAGE_CUR = {
    -1: "Au repos",
    0: "Impression",
    1: "Auto bed leveling",
    2: "Préchauffage du lit",
    3: "Inspection vibrations XY",
    4: "Changement de filament",
    5: "Calibration M400",
    6: "Mesure résonance Z",
    7: "Homing des axes",
    8: "Nettoyage de la buse",
    9: "Vérification température extrudeur",
    10: "Imprimante refroidit",
    11: "Calibration du moteur",
    12: "Vérification débit",
    13: "Calibration hauteur Z avec buse chaude",
    14: "Nettoyage de la buse (extrusion)",
    15: "Vérification position haute température",
    16: "Calibration capteur de filament",
    17: "Calibration du débit",
    18: "Calibration de l'offset Z",
    19: "Mise à jour du firmware AMS",
    20: "Scan du lit",
    21: "Première couche - inspection",
    22: "Identification du type de filament",
    23: "Calibration caméra",
    24: "Homing du toolhead",
    25: "Nettoyage de la buse",
    26: "Vérification extrudeur",
    35: "Découpe du filament",
}


def _num(value, default=None):
    """Conversion robuste en nombre (None si absent/illisible)."""
    if value is None:
        return default
    try:
        return float(value)
    except (TypeError, ValueError):
        return default


def map_state(report, hms_severity=None, hms_messages=None):
    """Transforme un payload report Bambu en dict plat {logicalId: value}.

    `report` = section 'print' du message MQTT (ou message complet : on gère les deux).
    `hms_severity` / `hms_messages` : déjà décodés par bambu/hms.py (injectés ici
    pour rester sur une seule remontée cohérente).
    Retourne uniquement les clés effectivement présentes (évite d'écraser des cmds
    avec None lors de reports partiels).
    """
    p = report.get("print", report) if isinstance(report, dict) else {}
    out = {}

    def put(key, value):
        if value is not None:
            out[key] = value

    # État haut niveau
    gstate = p.get("gcode_state")
    if gstate is not None:
        put("printer_state", GCODE_STATE.get(gstate, gstate))

    stg = p.get("stg_cur")
    if stg is not None:
        try:
            put("stage", STAGE_CUR.get(int(stg), "Étape %s" % stg))
        except (TypeError, ValueError):
            put("stage", str(stg))

    put("progress", _num(p.get("mc_percent")))
    put("layer_num", _num(p.get("layer_num")))
    put("total_layer", _num(p.get("total_layer_num")))
    put("remaining_time", _num(p.get("mc_remaining_time")))

    job = p.get("subtask_name") or p.get("gcode_file")
    if job:
        # Ne garde que le nom de fichier, sans chemin
        put("job_name", str(job).replace("\\", "/").split("/")[-1])

    # Températures
    put("nozzle_temp", _num(p.get("nozzle_temper")))
    put("nozzle_target", _num(p.get("nozzle_target_temper")))
    put("bed_temp", _num(p.get("bed_temper")))
    put("bed_target", _num(p.get("bed_target_temper")))
    put("chamber_temp", _num(p.get("chamber_temper")))

    # Ventilateurs : valeurs Bambu sur 0-15 -> conversion en %
    def fan_pct(raw):
        v = _num(raw)
        if v is None:
            return None
        return round(v / 15.0 * 100)
    put("fan_speed", fan_pct(p.get("cooling_fan_speed")))
    put("aux_fan_speed", fan_pct(p.get("big_fan1_speed")))
    put("chamber_fan_speed", fan_pct(p.get("big_fan2_speed")))

    # Vitesse d'impression
    spd_lvl = p.get("spd_lvl")
    if spd_lvl is not None:
        levels = {1: "Silencieux", 2: "Standard", 3: "Sport", 4: "Ludicrous"}
        try:
            put("print_speed_lvl", levels.get(int(spd_lvl), str(spd_lvl)))
        except (TypeError, ValueError):
            put("print_speed_lvl", str(spd_lvl))
    put("print_speed_mag", _num(p.get("spd_mag")))

    put("wifi_signal", _num(str(p.get("wifi_signal", "")).replace("dBm", "").strip()))

    # Lumière chambre : lights_report = [{node:'chamber_light', mode:'on'/'off'}]
    lights = p.get("lights_report")
    if isinstance(lights, list):
        for light in lights:
            if light.get("node") == "chamber_light":
                put("light_state", 1 if light.get("mode") == "on" else 0)
                break

    put("nozzle_diameter", p.get("nozzle_diameter"))
    put("nozzle_type", p.get("nozzle_type"))

    # HMS (injecté pré-décodé)
    if hms_severity is not None:
        put("hms_severity", hms_severity)
    if hms_messages is not None:
        put("hms_messages", hms_messages)

    return out
