# Changelog BambuJab

>**IMPORTANT**
>
>S'il n'y a pas d'information sur la mise à jour, c'est qu'elle concerne uniquement de la documentation, une traduction ou du texte.

# 0.5.0 (beta)

- ☁️ **Mode Cloud BambuLab** : choix LAN ou Cloud à la création de l'équipement (champs adaptés).
  Connexion par compte (email + code de vérification), sélection de l'imprimante liée,
  monitoring et pilotage via MQTT cloud. Widget adapté (badge LAN/Cloud ; caméra/FTPS = LAN seulement).
  Comptes **Google/Apple/Facebook** : définir un mot de passe sur le compte Bambu, ou coller un jeton d'accès.
  ⚠️ Le pilotage cloud peut être restreint par BambuLab (Bambu Connect).

# 0.3.0 (beta)

- 📷 Caméra (best-effort P1/A1) : capture d'image de la chambre + rafraîchissement automatique.
- 🧩 Widget tableau de bord dédié (carte : état, progression, couche/temps, températures, AMS coloré, HMS).
- 📁 Fenêtre **Fichiers** : lister, envoyer (`.3mf`/`.gcode`) et relancer une impression.
- ❤️ Bouton et fenêtre de soutien (don).

# 0.2.0 (beta)

- 🎮 Pilotage : pause, reprise, arrêt, home, lumière, vitesse, consignes de température, AMS.
- 📁 FTPS : lister les fichiers présents, envoyer un fichier, relancer un projet (mapping AMS + options).

# 0.1.0 (beta)

- 🖨️ Surveillance temps réel via MQTT local : états, étapes, progression, temps, températures, ventilateurs, vitesse, Wi-Fi, lumière, buse.
- 🎨 Gestion de l'AMS : type, couleur, humidité, slot actif, bobine externe (commandes dynamiques).
- ⚠️ Décodage des alertes HMS.
- 🔎 Découverte réseau, n° de série optionnel (auto-découverte) et modèle auto-détecté.
- 🔒 LAN-only, code d'accès chiffré, aucune fuite de secret dans les logs.
