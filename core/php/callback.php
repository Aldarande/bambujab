<?php
/* This file is part of Jeedom.
 * Plugin BambuJab - Aldarande — Licence AGPL v3
 *
 * Point d'entrée montant démon -> PHP. Le démon Python poste ici les changements
 * d'état des imprimantes au format {"devices":{"<eqLogic_id>":{logicalId:value}}}.
 */

require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

// Authentification par apikey du plugin (jamais logguée).
// Priorité au header Authorization: "apikey <clé>" (le démon ne met plus la clé
// en query string). Repli sur query string pour compatibilité ascendante.
$apikey = '';
$authHeader = '';
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
  $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (function_exists('apache_request_headers')) {
  $headers = apache_request_headers();
  $authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? '');
}
if (stripos($authHeader, 'apikey ') === 0) {
  $apikey = trim(substr($authHeader, 7));
}
// Header X-Api-Key (plus fiable : Apache ne le filtre pas)
if ($apikey === '') {
  if (isset($_SERVER['HTTP_X_API_KEY'])) {
    $apikey = $_SERVER['HTTP_X_API_KEY'];
  } elseif (function_exists('apache_request_headers')) {
    $h = apache_request_headers();
    $apikey = $h['X-Api-Key'] ?? ($h['x-api-key'] ?? '');
  }
}
if ($apikey === '') { $apikey = init('apikey'); }
if ($apikey === '' && isset($_GET['apikey'])) { $apikey = $_GET['apikey']; }
if (!jeedom::apiAccess($apikey, 'bambujab')) {
  log::add('bambujab', 'error', 'callback.php — accès refusé (apikey invalide)');
  http_response_code(401);
  echo 'Not authorized';
  die();
}

// Test de connectivité montante (le démon fait un GET au démarrage)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  echo 'OK';
  die();
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
  // Certaines versions de requests envoient en form-encoded
  $data = $_POST;
}

if (isset($data['devices']) && is_array($data['devices'])) {
  foreach ($data['devices'] as $id => $changes) {
    try {
      bambujab::callbackDevice($id, $changes);
    } catch (Exception $e) {
      log::add('bambujab', 'error', 'callback.php — eqLogic #' . $id . ' : ' . $e->getMessage());
    }
  }
}

echo 'OK';
