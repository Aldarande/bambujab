# -*- coding: utf-8 -*-
"""
Tests de bambu/ams.py — état des AMS et des filaments.

Les logicalId produits ici sont créés dynamiquement côté PHP : une clé mal
formée crée une commande fantôme dans l'équipement, et une couleur mal
normalisée donne une pastille noire dans le widget. Les deux passent inaperçus
sans imprimante sous la main.
"""

from bambu.ams import _norm_color, map_ams


def _report(**print_fields):
    return {"print": print_fields}


# -- Structure absente -------------------------------------------------------

def test_pas_d_ams_pas_de_commandes():
    assert map_ams(_report()) == {}
    assert map_ams(_report(ams="pas un dict")) == {}
    assert map_ams("bruit") == {}


# -- Slot actif --------------------------------------------------------------

def test_slot_actif_converti_en_unite_et_position():
    """tray_now est un index absolu (unité * 4 + slot)."""
    assert map_ams(_report(ams={"tray_now": 6}))["ams_active_tray"] == "1-2"
    assert map_ams(_report(ams={"tray_now": "0"}))["ams_active_tray"] == "0-0"


def test_valeurs_sentinelles_du_slot_actif():
    assert map_ams(_report(ams={"tray_now": 255}))["ams_active_tray"] == "Aucun"
    assert map_ams(_report(ams={"tray_now": 254}))["ams_active_tray"] == "Externe"


def test_slot_actif_illisible_ignore():
    assert "ams_active_tray" not in map_ams(_report(ams={"tray_now": "n/a"}))


# -- Couleurs ----------------------------------------------------------------

def test_couleur_bambu_rrggbbaa_reduite_en_rrggbb():
    assert _norm_color("F6DA5AFF") == "#F6DA5A"


def test_couleur_normalisee_en_majuscules():
    """Le widget compare la couleur à '#00000000' : la casse doit être stable."""
    assert _norm_color("f6da5aff") == "#F6DA5A"


def test_couleur_absente_ou_trop_courte():
    assert _norm_color("") is None
    assert _norm_color("ABC") is None
    assert _norm_color(None) is None


# -- Unités et slots ---------------------------------------------------------

def test_unite_ams_complete():
    out = map_ams(_report(ams={"ams": [{
        "id": "0", "humidity": "2", "temp": "28.5",
        "tray": [
            {"id": "0", "tray_type": "PLA", "tray_color": "FFFFFFFF", "remain": "80"},
            {"id": "3", "tray_type": "PETG", "tray_color": "F6DA5AFF", "remain": -1},
        ],
    }]}))
    assert out["ams_0_humidity"] == "2"
    assert out["ams_0_temp"] == 28.5
    assert out["ams_0_0_type"] == "PLA"
    assert out["ams_0_0_color"] == "#FFFFFF"
    assert out["ams_0_0_remain"] == 80.0
    assert out["ams_0_3_type"] == "PETG"
    assert out["ams_0_3_remain"] == -1.0


def test_slot_vide_libelle_explicitement():
    """tray_type vide = slot vide ; le widget filtre sur ce libellé."""
    out = map_ams(_report(ams={"ams": [{"id": 0, "tray": [{"id": 1, "tray_type": ""}]}]}))
    assert out["ams_0_1_type"] == "Vide"


def test_plusieurs_unites_ams_ne_se_recouvrent_pas():
    out = map_ams(_report(ams={"ams": [
        {"id": 0, "tray": [{"id": 0, "tray_type": "PLA"}]},
        {"id": 1, "tray": [{"id": 0, "tray_type": "ABS"}]},
    ]}))
    assert out["ams_0_0_type"] == "PLA"
    assert out["ams_1_0_type"] == "ABS"


def test_unite_ou_slot_malformes_ignores():
    out = map_ams(_report(ams={"ams": [
        "pas un dict",
        {"id": "x", "tray": [{"id": 0, "tray_type": "PLA"}]},
        {"id": 0, "tray": [{"id": "y", "tray_type": "PLA"}, {"id": 0, "tray_type": "PETG"}]},
    ]}))
    assert out == {"ams_0_0_type": "PETG"}


# -- Bobine externe ----------------------------------------------------------

def test_bobine_externe_vt_tray():
    out = map_ams(_report(vt_tray={"tray_type": "PLA", "tray_color": "1A2B3CFF"}))
    assert out["vt_tray_type"] == "PLA"
    assert out["vt_tray_color"] == "#1A2B3C"


def test_bobine_externe_vide():
    assert map_ams(_report(vt_tray={"tray_type": ""}))["vt_tray_type"] == "Vide"
