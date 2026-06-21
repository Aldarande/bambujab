<p align="center">
  <img src="plugin_info/bambujab_icon.png" alt="BambuJab" width="96">
</p>

<h1 align="center">BambuJab</h1>

<p align="center">
  <strong>Surveillez et pilotez vos imprimantes 3D BambuLab depuis Jeedom — 100 % réseau local.</strong><br>
  <em>Pas de cloud Bambu, vos données restent chez vous.</em>
</p>

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/licence-AGPL%20v3-blue.svg" alt="AGPL v3"></a>
  <img src="https://img.shields.io/badge/Jeedom-%E2%89%A5%204.4-success.svg" alt="Jeedom 4.4+">
  <img src="https://img.shields.io/badge/version-0.3.0--beta-orange.svg" alt="version">
</p>

---

## ✨ Fonctionnalités

- 🖨️ **Surveillance temps réel** (MQTT local) : état d'impression, étape en cours, progression, couche, temps restant, températures (buse / plateau / chambre), ventilateurs, vitesse, signal Wi-Fi, lumière, usure et type de buse.
- 🎨 **Gestion de l'AMS** : type et **couleur** de filament par slot, humidité, slot actif, bobine externe — création **dynamique** des commandes selon les AMS détectés.
- ⚠️ **Alertes HMS** décodées (gravité + code).
- 🎮 **Pilotage** : pause / reprise / arrêt, home, lumière, profil de vitesse, consignes de température, chargement / déchargement filament AMS.
- 📁 **Fichiers (FTPS)** : lister les projets présents sur l'imprimante, **envoyer** un `.3mf`/`.gcode`, **relancer** une impression avec mapping AMS et options.
- 📷 **Caméra** (best-effort, P1/A1) : capture d'image de la chambre.
- 🧩 **Widget tableau de bord** dédié (progression, AMS coloré, températures, alertes).
- 🔎 **Découverte réseau** et **auto-détection** du modèle et du n° de série.

## 🔒 Vie privée & sécurité

- **LAN-only** : communication directe avec l'imprimante (MQTT TLS `:8883`, FTPS `:990`). Aucune connexion au cloud Bambu.
- Le **code d'accès** est chiffré dans la base Jeedom et n'apparaît **jamais** dans les logs.
- Les identifiants transitent par variables d'environnement, jamais en ligne de commande.

## 📋 Pré-requis

- Jeedom **≥ 4.4**.
- Une imprimante BambuLab (**X1/X1C, P1P/P1S, A1/A1 mini**) avec le **Mode LAN activé** (Réglages › Réseau).
- Python 3 (installé automatiquement avec les dépendances).

## 🚀 Installation

1. Installez le plugin depuis le Market Jeedom (ou copiez ce dépôt dans `plugins/bambujab`).
2. Activez le plugin, puis cliquez sur **« Installer les dépendances »** (création d'un venv Python isolé : `paho-mqtt`, `requests`).
3. Activez le **démon**.
4. **Ajouter** une imprimante : renseignez **IP** + **Code d'accès** (le n° de série et le modèle sont auto-détectés). Sauvegardez.

> 💡 Le n° de série peut être laissé vide : il est découvert automatiquement à la première connexion.

## 🖼️ Compatibilité

`smart` · `luna` · `atlas` · `rpi` · `docker` · `diy` · `mobile`

## ❤️ Soutenir le projet

BambuJab est **gratuit et open-source** (AGPL v3), développé bénévolement. Si le plugin vous est utile :

- [☕ Ko-fi](https://ko-fi.com/aldarande) · [💜 GitHub Sponsors](https://github.com/sponsors/Aldarande)

## 📜 Licence

Distribué sous licence **GNU AGPL v3**. Voir [LICENSE](LICENSE).

## 🙌 Crédits

Auteur : **Aldarande**. Intégration inspirée des travaux communautaires
[pybambu](https://github.com/greghesp/pybambu),
[ha-bambulab](https://github.com/greghesp/ha-bambulab) et
[OpenBambuAPI](https://github.com/Doridian/OpenBambuAPI).
BambuLab® est une marque de Bambu Lab — ce plugin n'est pas affilié à Bambu Lab.
