# Roadmap — BambuJab

Ordre de priorité défini avec l'auteur. Version courante : **0.6.0-beta**.

## ✅ Fait récemment (0.6.x)
- Widget : **auto-rafraîchissement** (~7 s) et **bouton Rafraîchir fonctionnel** (redessine la carte sans recharger la page).
- Widget : **bannière d'erreur d'impression** mise en avant (HMS Fatal/Sérieux) + badge d'alerte.
- 3 états imprimante (En ligne / En veille / Éteinte) via sonde de joignabilité.
- Ampoule cliquable, flux caméra MJPEG live, mode Cloud (comptes SSO inclus).

## 🎯 Priorité 1 — Sécurité (#3)
**Chiffrement au repos des secrets de configuration.**
- Chiffrer `access_code` et `cloud_token` en base (Jeedom ne chiffre pas le JSON de config par défaut ; ils sont aujourd'hui surtout masqués à l'affichage).
- Utiliser `utils::encrypt()` / `utils::decrypt()` au niveau `getConfiguration`/`setConfiguration`.
- Vérifier qu'aucun secret ne transite en clair vers le front (formulaire, AJAX).

## 🎯 Priorité 2 — Robustesse cloud (#2)
**Rafraîchissement automatique du jeton cloud Bambu.**
- Le token expire (~3 mois) → aujourd'hui la remontée s'arrête silencieusement.
- Détecter l'expiration (erreur d'auth MQTT/API) et **renouveler** le token (refresh token si disponible, sinon inviter à se reconnecter).
- Cmd/alerte « jeton cloud à renouveler » + log clair.

## ✅ Priorité 3 — Fichiers & impression en Cloud (#1) — FAIT (voie locale)
- **Fichiers (liste/envoi)** disponibles en mode Cloud via l'**IP locale** (FTPS), comme la caméra —
  l'imprimante reste joignable sur le réseau. Validé : 274 fichiers listés en cloud.
- **Lancement d'impression** via **MQTT cloud** (best-effort, dépendant de l'ACS Bambu).
- **Limite** : le pur distant (imprimante hors du réseau de Jeedom) nécessiterait l'**upload S3 cloud**
  (endpoints non officiels, non documentés publiquement, soumis à l'ACS) — reporté tant que la voie
  locale couvre le besoin courant.

## Ensuite (dans l'ordre)

### 4. Boutons d'action dans le widget
Pause / Reprise / Stop (confirmation pour Stop), en plus de l'ampoule.

### 5. Vignette du modèle en cours
Afficher l'aperçu du plateau (image embarquée par Bambu) dans le widget.

### 6. Types génériques Jeedom
Températures en `TEMPERATURE`, progression en pourcentage, etc. → widgets natifs + historisation/courbes.

### 7. Gestion des erreurs d'impression — messages lisibles
- Résoudre les **codes HMS** en **texte humain** (base HMS Bambu / wiki `ha-bambulab`), au lieu du code brut.
- Événement/déclencheur dédié « erreur d'impression » et « impression terminée » exploitable en scénario.
- (Les codes + sévérité sont déjà remontés dans `hms_severity` / `hms_messages` : notification possible dès maintenant via scénario.)

### 8. Flux caméra multi-spectateurs
Proxy partagé (1 connexion imprimante redistribuée à N spectateurs) + coupure auto après X min d'inactivité.

### 9. Documentation en ligne
Publier `aldarande.github.io/bambujab` (GitHub Pages) pour que les liens de doc du plugin résolvent.

### 10. Traductions complètes
Compléter de/it/es (docs et i18n) au niveau de fr/en.

### 11. Intégration continue GitHub
Workflow CI : `php -l` + `py_compile` (+ prettier) à chaque push.

### 12. Release & Market
Tag `v0.6.0`, icône dédiée, soumission au Market Jeedom.

### 13. Historisation
Activer l'historique sur progression / températures pour les graphiques Jeedom.
