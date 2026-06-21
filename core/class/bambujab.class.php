<?php
/* This file is part of Jeedom.
 * Plugin BambuJab - Aldarande
 * Licence AGPL v3 — https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Surveillance LAN-only des imprimantes 3D BambuLab (MQTT TLS local).
 */

require_once __DIR__ . '/../../../../core/php/core.inc.php';

class bambujab extends eqLogic {

  const DAEMON_PORT_DEFAULT = 55070;

  /* Champs de configuration chiffrés automatiquement (access code = secret). */
  public static $_encryptConfigKey = array('access_code');

  // -------------------------------------------------------------------------
  // Définition des commandes info de monitoring (logicalId => [name, type/subType, unite, generic])
  // Contrat issu de resources/bambujabd/bambu/state_map.py (doc _dev/bambulab-capabilities.md)
  // -------------------------------------------------------------------------
  public static function monitoringCmds() {
    return array(
      'online'            => array('En ligne',          'binary',  '',    'GENERIC_INFO'),
      'model'             => array('Modèle',            'string',  '',    'GENERIC_INFO'),
      'printer_state'     => array('État imprimante',   'string',  '',    'GENERIC_INFO'),
      'stage'             => array('Étape courante',    'string',  '',    'GENERIC_INFO'),
      'progress'          => array('Progression',       'numeric', '%',   'GENERIC_INFO'),
      'layer_num'         => array('Couche courante',   'numeric', '',    'GENERIC_INFO'),
      'total_layer'       => array('Couches totales',   'numeric', '',    'GENERIC_INFO'),
      'remaining_time'    => array('Temps restant',     'numeric', 'min', 'GENERIC_INFO'),
      'job_name'          => array('Nom du job',        'string',  '',    'GENERIC_INFO'),
      'nozzle_temp'       => array('Température buse',   'numeric', '°C',  'TEMPERATURE'),
      'nozzle_target'     => array('Consigne buse',     'numeric', '°C',  'TEMPERATURE'),
      'bed_temp'          => array('Température plateau','numeric', '°C',  'TEMPERATURE'),
      'bed_target'        => array('Consigne plateau',  'numeric', '°C',  'TEMPERATURE'),
      'chamber_temp'      => array('Température chambre','numeric', '°C',  'TEMPERATURE'),
      'fan_speed'         => array('Ventilateur pièce', 'numeric', '%',   'GENERIC_INFO'),
      'aux_fan_speed'     => array('Ventilateur aux.',  'numeric', '%',   'GENERIC_INFO'),
      'chamber_fan_speed' => array('Ventilateur chambre','numeric','%',   'GENERIC_INFO'),
      'print_speed_lvl'   => array('Profil de vitesse', 'string',  '',    'GENERIC_INFO'),
      'print_speed_mag'   => array('Vitesse',           'numeric', '%',   'GENERIC_INFO'),
      'wifi_signal'       => array('Signal Wi-Fi',      'numeric', 'dBm', 'GENERIC_INFO'),
      'light_state'       => array('Lumière chambre',   'binary',  '',    'LIGHT_STATE'),
      'nozzle_diameter'   => array('Diamètre buse',     'string',  'mm',  'GENERIC_INFO'),
      'nozzle_type'       => array('Type de buse',      'string',  '',    'GENERIC_INFO'),
      'hms_severity'      => array('Gravité alerte HMS','string',  '',    'GENERIC_INFO'),
      'hms_messages'      => array('Alertes HMS',       'string',  '',    'GENERIC_INFO'),
      'camera_on'         => array('Caméra active',     'binary',  '',    'GENERIC_INFO'),
    );
  }

  // -------------------------------------------------------------------------
  // Port daemon (configurable pour éviter les collisions entre plugins)
  // -------------------------------------------------------------------------
  public static function getPort() {
    return config::byKey('socketport', __CLASS__, self::DAEMON_PORT_DEFAULT);
  }

  // -------------------------------------------------------------------------
  // Dépendances Python (venv + paho-mqtt)
  // -------------------------------------------------------------------------
  public static function dependancy_info() {
    $return = array();
    $return['log'] = log::getPathToLog(__CLASS__ . '_dependancy');
    $return['progress_file'] = jeedom::getTmpFolder(__CLASS__) . '/dependancy';
    if (file_exists($return['progress_file'])) {
      $return['state'] = 'in_progress';
      return $return;
    }
    // On interroge directement le python du venv (pas de source activate, fragile
    // sous sh : un échec silencieux retomberait sur le python système sans paho).
    $venv_python = __DIR__ . '/../../resources/venv/bin/python3';
    $ok = '';
    if (file_exists($venv_python)) {
      $ok = shell_exec(escapeshellarg($venv_python) . ' -c "import paho.mqtt.client" >/dev/null 2>&1 && echo ok');
    }
    $return['state'] = (trim((string)$ok) === 'ok') ? 'ok' : 'nok';
    return $return;
  }

  public static function dependancy_install() {
    log::remove(__CLASS__ . '_dependancy');
    return array(
      'script' => __DIR__ . '/../../resources/install_dep.sh ' . jeedom::getTmpFolder(__CLASS__) . '/dependancy',
      'log'    => log::getPathToLog(__CLASS__ . '_dependancy'),
    );
  }

  public static function backupExclude() {
    return array(
      'resources/venv',
      'resources/bambujabd/jeedom/__pycache__',
      'resources/bambujabd/bambu/__pycache__',
    );
  }

  // -------------------------------------------------------------------------
  // Daemon
  // -------------------------------------------------------------------------
  public static function deamon_info() {
    $return = array('launchable' => 'ok', 'launchable_message' => '', 'state' => 'nok', 'log' => __CLASS__);
    $pid_file = jeedom::getTmpFolder(__CLASS__) . '/daemon.pid';
    if (file_exists($pid_file)) {
      $pid = intval(trim(file_get_contents($pid_file)));
      if ($pid > 0) {
        $alive = function_exists('posix_kill') ? @posix_kill($pid, 0) : @file_exists("/proc/$pid");
        if ($alive) { $return['state'] = 'ok'; }
      }
    }
    if (self::dependancy_info()['state'] !== 'ok') {
      $return['launchable'] = 'nok';
      $return['launchable_message'] = __('Dépendances non installées', __FILE__);
    }
    if (count(self::enabledInstances()) === 0) {
      $return['launchable'] = 'nok';
      $return['launchable_message'] = __('Aucune imprimante configurée (IP + code d\'accès requis)', __FILE__);
    }
    return $return;
  }

  /** Instances actives complètes (id/ip/serial/access_code). */
  private static function enabledInstances() {
    $instances = array();
    foreach (eqLogic::byType(__CLASS__) as $eqLogic) {
      if (!$eqLogic->getIsEnable()) { continue; }
      $mode   = trim((string)$eqLogic->getConfiguration('conn_mode', 'lan'));
      $serial = trim((string)$eqLogic->getConfiguration('serial', ''));
      if ($mode === 'cloud') {
        // Cloud : token + uid (u_<id>) + broker région ; serial obligatoire (choisi).
        $token = trim((string)$eqLogic->getConfiguration('cloud_token', ''));
        $user  = trim((string)$eqLogic->getConfiguration('cloud_username', ''));
        $host  = trim((string)$eqLogic->getConfiguration('cloud_mqtt_host', ''));
        if ($token === '' || $user === '' || $host === '' || $serial === '') { continue; }
        $instances[] = array(
          'id'       => $eqLogic->getId(),
          'mode'     => 'cloud',
          'host'     => $host,
          'port'     => 8883,
          'username' => $user,
          'token'    => $token,
          'serial'   => $serial,
        );
      } else {
        // LAN : IP + access code ; serial optionnel (auto-découverte).
        $ip   = trim((string)$eqLogic->getConfiguration('ip', ''));
        $code = trim((string)$eqLogic->getConfiguration('access_code', ''));
        if ($ip === '' || $code === '') { continue; }
        $instances[] = array(
          'id'          => $eqLogic->getId(),
          'mode'        => 'lan',
          'ip'          => $ip,
          'serial'      => $serial,
          'access_code' => $code,
        );
      }
    }
    return $instances;
  }

  public static function deamon_start($_automatic = false) {
    self::deamon_stop();
    $daemon_info = self::deamon_info();
    if ($daemon_info['launchable'] !== 'ok') {
      throw new Exception(__('Impossible de lancer le démon', __FILE__) . ' : ' . $daemon_info['launchable_message']);
    }

    $instances = self::enabledInstances();

    $jeedom_port = config::byKey('port', 'network', 80);
    $jeedom_comp = config::byKey('urlcomplement', 'network', '');
    $callback    = 'http://127.0.0.1:' . $jeedom_port . $jeedom_comp . '/plugins/bambujab/core/php/callback.php';

    $pid_file = jeedom::getTmpFolder(__CLASS__) . '/daemon.pid';
    $log_file = log::getPathToLog(__CLASS__);
    // IMPORTANT : ne PAS utiliser realpath() ici. venv/bin/python3 est un symlink
    // vers le python système ; realpath() le résoudrait et contournerait le venv
    // (paho-mqtt installé uniquement dans le venv deviendrait introuvable).
    $python   = __DIR__ . '/../../resources/venv/bin/python3';
    $daemon   = __DIR__ . '/../../resources/bambujabd/bambujabd.py';

    // SECURITY : apikey Jeedom et access codes des imprimantes transitent par
    // variables d'environnement (jamais en CLI/ps ni en log).
    $cmd  = 'BAMBUJAB_APIKEY=' . escapeshellarg(jeedom::getApiKey(__CLASS__)) . ' ';
    $cmd .= 'BAMBUJAB_INSTANCES=' . escapeshellarg(json_encode($instances)) . ' ';
    $cmd .= escapeshellarg($python) . ' ' . escapeshellarg($daemon);
    $cmd .= ' --socketport ' . self::getPort();
    $cmd .= ' --callback '   . escapeshellarg($callback);
    $cmd .= ' --pid '        . escapeshellarg($pid_file);
    $cmd .= ' --cycle 0.5';
    $cmd .= ' --loglevel '   . escapeshellarg(log::getLogLevel(__CLASS__));
    $cmd .= ' >> ' . escapeshellarg($log_file) . ' 2>&1 &';

    log::add(__CLASS__, 'info', 'bambujab.class.php::deamon_start() — démarrage (port ' . self::getPort() . ', ' . count($instances) . ' imprimante(s))');
    exec($cmd);

    $i = 0;
    while ($i < 30) {
      if (self::deamon_info()['state'] === 'ok') { break; }
      sleep(1);
      $i++;
    }
    if (self::deamon_info()['state'] !== 'ok') {
      throw new Exception(__('Le démon n\'a pas démarré dans le délai imparti', __FILE__));
    }
  }

  public static function deamon_stop() {
    $pid_file = jeedom::getTmpFolder(__CLASS__) . '/daemon.pid';
    if (file_exists($pid_file)) {
      $pid = intval(trim(file_get_contents($pid_file)));
      if ($pid > 0) {
        shell_exec('kill -15 ' . $pid . ' 2>/dev/null');
        // Attendre la mort effective du processus (sinon le port reste occupé
        // et le redémarrage échoue avec "Address already in use").
        $isAlive = function ($p) {
          return function_exists('posix_kill') ? @posix_kill($p, 0) : @file_exists("/proc/$p");
        };
        for ($i = 0; $i < 20 && $isAlive($pid); $i++) { usleep(150000); }
        if ($isAlive($pid)) { shell_exec('kill -9 ' . $pid . ' 2>/dev/null'); usleep(300000); }
      }
      @unlink($pid_file);
    }
    // Filet de sécurité : libère le port socket dans tous les cas.
    $port = intval(self::getPort());
    if ($port > 0) {
      shell_exec('fuser -k ' . $port . '/tcp > /dev/null 2>&1');
      usleep(200000);
    }
  }

  /** Envoi d'une commande descendante au démon via le socket TCP. */
  public function sendToDaemon($command, $extra = array()) {
    $value = array_merge(array('apikey' => jeedom::getApiKey(__CLASS__), 'command' => $command), $extra);
    $payload = json_encode($value);
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket === false) {
      throw new Exception(__('Création du socket impossible', __FILE__));
    }
    if (@socket_connect($socket, '127.0.0.1', intval(self::getPort())) === false) {
      socket_close($socket);
      throw new Exception(__('Connexion au démon impossible (démon arrêté ?)', __FILE__));
    }
    socket_write($socket, $payload . "\n", strlen($payload) + 1);
    socket_close($socket);
  }

  /** Pilotage : envoie une action vers l'imprimante via le démon (v0.2). */
  public function control($action, $params = array()) {
    $this->sendToDaemon('control', array(
      'instance_id' => $this->getId(),
      'action'      => $action,
      'params'      => $params,
    ));
  }

  /** Définition des commandes action de pilotage (logicalId => [nom, subType, generic, min, max]). */
  public static function actionCmds() {
    return array(
      'pause'       => array('Pause',              'other',  '', null, null),
      'resume'      => array('Reprendre',          'other',  '', null, null),
      'stop'        => array('Arrêter',            'other',  '', null, null),
      'home'        => array('Home (G28)',         'other',  '', null, null),
      'light_on'    => array('Lumière ON',         'other',  'LIGHT_ON',  null, null),
      'light_off'   => array('Lumière OFF',        'other',  'LIGHT_OFF', null, null),
      'camera_start'=> array('Caméra ON',          'other',  '', null, null),
      'camera_stop' => array('Caméra OFF',         'other',  '', null, null),
      'speed'       => array('Régler vitesse (1-4)', 'slider', '', 1, 4),
      'nozzle_temp' => array('Régler buse',          'slider', '', 0, 300),
      'bed_temp'    => array('Régler plateau',       'slider', '', 0, 120),
    );
  }

  // -------------------------------------------------------------------------
  // Callback montant : appelé par core/php/callback.php pour chaque équipement
  // $_data = dict plat {logicalId: value} produit par le démon Python
  // -------------------------------------------------------------------------
  public static function callbackDevice($_id, $_data) {
    $eqLogic = eqLogic::byId(intval($_id));
    if (!is_object($eqLogic) || $eqLogic->getEqType_name() !== __CLASS__) { return; }
    $eqLogic->updateFromState($_data);
  }

  public function updateFromState($_data) {
    if (!is_array($_data)) { return; }
    $configChanged = false;
    foreach ($_data as $logicalId => $value) {
      // Clés de configuration (__cfg_*) : persistées dans l'eqLogic, pas en cmd.
      // Cas du n° de série / modèle auto-découverts quand le serial est laissé vide.
      if (strpos($logicalId, '__cfg_') === 0) {
        $key = substr($logicalId, 6);
        if (trim((string)$this->getConfiguration($key, '')) !== trim((string)$value)) {
          $this->setConfiguration($key, $value);
          $configChanged = true;
        }
        continue;
      }
      $cmd = $this->getCmd('info', $logicalId);
      if (!is_object($cmd)) {
        // Création dynamique (indispensable pour les slots AMS détectés à l'exécution)
        $cmd = $this->buildDynamicCmd($logicalId);
      }
      if (is_object($cmd)) {
        $this->checkAndUpdateCmd($logicalId, $value);
      }
    }
    if ($configChanged) {
      // Sauvegarde sans relancer un postSave lourd : on persiste juste la config.
      // Le n° de série servira au prochain démarrage (abonnement ciblé) et au
      // pilotage v0.2. Pas de redémarrage immédiat du démon (déjà connecté).
      $this->save(true);
    }
  }

  /** Crée à la volée une cmd info pour un logicalId non pré-déclaré (AMS, vt_tray). */
  private function buildDynamicCmd($logicalId) {
    list($name, $subType, $unite) = $this->describeDynamicCmd($logicalId);
    if ($name === null) { return null; }
    $cmd = new bambujabCmd();
    $cmd->setName($name);
    $cmd->setEqLogic_id($this->getId());
    $cmd->setLogicalId($logicalId);
    $cmd->setType('info');
    $cmd->setSubType($subType);
    if ($unite !== '') { $cmd->setUnite($unite); }
    $cmd->setIsVisible(1);
    $cmd->setIsHistorized(0);
    $cmd->save();
    return $cmd;
  }

  /** Libellé/type d'un logicalId AMS dynamique (ams_{u}_{s}_*, ams_{u}_*, vt_tray_*). */
  private function describeDynamicCmd($logicalId) {
    if (preg_match('/^ams_(\d+)_(\d+)_type$/', $logicalId, $m)) {
      return array('AMS ' . $m[1] . ' slot ' . $m[2] . ' - filament', 'string', '');
    }
    if (preg_match('/^ams_(\d+)_(\d+)_color$/', $logicalId, $m)) {
      return array('AMS ' . $m[1] . ' slot ' . $m[2] . ' - couleur', 'string', '');
    }
    if (preg_match('/^ams_(\d+)_(\d+)_remain$/', $logicalId, $m)) {
      return array('AMS ' . $m[1] . ' slot ' . $m[2] . ' - restant', 'numeric', '%');
    }
    if (preg_match('/^ams_(\d+)_humidity$/', $logicalId, $m)) {
      return array('AMS ' . $m[1] . ' - humidité', 'string', '');
    }
    if (preg_match('/^ams_(\d+)_temp$/', $logicalId, $m)) {
      return array('AMS ' . $m[1] . ' - température', 'numeric', '°C');
    }
    if ($logicalId === 'ams_active_tray') { return array('AMS - slot actif', 'string', ''); }
    if ($logicalId === 'vt_tray_type')    { return array('Slot externe - filament', 'string', ''); }
    if ($logicalId === 'vt_tray_color')   { return array('Slot externe - couleur', 'string', ''); }
    return array(null, null, null);
  }

  // -------------------------------------------------------------------------
  // FTPS — fichiers présents sur l'imprimante (v0.2b)
  // Secrets (IP/access code) passés au script Python par variables d'env.
  // -------------------------------------------------------------------------
  private function ftpEnvPrefix() {
    // En mode cloud, l'IP LAN n'est pas saisie : on accepte une IP locale dédiée
    // (camera_ip) pour la caméra/FTPS, l'imprimante restant joignable sur le réseau.
    $ip   = trim((string)$this->getConfiguration('ip', ''));
    if ($ip === '') { $ip = trim((string)$this->getConfiguration('camera_ip', '')); }
    $code = trim((string)$this->getConfiguration('access_code', ''));
    if ($ip === '' || $code === '') {
      throw new Exception(__('IP locale ou code d\'accès manquant (renseignez l\'IP locale pour la caméra)', __FILE__));
    }
    return 'BAMBU_IP=' . escapeshellarg($ip) . ' BAMBU_CODE=' . escapeshellarg($code) . ' ';
  }

  private function ftpTool() {
    $python = __DIR__ . '/../../resources/venv/bin/python3';
    $tool   = __DIR__ . '/../../resources/bambujabd/ftp_tool.py';
    if (!file_exists($python) || !file_exists($tool)) {
      throw new Exception(__('Dépendances non installées', __FILE__));
    }
    return escapeshellarg($python) . ' ' . escapeshellarg($tool);
  }

  /** Liste les fichiers imprimables présents sur l'imprimante. */
  public function listFiles() {
    $cmd = $this->ftpEnvPrefix() . 'timeout 25 ' . $this->ftpTool() . ' list 2>/dev/null';
    $out = shell_exec($cmd);
    $data = json_decode(trim((string)$out), true);
    if (!is_array($data) || empty($data['ok'])) {
      throw new Exception(__('Lecture FTPS impossible', __FILE__) . ' : ' . ($data['error'] ?? '—'));
    }
    return $data['files'] ?? array();
  }

  /** Envoie un fichier local (.3mf/.gcode) vers l'imprimante. */
  public function pushFile($_localPath, $_remoteName = null) {
    if (!is_file($_localPath)) {
      throw new Exception(__('Fichier local introuvable', __FILE__));
    }
    $remote = $_remoteName !== null ? $_remoteName : basename($_localPath);
    // Sécurité : nom distant nettoyé (pas de chemin)
    $remote = '/' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename($remote));
    $cmd = $this->ftpEnvPrefix() . 'timeout 120 ' . $this->ftpTool()
         . ' upload ' . escapeshellarg($_localPath) . ' ' . escapeshellarg($remote) . ' 2>/dev/null';
    $out = shell_exec($cmd);
    $data = json_decode(trim((string)$out), true);
    if (!is_array($data) || empty($data['ok'])) {
      throw new Exception(__('Envoi FTPS impossible', __FILE__) . ' : ' . ($data['error'] ?? '—'));
    }
    return $data['remote'] ?? $remote;
  }

  /** Lance l'impression d'un fichier présent sur l'imprimante (via MQTT project_file). */
  public function startPrint($_file, $_options = array()) {
    $params = array_merge(array('file' => $_file), $_options);
    $this->control('project_file', $params);
  }

  /** Exécute l'outil d'auth cloud (login/verify/devices). Secrets passés par env. */
  public static function cloudTool($action, $env) {
    $python = __DIR__ . '/../../resources/venv/bin/python3';
    $tool   = __DIR__ . '/../../resources/bambujabd/cloud_tool.py';
    if (!file_exists($python) || !file_exists($tool)) {
      throw new Exception(__('Dépendances non installées', __FILE__));
    }
    $prefix = '';
    foreach ($env as $k => $v) { $prefix .= $k . '=' . escapeshellarg((string)$v) . ' '; }
    $cmd = $prefix . 'timeout 35 ' . escapeshellarg($python) . ' ' . escapeshellarg($tool)
         . ' ' . escapeshellarg($action) . ' 2>/dev/null';
    $data = json_decode(trim((string)shell_exec($cmd)), true);
    if (!is_array($data)) {
      throw new Exception(__('Réponse du cloud illisible', __FILE__));
    }
    return $data;
  }

  /** Capture une image de la caméra (best-effort P1/A1). Retourne le chemin du JPEG. */
  public function getSnapshot() {
    $python = __DIR__ . '/../../resources/venv/bin/python3';
    $tool   = __DIR__ . '/../../resources/bambujabd/camera_tool.py';
    if (!file_exists($python) || !file_exists($tool)) {
      throw new Exception(__('Dépendances non installées', __FILE__));
    }
    $dir = jeedom::getTmpFolder('bambujab');
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $out = $dir . '/snap_' . $this->getId() . '.jpg';
    // Robustesse : supprime un éventuel fichier précédent non réinscriptible
    // (ex. créé par un autre utilisateur lors d'un test CLI) avant la capture.
    if (file_exists($out) && !is_writable($out)) { @unlink($out); }
    $cmd = $this->ftpEnvPrefix() . 'timeout 15 ' . escapeshellarg($python) . ' '
         . escapeshellarg($tool) . ' ' . escapeshellarg($out) . ' 2>/dev/null';
    $res = json_decode(trim((string)shell_exec($cmd)), true);
    if (!is_array($res) || empty($res['ok']) || !file_exists($out)) {
      throw new Exception(__('Caméra indisponible', __FILE__) . ' : ' . ($res['error'] ?? '—'));
    }
    return $out;
  }

  /** Flux MJPEG continu (multipart) — diffuse les images du port 6000 vers la sortie HTTP.
   *  Appelé par core/php/stream.php après envoi de l'en-tête multipart. Bloque jusqu'à
   *  déconnexion du client (passthru -> SIGPIPE sur le process Python). */
  public function cameraStream() {
    $python = __DIR__ . '/../../resources/venv/bin/python3';
    $tool   = __DIR__ . '/../../resources/bambujabd/camera_tool.py';
    if (!file_exists($python) || !file_exists($tool)) {
      throw new Exception(__('Dépendances non installées', __FILE__));
    }
    $cmd = $this->ftpEnvPrefix() . escapeshellarg($python) . ' ' . escapeshellarg($tool) . ' stream 2>/dev/null';
    passthru($cmd);
  }

  // -------------------------------------------------------------------------
  // Découverte LAN (appelée par l'AJAX) — exécute le démon en mode --discover
  // -------------------------------------------------------------------------
  public static function discover() {
    // Pas de realpath() sur le python du venv (symlink -> python système hors venv).
    $python = __DIR__ . '/../../resources/venv/bin/python3';
    $daemon = __DIR__ . '/../../resources/bambujabd/bambujabd.py';
    if (!file_exists($python) || !file_exists($daemon)) {
      throw new Exception(__('Dépendances non installées — impossible de scanner le réseau', __FILE__));
    }
    // timeout dur (8s) : le scan SSDP est best-effort, il ne doit jamais figer l'AJAX.
    $out = shell_exec('timeout 8 ' . escapeshellarg($python) . ' ' . escapeshellarg($daemon) . ' --discover 2>/dev/null');
    $data = json_decode(trim((string)$out), true);
    return is_array($data) ? $data : array();
  }

  // -------------------------------------------------------------------------
  // Cycle de vie
  // -------------------------------------------------------------------------
  public function postSave() {
    // Crée les commandes de monitoring fixes (idempotent)
    foreach (self::monitoringCmds() as $logicalId => $def) {
      $cmd = $this->getCmd('info', $logicalId);
      if (!is_object($cmd)) {
        $cmd = new bambujabCmd();
        $cmd->setEqLogic_id($this->getId());
        $cmd->setLogicalId($logicalId);
        $cmd->setType('info');
      }
      $cmd->setName(__($def[0], __FILE__));
      $cmd->setSubType($def[1]);
      if ($def[2] !== '') { $cmd->setUnite($def[2]); }
      $cmd->setGeneric_type($def[3]);
      $cmd->save();
    }

    // Commande action "Rafraîchir" (demande un pushall au démon)
    $refresh = $this->getCmd('action', 'refresh');
    if (!is_object($refresh)) {
      $refresh = new bambujabCmd();
      $refresh->setEqLogic_id($this->getId());
      $refresh->setLogicalId('refresh');
      $refresh->setName(__('Rafraîchir', __FILE__));
      $refresh->setType('action');
      $refresh->setSubType('other');
      $refresh->save();
    }

    // Commandes action de pilotage (v0.2)
    foreach (self::actionCmds() as $logicalId => $def) {
      $cmd = $this->getCmd('action', $logicalId);
      if (!is_object($cmd)) {
        $cmd = new bambujabCmd();
        $cmd->setEqLogic_id($this->getId());
        $cmd->setLogicalId($logicalId);
        $cmd->setType('action');
      }
      $cmd->setName(__($def[0], __FILE__));
      $cmd->setSubType($def[1]);
      if ($def[2] !== '') { $cmd->setGeneric_type($def[2]); }
      if ($def[3] !== null) { $cmd->setConfiguration('minValue', $def[3]); }
      if ($def[4] !== null) { $cmd->setConfiguration('maxValue', $def[4]); }
      // Lien d'affichage : lumière ON/OFF reflètent l'état light_state
      if (in_array($logicalId, array('light_on', 'light_off'), true)) {
        $light = $this->getCmd('info', 'light_state');
        if (is_object($light)) { $cmd->setValue($light->getId()); }
      }
      $cmd->save();
    }

    // Redémarrage automatique du démon si les paramètres de connexion ont changé
    // (mode/IP/serial/code/cloud…) ou l'activation. Évite que l'utilisateur doive
    // relancer le démon à la main après avoir ajouté/modifié une imprimante.
    // On signe uniquement les champs de connexion : un simple réagencement de
    // commandes ne déclenche pas de redémarrage.
    $sig = md5(json_encode(array(
      $this->getIsEnable(),
      $this->getConfiguration('conn_mode', 'lan'),
      $this->getConfiguration('ip', ''),
      $this->getConfiguration('serial', ''),
      $this->getConfiguration('access_code', ''),
      $this->getConfiguration('cloud_token', ''),
      $this->getConfiguration('cloud_username', ''),
      $this->getConfiguration('cloud_mqtt_host', ''),
    )));
    $cacheKey = 'bambujab::connsig::' . $this->getId();
    if (cache::byKey($cacheKey)->getValue('') !== $sig) {
      cache::set($cacheKey, $sig);
      // Redémarrage différé (après la fin de la requête de sauvegarde)
      try { self::deamon_start(); }
      catch (Exception $e) {
        log::add(__CLASS__, 'warning', 'bambujab.class.php::postSave() — redémarrage démon : ' . $e->getMessage());
      }
    }
  }

  public function preRemove() {
    // Rien de spécial : le redémarrage du démon (sans cette instance) est géré
    // par le core après suppression. On évite de couper le démon des autres
    // imprimantes ici.
  }

  // -------------------------------------------------------------------------
  // Widget tableau de bord — carte imprimante (v0.3)
  // -------------------------------------------------------------------------
  private function val($logicalId, $default = '') {
    $cmd = $this->getCmd('info', $logicalId);
    return is_object($cmd) ? $cmd->execCmd() : $default;
  }

  public function toHtml($_version = 'dashboard') {
    $state    = (string)$this->val('printer_state', '—');
    $stage    = (string)$this->val('stage', '');
    $progress = (float)$this->val('progress', 0);
    $layer    = (int)$this->val('layer_num', 0);
    $total    = (int)$this->val('total_layer', 0);
    $remain   = (int)$this->val('remaining_time', 0);
    $online   = (int)$this->val('online', 0);
    $light    = (int)$this->val('light_state', 0);
    $model    = (string)$this->val('model', 'BambuLab');
    $nozzle   = $this->val('nozzle_temp', '—');
    $nozzleT  = $this->val('nozzle_target', 0);
    $bed      = $this->val('bed_temp', '—');
    $bedT     = $this->val('bed_target', 0);
    $hms      = (string)$this->val('hms_severity', 'Aucune');
    $cameraOn = (int)$this->val('camera_on', 0);
    $id       = $this->getId();
    $mode     = trim((string)$this->getConfiguration('conn_mode', 'lan'));
    // Caméra locale (port 6000) : dispo dès qu'on a une IP locale + code d'accès,
    // que l'imprimante soit en mode LAN ou Cloud (elle reste joignable sur le réseau).
    $camIp     = trim((string)$this->getConfiguration('ip', ''));
    if ($camIp === '') { $camIp = trim((string)$this->getConfiguration('camera_ip', '')); }
    $hasCamera = ($camIp !== '' && trim((string)$this->getConfiguration('access_code', '')) !== '');
    if (!$hasCamera) { $cameraOn = 0; }
    $modeBadge = ($mode === 'cloud')
      ? '<span class="jbb-mode" title="Cloud BambuLab">☁ Cloud</span>'
      : '<span class="jbb-mode" title="Réseau local">🏠 LAN</span>';

    // Couleur d'état
    $stateColors = array(
      'Impression' => '#16a34a', 'En pause' => '#d97706', 'Terminé' => '#0ea5e9',
      'Échec' => '#dc2626', 'Inactif' => '#64748b', 'Préparation' => '#0891b2',
    );
    $sc = isset($stateColors[$state]) ? $stateColors[$state] : '#64748b';
    if ($online !== 1) { $sc = '#475569'; $state = __('Hors ligne', __FILE__); }

    // Temps restant lisible
    $rt = '';
    if ($remain > 0) {
      $h = intdiv($remain, 60); $m = $remain % 60;
      $rt = ($h > 0 ? $h . 'h' : '') . sprintf('%02dmin', $m);
    }

    // Slots AMS (chips colorés)
    $chips = '';
    foreach ($this->getCmd('info') as $cmd) {
      if (preg_match('/^(ams_\d+_\d+|vt_tray)_color$/', $cmd->getLogicalId(), $m)) {
        $color = (string)$cmd->execCmd();
        if ($color === '' || strtoupper($color) === '#00000000') { continue; }
        $typeCmd = $this->getCmd('info', $m[1] . '_type');
        $type = is_object($typeCmd) ? (string)$typeCmd->execCmd() : '';
        if ($type === '' || $type === 'Vide') { continue; }
        $label = ($m[1] === 'vt_tray') ? __('Ext', __FILE__) : str_replace('ams_', '', $m[1]);
        $chips .= '<span class="jbb-chip" title="' . htmlspecialchars($type) . '">'
                . '<span class="jbb-dot" style="background:' . htmlspecialchars($color) . ';"></span>'
                . htmlspecialchars($type) . '</span>';
      }
    }
    if ($chips === '') { $chips = '<span class="jbb-muted">' . __('Aucun filament détecté', __FILE__) . '</span>'; }

    $hmsBadge = ($hms !== 'Aucune' && $hms !== '')
      ? '<span class="jbb-hms" title="HMS">⚠ ' . htmlspecialchars($hms) . '</span>' : '';

    ob_start(); ?>
<div class="jbb-card" data-state="<?php echo htmlspecialchars($state); ?>">
  <style>
  .jbb-card{position:relative;border-radius:16px;padding:16px 18px;color:#e5e7eb;
    background:linear-gradient(145deg,rgba(30,41,59,.92),rgba(15,23,42,.96));
    border:1px solid rgba(148,163,184,.18);box-shadow:0 8px 28px rgba(0,0,0,.35);overflow:hidden;}
  .jbb-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
  .jbb-model{font-weight:700;font-size:1.05em;letter-spacing:.3px;}
  .jbb-badge{font-size:.78em;font-weight:600;padding:3px 10px;border-radius:999px;color:#fff;}
  .jbb-light{margin-left:8px;font-size:1em;}
  .jbb-barwrap{height:10px;border-radius:999px;background:rgba(148,163,184,.18);overflow:hidden;margin:10px 0 6px;}
  .jbb-bar{height:100%;border-radius:999px;transition:width .6s ease;}
  .jbb-row{display:flex;justify-content:space-between;font-size:.82em;color:#cbd5e1;margin-bottom:10px;}
  .jbb-temps{display:flex;gap:14px;margin:10px 0;font-size:.86em;}
  .jbb-temp{flex:1;background:rgba(148,163,184,.08);border-radius:10px;padding:8px 10px;text-align:center;}
  .jbb-temp b{display:block;font-size:1.15em;color:#f1f5f9;}
  .jbb-temp small{color:#94a3b8;}
  .jbb-ams{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;}
  .jbb-chip{display:inline-flex;align-items:center;gap:6px;font-size:.78em;padding:3px 9px;border-radius:999px;
    background:rgba(148,163,184,.12);border:1px solid rgba(148,163,184,.2);}
  .jbb-dot{width:11px;height:11px;border-radius:50%;border:1px solid rgba(255,255,255,.35);}
  .jbb-muted{color:#64748b;font-size:.8em;}
  .jbb-hms{font-size:.74em;color:#fca5a5;margin-left:8px;}
  .jbb-stage{font-size:.78em;color:#94a3b8;}
  .jbb-mode{font-size:.68em;color:#94a3b8;margin-left:8px;padding:2px 7px;border-radius:999px;background:rgba(148,163,184,.12);}
  .jbb-titlebar{display:flex;align-items:center;justify-content:space-between;margin:-4px -4px 10px;
    padding-bottom:8px;border-bottom:1px solid rgba(148,163,184,.15);}
  .jbb-name{color:#f1f5f9;font-weight:700;font-size:1.02em;text-decoration:none;cursor:pointer;}
  .jbb-name:hover{color:#34d399;text-decoration:none;}
  .jbb-tools{display:flex;align-items:center;gap:6px;}
  .jbb-tool{width:28px;height:28px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;
    color:#cbd5e1;background:rgba(148,163,184,.12);border:1px solid rgba(148,163,184,.18);cursor:pointer;
    text-decoration:none;transition:all .15s;}
  .jbb-tool:hover{background:rgba(52,211,153,.2);color:#fff;}
  .jbb-cam{margin:0 0 12px;border-radius:10px;overflow:hidden;background:#000;text-align:center;}
  .jbb-cam img{max-width:100%;max-height:240px;width:auto;height:auto;display:block;margin:auto;object-fit:contain;}
  </style>
  <div class="jbb-titlebar">
    <a class="jbb-name" href="index.php?v=d&p=bambujab&m=bambujab&id=<?php echo $id; ?>" title="<?php echo __('Ouvrir la configuration', __FILE__); ?>"><?php echo htmlspecialchars($this->getName()); ?></a>
    <span class="jbb-tools">
      <?php if ($hasCamera) { ?>
      <span class="jbb-tool" title="<?php echo __('Caméra (afficher/masquer le flux)', __FILE__); ?>" onclick="(function(){var id=<?php echo $id; ?>;var w=document.getElementById('jbbCamWrap'+id);var i=document.getElementById('jbbCam'+id);if(!w||!i){return;}if((i.getAttribute('src')||'').indexOf('stream.php')===-1){w.style.display='block';i.src='plugins/bambujab/core/php/stream.php?id='+id;}else{i.src='';w.style.display='none';}})();return false;"><i class="fas fa-video"></i></span>
      <?php } ?>
      <a class="jbb-tool" href="https://ko-fi.com/aldarande" target="_blank" rel="noopener" title="<?php echo __('Faire un don', __FILE__); ?>"><i class="fas fa-mug-hot"></i></a>
      <span class="jbb-tool" title="<?php echo __('Rafraîchir', __FILE__); ?>" onclick="(function(){try{$.ajax({type:'POST',url:'plugins/bambujab/core/ajax/bambujab.ajax.php',data:{action:'pushall',id:<?php echo $id; ?>},dataType:'json'});}catch(e){}})();return false;"><i class="fas fa-sync"></i></span>
    </span>
  </div>
  <?php if ($hasCamera) { ?>
  <div class="jbb-cam" id="jbbCamWrap<?php echo $id; ?>" style="display:<?php echo $cameraOn === 1 ? 'block' : 'none'; ?>;"><img id="jbbCam<?php echo $id; ?>" <?php if ($cameraOn === 1) { echo 'src="plugins/bambujab/core/php/stream.php?id=' . $id . '"'; } ?> alt="<?php echo __('Caméra', __FILE__); ?>"></div>
  <?php } ?>
  <div class="jbb-head">
    <div><span class="jbb-model">🖨 <?php echo htmlspecialchars($model); ?></span>
      <i class="fas fa-lightbulb jbb-light" style="color:<?php echo $light ? '#fbbf24' : '#475569'; ?>;"></i>
      <?php echo $modeBadge; ?><?php echo $hmsBadge; ?></div>
    <span class="jbb-badge" style="background:<?php echo $sc; ?>;"><?php echo htmlspecialchars($state); ?></span>
  </div>
  <div class="jbb-barwrap"><div class="jbb-bar" style="width:<?php echo max(0, min(100, $progress)); ?>%;background:<?php echo $sc; ?>;"></div></div>
  <div class="jbb-row">
    <span><?php echo round($progress); ?>% <span class="jbb-stage"><?php echo htmlspecialchars($stage); ?></span></span>
    <span><?php if ($total > 0) { echo __('Couche', __FILE__) . ' ' . $layer . '/' . $total; } ?><?php if ($rt !== '') { echo ' · ⏱ ' . $rt; } ?></span>
  </div>
  <div class="jbb-temps">
    <div class="jbb-temp"><small>🔥 <?php echo __('Buse', __FILE__); ?></small><b><?php echo round((float)$nozzle); ?>°</b><small><?php echo $nozzleT > 0 ? '→ ' . round((float)$nozzleT) . '°' : ''; ?></small></div>
    <div class="jbb-temp"><small>▬ <?php echo __('Plateau', __FILE__); ?></small><b><?php echo round((float)$bed); ?>°</b><small><?php echo $bedT > 0 ? '→ ' . round((float)$bedT) . '°' : ''; ?></small></div>
  </div>
  <div class="jbb-ams"><?php echo $chips; ?></div>
</div>
    <?php
    return ob_get_clean();
  }
}

class bambujabCmd extends cmd {

  public function execute($_options = array()) {
    /** @var bambujab $eqLogic */
    $eqLogic = $this->getEqLogic();
    $logicalId = $this->getLogicalId();

    if ($logicalId === 'refresh') {
      $eqLogic->sendToDaemon('pushall', array('instance_id' => $eqLogic->getId()));
      return;
    }

    // Caméra : active/désactive le flux affiché dans le widget (côté Jeedom)
    if ($logicalId === 'camera_start' || $logicalId === 'camera_stop') {
      $eqLogic->checkAndUpdateCmd('camera_on', $logicalId === 'camera_start' ? 1 : 0);
      return;
    }

    // Actions à paramètre (slider) : vitesse / consignes de température
    if (in_array($logicalId, array('speed', 'nozzle_temp', 'bed_temp'), true)) {
      $value = isset($_options['slider']) ? $_options['slider'] : 0;
      $eqLogic->control($logicalId, array('param' => $value));
      return;
    }

    // Actions simples (pause/resume/stop/home/light_on/light_off)
    if (array_key_exists($logicalId, bambujab::actionCmds())) {
      $eqLogic->control($logicalId);
      return;
    }
  }
}
