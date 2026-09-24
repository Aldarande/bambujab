# -*- coding: utf-8 -*-
"""
Tests de bambu/models.py — modèle déduit du numéro de série.

Le modèle est détecté automatiquement quand l'utilisateur n'a saisi que l'IP et
le code d'accès. Une table fausse afficherait « X1 Carbon » sur une A1 sans
qu'aucune erreur ne soit levée.
"""

import pytest

from bambu.models import model_from_serial


@pytest.mark.parametrize("serial,modele", [
    ("00M09A123456789", "X1 Carbon"),
    ("00W09A123456789", "X1"),
    ("03W09A123456789", "X1E"),
    ("01S09A123456789", "P1P"),
    ("01P09A123456789", "P1S"),
    ("03009A123456789", "A1 mini"),
    ("03909A123456789", "A1"),
    ("09409A123456789", "H2D"),
])
def test_modele_deduit_du_prefixe(serial, modele):
    assert model_from_serial(serial) == modele


def test_prefixe_insensible_a_la_casse():
    assert model_from_serial("00m09a123456789") == "X1 Carbon"


def test_prefixe_inconnu_laisse_le_champ_libre():
    """None = « je ne sais pas » : le modèle reste éditable par l'utilisateur."""
    assert model_from_serial("ZZZ09A123456789") is None


def test_serie_absente_ou_invalide():
    assert model_from_serial("") is None
    assert model_from_serial(None) is None
    assert model_from_serial(12345) is None
