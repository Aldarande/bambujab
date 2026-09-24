# -*- coding: utf-8 -*-
"""
Tests de jeedom/jeedom.py — décision de vérification TLS du callback.

Le template `jeedom_com` du core passe `verify=False` en dur. Le callback
transporte l'apikey Jeedom : si l'utilisateur pointe une URL externe, ne pas
vérifier le certificat expose cette clé à un intercepteur sans que rien ne le
signale. On ne relâche le contrôle que là où un certificat auto-signé est
attendu — Jeedom local ou réseau privé.
"""

import pytest

from jeedom.jeedom import _tls_verify


@pytest.mark.parametrize("url", [
    "https://127.0.0.1/core/api/jeeApi.php",
    "https://localhost/core/api/jeeApi.php",
    "https://[::1]/core/api/jeeApi.php",
    "https://192.168.1.42/core/api/jeeApi.php",
    "https://10.0.0.5:8443/core/api/jeeApi.php",
    "https://172.16.3.9/core/api/jeeApi.php",
    "https://169.254.10.2/core/api/jeeApi.php",
])
def test_certificat_non_verifie_en_local(url):
    """Jeedom local en HTTPS : certificat auto-signé attendu, vérification levée."""
    assert _tls_verify(url) is False


@pytest.mark.parametrize("url", [
    "https://jeedom.example.com/core/api/jeeApi.php",
    "https://mon-jeedom.duckdns.org:8443/core/api/jeeApi.php",
    "https://93.184.216.34/core/api/jeeApi.php",
])
def test_certificat_verifie_pour_une_cible_externe(url):
    assert _tls_verify(url) is True


@pytest.mark.parametrize("url", [
    "http://127.0.0.1/core/api/jeeApi.php",
    "http://jeedom.example.com/core/api/jeeApi.php",
])
def test_en_http_le_parametre_est_sans_objet(url):
    """Pas de TLS : la valeur renvoyée ne doit rien casser (défaut sûr)."""
    assert _tls_verify(url) is True


def test_url_absente_ou_illisible():
    assert _tls_verify("") is True
    assert _tls_verify(None) is True
