<p align="center">
  <img src="plugin_info/bambujab_icon.png" alt="BambuJab" width="96">
</p>

<h1 align="center">BambuJab</h1>

<p align="center">
  <strong>Surveillez et pilotez vos imprimantes 3D BambuLab depuis Jeedom.</strong><br>
  <em>En réseau local (LAN) ou via le cloud Bambu — au choix, par imprimante.</em>
</p>

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/licence-AGPL%20v3-blue.svg" alt="AGPL v3"></a>
  <img src="https://img.shields.io/badge/Jeedom-%E2%89%A5%204.4-success.svg" alt="Jeedom 4.4+">
  <img src="https://img.shields.io/badge/version-0.6.0--beta-orange.svg" alt="version">
</p>

---

## ✨ Fonctionnalités

- 🖨️ **Surveillance temps réel** (MQTT) : état d'impression, étape, progression, couche, temps restant, températures (buse / plateau / chambre), ventilateurs, vitesse, Wi-Fi, lumière, usure et type de buse.
- 🎨 **Gestion de l'AMS** : type et **couleur** de filament par slot, humidité, slot actif, bobine externe — commandes créées **dynamiquement** selon les AMS détectés.
- ⚠️ **Alertes HMS** décodées (gravité + message).
- 🎮 **Pilotage** : pause / reprise / arrêt, home, lumière, profil de vitesse, consignes de température, gestion filament AMS.
- 📁 **Fichiers (FTPS)** : lister les projets sur l'imprimante, **envoyer** un `.3mf`/`.gcode`, **relancer** une impression (mapping AMS + options).
- 📷 **Caméra** : **flux vidéo live** (MJPEG) de la chambre, directement dans le widget (P1/A1).
- 🧩 **Widget tableau de bord** : carte avec progression, **bobines AMS colorées**, températures, alertes, **lumière cliquable** et **3 états** (🟢 En ligne / 💤 En veille / 🔌 Éteinte).
- ☁️ **Mode LAN ou Cloud** au choix : LAN-only (vie privée) **ou** compte Bambu (accès à distance), y compris comptes **Google/Apple/Facebook** (via jeton).
- 🔎 **Découverte réseau** et **auto-détection** du modèle et du n° de série.

## 🔒 Vie privée & sécurité

- **LAN-only** par défaut : communication directe avec l'imprimante (MQTT TLS `:8883`, FTPS `:990`, caméra `:6000`). Aucun cloud.
- **Mode Cloud** optionnel : le mot de passe Bambu n'est **pas** stocké (seul un jeton d'accès chiffré l'est).
- Le **code d'accès** est chiffré et n'apparaît **jamais** dans les logs ; les secrets transitent par variables d'environnement et l'apikey Jeedom par en-tête HTTP.
- Pages sensibles (configuration, caméra) réservées aux **administrateurs**.

> ⚠️ Une imprimante est soit en **mode LAN**, soit en **mode Cloud** (le « Mode LAN seul » la déconnecte du cloud). Choisissez **un seul mode** par imprimante.

## 📋 Pré-requis

- Jeedom **≥ 4.4**.
- Une imprimante BambuLab (**X1/X1C, P1P/P1S, A1/A1 mini**).
- Python 3 (installé automatiquement avec les dépendances).

## 🚀 Installation

1. Installez le plugin depuis le Market Jeedom (ou copiez ce dépôt dans `plugins/bambujab`).
2. Activez le plugin, puis cliquez sur **« Installer les dépendances »** (venv Python isolé : `paho-mqtt`, `requests`).
3. Activez le **démon**.
4. **Ajouter** une imprimante, choisir le mode :
   - **LAN** : IP + Code d'accès (Mode LAN activé sur l'imprimante). N° de série et modèle auto-détectés.
   - **Cloud** : email + mot de passe Bambu (ou jeton pour les comptes Google/Apple/Facebook), puis choisir l'imprimante liée.

## 🖼️ Compatibilité

`smart` · `luna` · `atlas` · `rpi` · `docker` · `diy` · `mobile`

## ❤️ Soutenir le projet

BambuJab est **gratuit et open-source** (AGPL v3), développé bénévolement :

- [☕ Ko-fi](https://ko-fi.com/aldarande) · [💜 GitHub Sponsors](https://github.com/sponsors/Aldarande)

## 📜 Licence

Distribué sous licence **GNU AGPL v3**. Voir [LICENSE](LICENSE).

## 🙌 Crédits

Auteur : **Aldarande**. Intégration inspirée des travaux communautaires
[pybambu](https://github.com/greghesp/pybambu),
[ha-bambulab](https://github.com/greghesp/ha-bambulab) et
[OpenBambuAPI](https://github.com/Doridian/OpenBambuAPI).
BambuLab® est une marque de Bambu Lab — ce plugin n'est pas affilié à Bambu Lab.
