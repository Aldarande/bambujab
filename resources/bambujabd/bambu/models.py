# BambuJab — détection du modèle d'imprimante à partir du n° de série
#
# Best-effort : le préfixe (3 premiers caractères) du n° de série encode le modèle.
# Table issue des observations communautaires (pybambu / ha-bambulab). En cas de
# préfixe inconnu, retourne None (le champ « modèle » reste éditable par l'utilisateur).

SERIAL_PREFIX_TO_MODEL = {
    "00M": "X1 Carbon",
    "00W": "X1",
    "03W": "X1E",
    "01S": "P1P",
    "01P": "P1S",
    "030": "A1 mini",
    "039": "A1",
    "094": "H2D",
}


def model_from_serial(serial):
    """Retourne le nom du modèle déduit du préfixe de série, ou None si inconnu."""
    if not serial or not isinstance(serial, str):
        return None
    return SERIAL_PREFIX_TO_MODEL.get(serial[:3].upper())
