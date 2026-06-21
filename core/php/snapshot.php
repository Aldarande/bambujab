<?php
/* This file is part of Jeedom.
 * Plugin BambuJab - Aldarande — Licence AGPL v3
 *
 * Sert une image JPEG de la caméra de l'imprimante (best-effort P1/A1).
 * Accès réservé aux utilisateurs connectés. <img src="snapshot.php?id=..&t=..">
 */

require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');

log::add('bambujab', 'debug', 'snapshot.php — appel id=' . init('id') . ' connecté=' . (isConnect() ? '1' : '0'));

// Flux/capture caméra = donnée sensible (vie privée) : réservé aux administrateurs.
if (!isConnect('admin')) {
  http_response_code(401);
  die('401');
}

$eqLogic = bambujab::byId(init('id'));
if (!is_object($eqLogic)) {
  http_response_code(404);
  die('404');
}

try {
  $path = $eqLogic->getSnapshot();
  log::add('bambujab', 'debug', 'snapshot.php — image servie : ' . filesize($path) . ' octets');
  header('Content-Type: image/jpeg');
  header('Cache-Control: no-store, max-age=0');
  header('Content-Length: ' . filesize($path));
  readfile($path);
} catch (Exception $e) {
  log::add('bambujab', 'warning', 'snapshot.php — échec : ' . $e->getMessage());
  http_response_code(503);
  header('Content-Type: text/plain; charset=utf-8');
  echo $e->getMessage();
}
