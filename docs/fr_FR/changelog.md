# Changelog BambuJab

>**IMPORTANT**
>
>S'il n'y a pas d'information sur la mise à jour, c'est qu'elle concerne uniquement de la documentation, une traduction ou du texte.

# 0.7.4 (beta)

- 🐛 **Correction d'affichage** : la carte du widget pouvait s'étirer sur toute la largeur de la fenêtre. Largeur désormais bornée (max 440 px) tout en restant fluide sur mobile.

# 0.7.3 (beta)

- 🔌 **Statut de connexion** : nouvelle info `Connecté à l'imprimante` (lien MQTT au broker), distincte de « En ligne » (données fraîches). Pastille verte/grise dans le widget pour vérifier d'un coup d'œil que le plugin dialogue bien avec l'imprimante, même en veille.

# 0.7.2 (beta)

- 🐛 **Correction** : la fenêtre **Fichiers** ne s'affichait pas (page grisée sans contenu) en mode édition d'une imprimante. La modal est déplacée hors du panneau masqué.

# 0.7.1 (beta)

- 🐛 **Correction** : erreur SQL (`Unknown column 'SECRET_KEYS'`) empêchant la création de l'équipement sur certaines installations.

# 0.7.0 (beta)

- ⚠️ **Erreurs d'impression en clair** : les codes HMS sont traduits en messages lisibles (base Bambu) + bannière d'erreur dans le widget.
- 🔒 **Chiffrement au repos** des secrets (code d'accès, jetons cloud).
- 🔁 **Rafraîchissement automatique du jeton cloud** (cron) + alerte avant panne.
- 🧩 **Type de widget au choix** : carte BambuJab ou widget Jeedom standard (configurable).
- 📁 **Fichiers & impression en mode Cloud** (via l'IP locale) ; caméra aussi.
- ▶️⏸️⏹️ **Boutons pause/reprise/arrêt** directement dans le widget ; **auto-rafraîchissement** du widget.
- 📈 **Historisation** de la progression et des températures. Intégration continue (CI) ajoutée.

# 0.6.0 (beta)

- 📷 **Flux vidéo live (MJPEG)** de la caméra dans le widget (connexion persistante, ~1 fps A1/P1), au lieu d'images figées.
- 🟢💤🔌 **3 états** d'imprimante : En ligne / En veille / Éteinte (sonde de joignabilité réseau pour distinguer veille et extinction).
- 💡 **Lumière cliquable** directement dans le widget ; barre de titre (nom → config, don, rafraîchir).
- 🎨 Bobines AMS **numérotées et réparties** sur la largeur du widget.
- ☁️ Mode **Cloud** finalisé : comptes **Google/Apple/Facebook** pris en charge (jeton), récupération fiable de l'identifiant MQTT, caméra locale possible en cloud via l'IP locale.
- 🔒 **Durcissement sécurité** : pages config/caméra réservées aux admins, apikey Jeedom en en-tête HTTP (plus en URL), TLS non vérifié documenté (LAN auto-signé), suppression d'un script de template.
- 🧹 Redémarrage automatique du démon au changement de connexion ; coupure robuste du flux caméra ; correctifs divers.

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
