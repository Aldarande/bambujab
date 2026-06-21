# BambuJab — construction des payloads MQTT de pilotage (v0.2)
#
# Chaque action Jeedom est traduite en payload JSON publié sur device/{serial}/request.
# Référence protocole : OpenBambuAPI (Doridian) / pybambu.
#
# Actions supportées (v0.2a) :
#   pause, resume, stop            -> {"print":{...,"command":"pause|resume|stop"}}
#   home                           -> gcode_line G28
#   light_on, light_off            -> {"system":{...,"command":"ledctrl",...}}
#   speed (param 1..4)             -> {"print":{...,"command":"print_speed","param":"n"}}
#   nozzle_temp (param °C)         -> gcode_line M104 S{t}
#   bed_temp (param °C)            -> gcode_line M140 S{t}
#   fan (param 0..100)             -> gcode_line M106 S{0..255}

import itertools

_seq = itertools.count(1)


def _seq_id():
    return str(next(_seq))


def _print(command, **extra):
    payload = {"print": {"sequence_id": _seq_id(), "command": command}}
    payload["print"].update(extra)
    return payload


def _gcode(line):
    # gcode_line attend une ou plusieurs lignes terminées par \n
    return _print("gcode_line", param=line if line.endswith("\n") else line + "\n")


def build_payload(action, params=None):
    """Traduit (action, params) en payload MQTT, ou retourne None si action inconnue."""
    params = params or {}

    if action == "pause":
        return _print("pause")
    if action == "resume":
        return _print("resume")
    if action == "stop":
        return _print("stop")
    if action == "home":
        return _gcode("G28")

    if action in ("light_on", "light_off"):
        return {
            "system": {
                "sequence_id": _seq_id(),
                "command": "ledctrl",
                "led_node": "chamber_light",
                "led_mode": "on" if action == "light_on" else "off",
                "led_on_time": 500,
                "led_off_time": 500,
                "loop_times": 0,
                "interval_time": 0,
            }
        }

    if action == "speed":
        lvl = str(int(_clamp(params.get("param", 2), 1, 4)))
        return _print("print_speed", param=lvl)

    if action == "nozzle_temp":
        t = int(_clamp(params.get("param", 0), 0, 320))
        return _gcode("M104 S%d" % t)

    if action == "bed_temp":
        t = int(_clamp(params.get("param", 0), 0, 120))
        return _gcode("M140 S%d" % t)

    if action == "fan":
        pct = _clamp(params.get("param", 0), 0, 100)
        return _gcode("M106 S%d" % int(pct * 255 / 100))

    if action == "ams_load":
        # Sélection/chargement d'un slot AMS (target = index absolu u*4+s ; 254 = externe)
        return _print("ams_change_filament",
                      target=int(params.get("target", 0)),
                      curr_temp=int(params.get("curr_temp", 220)),
                      tar_temp=int(params.get("tar_temp", 220)))
    if action == "ams_unload":
        return _print("ams_change_filament", target=255, curr_temp=int(params.get("curr_temp", 220)), tar_temp=0)

    if action == "project_file":
        # Lance l'impression d'un fichier déjà présent sur l'imprimante.
        # NB : c'est l'action la plus dépendante du firmware/modèle.
        name = str(params.get("file", "")).lstrip("/")
        plate = int(params.get("plate", 1))
        ams_mapping = params.get("ams_mapping") or [0]
        return {
            "print": {
                "sequence_id": _seq_id(),
                "command": "project_file",
                "param": "Metadata/plate_%d.gcode" % plate,
                "subtask_name": name.rsplit("/", 1)[-1],
                "url": "file:///sdcard/%s" % name,
                "bed_type": "auto",
                "timelapse": bool(params.get("timelapse", False)),
                "bed_leveling": bool(params.get("bed_leveling", True)),
                "flow_cali": bool(params.get("flow_cali", False)),
                "vibration_cali": bool(params.get("vibration_cali", True)),
                "layer_inspect": bool(params.get("layer_inspect", True)),
                "use_ams": bool(params.get("use_ams", True)),
                "ams_mapping": ams_mapping,
                "profile_id": "0", "project_id": "0",
                "subtask_id": "0", "task_id": "0",
            }
        }

    return None


def _clamp(value, lo, hi):
    try:
        v = float(value)
    except (TypeError, ValueError):
        v = lo
    return max(lo, min(hi, v))
