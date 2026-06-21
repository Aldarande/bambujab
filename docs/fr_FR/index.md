# Plugin BambuJab

Le plugin **BambuJab** permet de **surveiller et piloter vos imprimantes 3D BambuLab** depuis Jeedom, entièrement sur le **réseau local** (LAN-only), sans passer par le cloud Bambu.

Modèles pris en charge : **X1 / X1C, P1P / P1S, A1 / A1 mini**.

# Compatibilité

Le plugin nécessite Jeedom **≥ 4.4** et une imprimante BambuLab avec le **Mode LAN activé**
(sur l'imprimante : *Réglages › Réseau › Mode LAN*).

# Installation

1. Installez le plugin depuis le Market, puis activez-le.
2. Dans l'onglet **Configuration**, cliquez sur **Installer / Relancer les dépendances**.
   Un environnement Python isolé (*venv*) est créé avec `paho-mqtt` et `requests`.
3. Attendez l'état **« Dépendances OK »**, puis **lancez le démon**.

> Le plugin ne se connecte à aucun serveur Bambu : toute la communication est locale.

# Configuration d'une imprimante

Cliquez sur **Ajouter**, ou sur **Rechercher sur le réseau** pour détecter les imprimantes présentes.

| Champ | Description |
|---|---|
| **Adresse IP** | IP locale de l'imprimante. |
| **Code d'accès** | *Access code* (*Réglages › Réseau › Mode LAN*). Stocké chiffré. |
| **Numéro de série** | *Optionnel* — laissé vide, il est **découvert automatiquement**. |
| **Modèle** | *Auto-détecté* d'après le n° de série (modifiable). |

Cochez **Activer** et **Visible**, puis **Sauvegarder**. Le démon se connecte et les commandes se remplissent en quelques secondes.

# Commandes

## Informations (surveillance)

État imprimante, étape courante, progression (%), couche courante / totale, temps restant,
nom du job, températures **buse / plateau / chambre** (et consignes), ventilateurs, profil et
pourcentage de vitesse, signal Wi-Fi, lumière, diamètre / type de buse, gravité et messages **HMS**, **en ligne**.

**AMS** (créées dynamiquement) : par slot → type de filament, **couleur**, niveau restant ;
par unité → humidité, température ; slot actif ; bobine externe.

## Actions (pilotage)

**Pause**, **Reprendre**, **Arrêter**, **Home**, **Lumière ON/OFF**, **Régler vitesse** (1–4),
**Régler buse** (°C), **Régler plateau** (°C), **Rafraîchir**.

# Fichiers de l'imprimante

Sur la fiche d'une imprimante, le bouton **Fichiers** permet de **lister** les projets présents
(`.3mf` / `.gcode`), d'**envoyer** un fichier, et de **relancer** une impression (avec mapping AMS et options).

> ⚠️ Le lancement d'une impression démarre l'imprimante : assurez-vous que le plateau est prêt.

# Caméra

Un panneau **Caméra** (best-effort, surtout P1/A1) capture une image de la chambre, avec un mode
rafraîchissement automatique. La disponibilité dépend du modèle et du firmware.

# Widget

Un widget dédié affiche une carte synthétique : état, progression, couche et temps restant,
températures et filaments AMS colorés.

# Foire aux questions

**Les commandes restent vides / « hors ligne ».** Vérifiez l'IP, le code d'accès et le **Mode LAN**.
Si le n° de série a été laissé vide et que rien ne remonte, renseignez-le (appli Bambu Handy › *Device info*).

**La recherche réseau ne trouve rien.** La découverte SSDP peut être bloquée (ex. Docker bridge) :
utilisez l'**ajout manuel** (IP + code d'accès).

# Sécurité & vie privée

Le code d'accès est chiffré et n'apparaît jamais dans les logs. Aucune donnée n'est envoyée au cloud Bambu.

# Soutien

BambuJab est gratuit et open-source (AGPL v3). Pour soutenir le développement :
[Ko-fi](https://ko-fi.com/aldarande), [GitHub Sponsors](https://github.com/sponsors/Aldarande),
[Liberapay](https://liberapay.com/Aldarande/donate). Merci !

# Changelog

Voir la page [changelog](changelog.md).
