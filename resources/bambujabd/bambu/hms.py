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

import logging

LOGGER = logging.getLogger(__name__)

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


def decode_hms(report):
    """Retourne (severity_str, messages_str).

    severity_str = sévérité maximale courante ('Aucune' si pas d'alerte).
    messages_str = lignes 'Sévérité : CODE' jointes par ' | ' (vide si aucune).
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
        messages.append("%s : %s" % (sev_label, code_str))
        rank = _SEVERITY_RANK.get(sev_label, 0)
        if rank > max_rank:
            max_rank = rank
            max_label = sev_label

    return (max_label, " | ".join(messages))
