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

### 4. ✅ Boutons d'action dans le widget
Pause / Reprise / Stop (confirmation) affichés dans le widget pendant l'impression.

### 5. ✅ Vignette du modèle en cours
Aperçu du plateau extrait du `.3mf` : **téléchargement partiel** (les images sont au tout
début du fichier) + lecture directe de `Metadata/plate_1_small.png` (parsing des entêtes
ZIP locaux, pas de sommaire de fin). Identifie le `.3mf` par le nom du job. Caché par job,
affiché dans le widget. **LAN & Cloud** (via l'IP locale). Validé : PNG 128×128 extrait.

### 6. ✅ Types génériques Jeedom
Températures en `TEMPERATURE`, progression avec unité `%`.

### 7. ✅ Gestion des erreurs d'impression — messages lisibles
- Résoudre les **codes HMS** en **texte humain** (base HMS Bambu / wiki `ha-bambulab`), au lieu du code brut.
- Événement/déclencheur dédié « erreur d'impression » et « impression terminée » exploitable en scénario.
- (Les codes + sévérité sont déjà remontés dans `hms_severity` / `hms_messages` : notification possible dès maintenant via scénario.)

### 8. ⏸️ Flux caméra multi-spectateurs (reporté)
Proxy partagé (1 connexion redistribuée à N spectateurs) + coupure auto. Reporté : le
modèle actuel (1 flux/spectateur + garde-fou 10 min) suffit à l'usage domestique.

### 9. 🟡 Documentation en ligne (config prête)
Config Jekyll (`docs/_config.yml` + `docs/index.md`) en place. **Action utilisateur** :
GitHub → Settings › Pages › Deploy from a branch → `main` /docs.

### 10. 🟡 Traductions complètes (docs ✅, i18n UI à compléter)
Docs de/it/es faites. Les chaînes UI (`{{…}}`) restent à traduire — volume important,
idéalement avec l'aide de la communauté.

### 11. ✅ Intégration continue GitHub
Workflow `.github/workflows/ci.yml` : `php -l` + `py_compile` + validation `info.json`.

### 12. ✅ Release
Tag `v0.7.0` publié. Reste : icône dédiée (placeholder en place) + soumission Market Jeedom (action utilisateur).

### 13. ✅ Historisation
Historique activé sur progression et températures (courbes Jeedom).
