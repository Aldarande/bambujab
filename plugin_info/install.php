<?php
/* This file is part of Jeedom.
 * Plugin BambuJab - Aldarande — Licence AGPL v3
 */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

function bambujab_install() {
  // Désactive l'installation automatique des dépendances : l'utilisateur clique
  // explicitement sur « Relancer dépendances » (téléchargement venv/pip).
  config::save('dependancyAutoMode', 0, 'bambujab');

  // Cron 2×/jour : vérifie/renouvelle les jetons cloud avant expiration (~3 mois).
  $cron = cron::byClassAndFunction('bambujab', 'cronCloudToken');
  if (!is_object($cron)) {
    $cron = new cron();
    $cron->setClass('bambujab');
    $cron->setFunction('cronCloudToken');
    $cron->setEnable(1);
    $cron->setDeamon(0);
    $cron->setSchedule('37 */12 * * *'); // minute 37 pour éviter les minutes chargées
    $cron->setTimeout(5);
    $cron->save();
  }
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
  $cron = cron::byClassAndFunction('bambujab', 'cronCloudToken');
  if (is_object($cron)) { $cron->remove(); }
}
