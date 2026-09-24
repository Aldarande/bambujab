# -*- coding: utf-8 -*-
"""
Tests de bambu/hms.py — décodage des alertes HMS BambuLab.

« Erreurs traduites en clair » est une promesse mise en avant dans la
description du plugin : c'est le seul endroit où le plugin interprète des
entiers bruts pour en faire un message que l'utilisateur lira. La sévérité est
cachée dans quatre bits du code, et le code lisible est un réassemblage de deux
entiers ; les deux se trompent en silence.

Aucun test ne touche le réseau : la base de libellés est injectée en mémoire.
"""

import pytest

from bambu import hms
from bambu.hms import _format_code, decode_hms

# Alertes synthétiques, sévérité portée par les bits 16-19 de `code`.
ATTR = 0x03001000
CODE_FATAL = 0x00010001
CODE_SERIEUX = 0x00020003
CODE_INFO = 0x00040005


@pytest.fixture(autouse=True)
def base_hms_en_memoire():
    """Neutralise le téléchargement de la base Bambu (aucun accès réseau en test)."""
    hms._HMS_DB = {"0300100000020003": "Filament bloqué dans l'extrudeur"}
    hms._HMS_LANG = "fr"
    yield
    hms._HMS_DB = None
    hms._HMS_LANG = None


# -- Absence d'alerte --------------------------------------------------------

def test_aucune_alerte():
    assert decode_hms({"print": {"hms": []}}) == ("Aucune", "")
    assert decode_hms({"print": {}}) == ("Aucune", "")
    assert decode_hms("bruit") == ("Aucune", "")


# -- Code lisible ------------------------------------------------------------

def test_format_du_code_hms():
    """Le code affiché doit être celui que l'utilisateur retrouve sur le site Bambu."""
    assert _format_code(ATTR, CODE_SERIEUX) == "HMS_0300_1000_0002_0003"


def test_format_du_code_refuse_une_entree_illisible():
    assert _format_code(None, CODE_SERIEUX) is None
    assert _format_code("xx", "yy") is None


# -- Sévérité ----------------------------------------------------------------

@pytest.mark.parametrize("code,attendu", [
    (CODE_FATAL, "Fatal"),
    (CODE_SERIEUX, "Sérieux"),
    (0x00030007, "Commun"),
    (CODE_INFO, "Info"),
])
def test_severite_lue_dans_les_bits_de_poids_fort(code, attendu):
    severite, _ = decode_hms({"print": {"hms": [{"attr": ATTR, "code": code}]}})
    assert severite == attendu


def test_severite_retenue_est_la_plus_grave():
    """Plusieurs alertes simultanées : le badge doit refléter la pire."""
    severite, messages = decode_hms({"print": {"hms": [
        {"attr": ATTR, "code": CODE_INFO},
        {"attr": ATTR, "code": CODE_FATAL},
        {"attr": ATTR, "code": CODE_SERIEUX},
    ]}})
    assert severite == "Fatal"
    assert messages.count(" | ") == 2


def test_severite_inconnue_ne_fait_pas_disparaitre_l_alerte():
    severite, messages = decode_hms({"print": {"hms": [{"attr": ATTR, "code": 0x000A0001}]}})
    assert severite == "Inconnu"
    assert "HMS_0300_1000_000A_0001" in messages


# -- Message humain ----------------------------------------------------------

def test_libelle_humain_resolu_depuis_la_base():
    _, messages = decode_hms({"print": {"hms": [{"attr": ATTR, "code": CODE_SERIEUX}]}})
    assert messages == "Sérieux : Filament bloqué dans l'extrudeur (HMS_0300_1000_0002_0003)"


def test_code_brut_si_libelle_absent_de_la_base():
    """Base non téléchargée ou code inconnu : on affiche au moins le code."""
    _, messages = decode_hms({"print": {"hms": [{"attr": ATTR, "code": CODE_FATAL}]}})
    assert messages == "Fatal : HMS_0300_1000_0001_0001"


# -- Entrées malformées ------------------------------------------------------

def test_entrees_malformees_ignorees_sans_masquer_les_autres():
    severite, messages = decode_hms({"print": {"hms": [
        "pas un dict",
        {"attr": ATTR},
        {"attr": ATTR, "code": CODE_SERIEUX},
    ]}})
    assert severite == "Sérieux"
    assert " | " not in messages
