<?php
/* This file is part of Jeedom.
 * Plugin BambuJab - Aldarande — Licence AGPL v3
 */

try {
  require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
  include_file('core', 'authentification', 'php');

  if (!isConnect('admin')) {
    throw new Exception(__('401 - Accès non autorisé', __FILE__));
  }

  ajax::init();

  if (init('action') === 'discover') {
    ajax::success(bambujab::discover());
  }

  if (init('action') === 'pushall') {
    $eqLogic = bambujab::byId(init('id'));
    if (!is_object($eqLogic)) {
      throw new Exception(__('Équipement introuvable', __FILE__));
    }
    $eqLogic->sendToDaemon('pushall', array('instance_id' => $eqLogic->getId()));
    ajax::success();
  }

  if (init('action') === 'light') {
    $eqLogic = bambujab::byId(init('id'));
    if (!is_object($eqLogic)) {
      throw new Exception(__('Équipement introuvable', __FILE__));
    }
    $eqLogic->control(init('state') === 'on' ? 'light_on' : 'light_off');
    ajax::success();
  }

  if (init('action') === 'status') {
    $eqLogic = bambujab::byId(init('id'));
    if (!is_object($eqLogic)) {
      throw new Exception(__('Équipement introuvable', __FILE__));
    }
    $get = function ($lid) use ($eqLogic) {
      $c = $eqLogic->getCmd('info', $lid);
      return is_object($c) ? $c->execCmd() : '';
    };
    $reach = $eqLogic->getCmd('info', 'reachable');
    ajax::success(array(
      'mode'      => $eqLogic->getConfiguration('conn_mode', 'lan'),
      'online'    => (int)$get('online'),
      'reachable' => is_object($reach) ? (int)$reach->execCmd() : 1,
      'state'     => (string)$get('printer_state'),
      'model'     => (string)$get('model'),
      'hasCam'    => ($eqLogic->getConfiguration('ip', '') !== '' || $eqLogic->getConfiguration('camera_ip', '') !== ''),
    ));
  }

  if (init('action') === 'cloudLogin') {
    ajax::success(bambujab::cloudTool('login', array(
      'BAMBU_EMAIL'    => init('email'),
      'BAMBU_PASSWORD' => init('password'),
      'BAMBU_REGION'   => init('region', 'global'),
    )));
  }

  if (init('action') === 'cloudVerify') {
    ajax::success(bambujab::cloudTool('verify', array(
      'BAMBU_EMAIL'  => init('email'),
      'BAMBU_CODE'   => init('code'),
      'BAMBU_REGION' => init('region', 'global'),
    )));
  }

  if (init('action') === 'cloudToken') {
    ajax::success(bambujab::cloudTool('token', array(
      'BAMBU_TOKEN'  => init('token'),
      'BAMBU_REGION' => init('region', 'global'),
    )));
  }

  if (init('action') === 'listFiles') {
    $eqLogic = bambujab::byId(init('id'));
    if (!is_object($eqLogic)) {
      throw new Exception(__('Équipement introuvable', __FILE__));
    }
    ajax::success($eqLogic->listFiles());
  }

  if (init('action') === 'startPrint') {
    $eqLogic = bambujab::byId(init('id'));
    if (!is_object($eqLogic)) {
      throw new Exception(__('Équipement introuvable', __FILE__));
    }
    $options = json_decode(init('options', '{}'), true) ?: array();
    $eqLogic->startPrint(init('file'), $options);
    ajax::success();
  }

  if (init('action') === 'uploadFile') {
    $eqLogic = bambujab::byId(init('id'));
    if (!is_object($eqLogic)) {
      throw new Exception(__('Équipement introuvable', __FILE__));
    }
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
      throw new Exception(__('Aucun fichier reçu', __FILE__));
    }
    $name = basename($_FILES['file']['name']);
    if (!preg_match('/\.(3mf|gcode)$/i', $name)) {
      throw new Exception(__('Type de fichier non autorisé (.3mf/.gcode uniquement)', __FILE__));
    }
    $tmp = jeedom::getTmpFolder('bambujab') . '/' . $name;
    if (!move_uploaded_file($_FILES['file']['tmp_name'], $tmp)) {
      throw new Exception(__('Échec de la réception du fichier', __FILE__));
    }
    try {
      $remote = $eqLogic->pushFile($tmp, $name);
      ajax::success($remote);
    } finally {
      @unlink($tmp);
    }
  }

  throw new Exception(__('Aucune méthode correspondante à', __FILE__) . ' : ' . init('action'));
} catch (Exception $e) {
  ajax::error(displayException($e), $e->getCode());
}
