<?php
/* This file is part of Jeedom.
 * Plugin BambuJab - Aldarande — Licence AGPL v3
 */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

function bambujab_install() {
  // Désactive l'installation automatique des dépendances : l'utilisateur clique
  // explicitement sur « Relancer dépendances » (téléchargement venv/pip).
  config::save('dependancyAutoMode', 0, 'bambujab');
}

function bambujab_update() {
  bambujab_install();
}

function bambujab_remove() {
  try {
    bambujab::deamon_stop();
  } catch (Exception $e) {
    log::add('bambujab', 'warning', 'install.php::bambujab_remove() — arrêt démon : ' . $e->getMessage());
  }
}
