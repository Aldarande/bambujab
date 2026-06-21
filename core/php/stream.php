<?php
/* This file is part of Jeedom.
 * Plugin BambuJab - Aldarande — Licence AGPL v3
 *
 * Flux vidéo MJPEG (multipart/x-mixed-replace) de la caméra de l'imprimante.
 * Connexion persistante au port 6000 -> images diffusées en continu (~1 fps A1/P1).
 * Accès réservé aux utilisateurs connectés. <img src="stream.php?id=..">
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

// Flux long : pas de limite de temps, pas de compression ni de bufferisation.
// ignore_user_abort(false) : si le client (navigateur) ferme, PHP doit s'arrêter
// pour que passthru rende la main et que le process Python soit coupé (SIGPIPE).
ignore_user_abort(false);
@set_time_limit(0);
@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', '0');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) { @ob_end_clean(); }

header('Content-Type: multipart/x-mixed-replace; boundary=frame');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Connection: close');
header('X-Accel-Buffering: no'); // désactive le buffering d'un éventuel proxy

try {
  $eqLogic->cameraStream();
} catch (Exception $e) {
  // L'en-tête multipart est déjà envoyé : on ne peut que mettre fin au flux.
  log::add('bambujab', 'warning', 'stream.php — flux interrompu : ' . $e->getMessage());
}
