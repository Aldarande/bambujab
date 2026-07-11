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
  // Revalidation plutôt que cache figé : le navigateur vérifie à chaque fois via
  // l'ETag (taille+date du PNG). Si l'image n'a pas changé -> 304 (quasi instantané) ;
  // sinon elle est re-téléchargée immédiatement. L'aperçu se met ainsi à jour dès
  // qu'une nouvelle impression régénère la vignette, sans attente de péremption.
  clearstatcache(true, $path);
  $etag = '"' . filesize($path) . '-' . filemtime($path) . '"';
  header('Content-Type: image/png');
  header('Cache-Control: no-cache, must-revalidate');
  header('ETag: ' . $etag);
  $ifNoneMatch = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : '';
  if ($ifNoneMatch !== '' && $ifNoneMatch === $etag) {
    http_response_code(304);
    exit;
  }
  header('Content-Length: ' . filesize($path));
  readfile($path);
} catch (Exception $e) {
  http_response_code(503);
  header('Content-Type: text/plain; charset=utf-8');
  echo $e->getMessage();
}
