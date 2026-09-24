# -*- coding: utf-8 -*-
"""
Tests de bambu/state_map.py — traduction d'un report MQTT Bambu en commandes
info Jeedom.

C'est le contrat entre le démon et le PHP (createCommands) : un champ mal
converti n'échoue nulle part, il affiche simplement une valeur fausse sur le
dashboard. D'où ces tests sur les conversions non triviales (ventilateurs sur
0-15, temps, nom de job, états) et sur la règle « ne rien émettre pour un champ
absent », qui protège les commandes des reports partiels.
"""

from bambu.state_map import map_state


# ── États d'impression ───────────────────────────────────────────────────────

def test_gcode_state_traduit_en_libelle_lisible():
    assert map_state({"print": {"gcode_state": "RUNNING"}})["printer_state"] == "Impression"
    assert map_state({"print": {"gcode_state": "PAUSE"}})["printer_state"] == "En pause"
    assert map_state({"print": {"gcode_state": "FINISH"}})["printer_state"] == "Terminé"


def test_gcode_state_inconnu_est_remonte_tel_quel():
    """Un état ajouté par un firmware récent doit rester visible, pas disparaître."""
    assert map_state({"print": {"gcode_state": "NEW_STATE"}})["printer_state"] == "NEW_STATE"


def test_section_print_optionnelle():
    """Le report est accepté avec ou sans son enveloppe 'print'."""
    assert map_state({"gcode_state": "RUNNING"})["printer_state"] == "Impression"


def test_report_non_dict_ne_leve_pas():
    assert map_state("bruit") == {}


# ── Étapes mécaniques ────────────────────────────────────────────────────────

def test_stage_traduit_depuis_stg_cur():
    assert map_state({"print": {"stg_cur": 2}})["stage"] == "Préchauffage du lit"
    assert map_state({"print": {"stg_cur": -1}})["stage"] == "Au repos"


def test_stage_accepte_une_valeur_texte():
    assert map_state({"print": {"stg_cur": "7"}})["stage"] == "Homing des axes"


def test_stage_inconnu_reste_identifiable():
    assert map_state({"print": {"stg_cur": 99}})["stage"] == "Étape 99"


# ── Avancement ───────────────────────────────────────────────────────────────

def test_avancement_couches_et_temps_restant():
    out = map_state({"print": {
        "mc_percent": 42, "layer_num": 84, "total_layer_num": 200,
        "mc_remaining_time": 73,
    }})
    assert out["progress"] == 42.0
    assert out["layer_num"] == 84.0
    assert out["total_layer"] == 200.0
    assert out["remaining_time"] == 73.0


def test_zero_pour_cent_est_emis():
    """0 % est une valeur, pas une absence : la barre doit repartir de zéro."""
    assert map_state({"print": {"mc_percent": 0}})["progress"] == 0.0


def test_valeur_illisible_est_ignoree_plutot_qu_ecrasee():
    assert "progress" not in map_state({"print": {"mc_percent": "n/a"}})


# ── Nom du job ───────────────────────────────────────────────────────────────

def test_job_name_reduit_au_nom_de_fichier():
    out = map_state({"print": {"subtask_name": "/data/Metadata/plate_1.gcode"}})
    assert out["job_name"] == "plate_1.gcode"


def test_job_name_gere_les_separateurs_windows():
    out = map_state({"print": {"gcode_file": r"Modeles\test\piece.3mf"}})
    assert out["job_name"] == "piece.3mf"


def test_subtask_name_prioritaire_sur_gcode_file():
    out = map_state({"print": {"subtask_name": "vrai.3mf", "gcode_file": "interne.gcode"}})
    assert out["job_name"] == "vrai.3mf"


# ── Températures et ventilateurs ─────────────────────────────────────────────

def test_temperatures_converties_en_nombres():
    out = map_state({"print": {
        "nozzle_temper": "215.5", "nozzle_target_temper": 220,
        "bed_temper": 60, "bed_target_temper": 60, "chamber_temper": 31,
    }})
    assert out["nozzle_temp"] == 215.5
    assert out["nozzle_target"] == 220.0
    assert out["bed_temp"] == 60.0
    assert out["chamber_temp"] == 31.0


def test_ventilateurs_convertis_de_0_15_vers_pourcent():
    """Bambu code la vitesse des ventilateurs sur 0-15, le widget affiche des %."""
    out = map_state({"print": {
        "cooling_fan_speed": "15", "big_fan1_speed": "0", "big_fan2_speed": "8",
    }})
    assert out["fan_speed"] == 100
    assert out["aux_fan_speed"] == 0
    assert out["chamber_fan_speed"] == 53


def test_ventilateur_absent_non_emis():
    assert "fan_speed" not in map_state({"print": {"gcode_state": "IDLE"}})


# ── Vitesse, wifi, lumière ───────────────────────────────────────────────────

def test_profil_de_vitesse_traduit():
    assert map_state({"print": {"spd_lvl": 2}})["print_speed_lvl"] == "Standard"
    assert map_state({"print": {"spd_lvl": 9}})["print_speed_lvl"] == "9"


def test_signal_wifi_debarrasse_de_son_unite():
    assert map_state({"print": {"wifi_signal": "-48dBm"}})["wifi_signal"] == -48.0


def test_lumiere_chambre_lue_dans_lights_report():
    report = {"print": {"lights_report": [
        {"node": "work_light", "mode": "off"},
        {"node": "chamber_light", "mode": "on"},
    ]}}
    assert map_state(report)["light_state"] == 1
    report["print"]["lights_report"][1]["mode"] = "off"
    assert map_state(report)["light_state"] == 0


def test_lumiere_absente_si_pas_de_noeud_chambre():
    assert "light_state" not in map_state({"print": {"lights_report": [{"node": "work_light", "mode": "on"}]}})


# ── HMS injecté ──────────────────────────────────────────────────────────────

def test_hms_pre_decode_est_repris_tel_quel():
    out = map_state({"print": {}}, hms_severity="Sérieux", hms_messages="Sérieux : bourrage")
    assert out["hms_severity"] == "Sérieux"
    assert out["hms_messages"] == "Sérieux : bourrage"


def test_hms_non_fourni_absent_du_resultat():
    out = map_state({"print": {"gcode_state": "IDLE"}})
    assert "hms_severity" not in out
