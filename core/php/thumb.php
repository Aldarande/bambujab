<?php
/* This file is part of Jeedom.
 * Plugin BambuJab - Aldarande — Licence AGPL v3
 *
 * Sert la vignette (aperçu du plateau) de l'impression en cours, extraite du .3mf.
 * Réservé aux utilisateurs connectés. <img src="thumb.php?id=..&j=..">
 */

require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');

if (!isConnect()) {
  http_response_code(401);
  die('401');
}

$eqLogic = bambujab::byId(init('id'));
if (!is_object($eqLogic)) {
  http_response_code(404);
  die('404');
}

try {
  $path = $eqLogic->getThumbnail();
  header('Content-Type: image/png');
  header('Cache-Control: max-age=600'); // la vignette ne change qu'entre deux jobs
  header('Content-Length: ' . filesize($path));
  readfile($path);
} catch (Exception $e) {
  http_response_code(503);
  header('Content-Type: text/plain; charset=utf-8');
  echo $e->getMessage();
}
