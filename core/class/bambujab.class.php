<?php
/* This file is part of Jeedom.
 * Plugin BambuJab - Aldarande
 * Licence AGPL v3 — https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Surveillance LAN-only des imprimantes 3D BambuLab (MQTT TLS local).
 */

require_once __DIR__ . '/../../../../core/php/core.inc.php';

class bambujab extends eqLogic {

  const DAEMON_PORT_DEFAULT = 55152;
  const WIDGET_CSS_VERSION = '060'; // bump pour invalider le cache du CSS widget
  const ENC_PREFIX = 'enc:';        // marqueur des valeurs de config chiffrées au repos

  // Champs de configuration sensibles chiffrés en base (utils::encrypt).
  // Préfixe "_" obligatoire : DB::getFields() liste par Reflection toutes les
  // propriétés de la classe (statiques incluses) et les insère comme colonnes SQL,
  // sauf celles préfixées par "_".
  private static $_SECRET_KEYS = array('access_code', 'cloud_token', 'cloud_refresh_token');

  /** Chiffre les secrets avant enregistrement (idempotent grâce au marqueur ENC_PREFIX). */
  public function preSave() {
    foreach (self::$_SECRET_KEYS as $key) {
      $val = (string)$this->getConfiguration($key, '');
      if ($val !== '' && strpos($val, self::ENC_PREFIX) !== 0) {
        $this->setConfiguration($key, self::ENC_PREFIX . utils::encrypt($val));
      }
    }
  }

  /** Lit un secret de config en le déchiffrant si nécessaire (compat valeurs en clair). */
  private function getSecret($key) {
    $val = (string)$this->getConfiguration($key, '');
    if (strpos($val, self::ENC_PREFIX) === 0) {
      return (string)utils::decrypt(substr($val, strlen(self::ENC_PREFIX)));
    }
    return $val;
  }

  /* Champs de configuration chiffrés automatiquement (access code = secret). */
  public static $_encryptConfigKey = array('access_code');

  // -------------------------------------------------------------------------
  // Définition des commandes info de monitoring (logicalId => [name, type/subType, unite, generic])
  // Contrat issu de resources/bambujabd/bambu/state_map.py (doc _dev/bambulab-capabilities.md)
  // -------------------------------------------------------------------------
  public static function monitoringCmds() {
    return array(
      'online'            => array('En ligne',          'binary',  '',    'GENERIC_INFO'),
      'connected'         => array('Connecté à l\'imprimante', 'binary', '', 'GENERIC_INFO'),
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
      'reachable'         => array('Joignable (réseau)','binary',  '',    'GENERIC_INFO'),
      'cloud_token_ok'    => array('Jeton cloud valide','binary',  '',    'GENERIC_INFO'),
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
      $return['launchable_message'] = self::missingConfigMessage();
    }
    return $return;
  }

  /**
   * Diagnostic : aucune instance complète. On détaille, pour le premier équipement
   * actif incomplet, les champs manquants (LAN ou cloud) au lieu d'un message
   * générique orienté LAN — beaucoup plus parlant pour l'utilisateur.
   */
  private static function missingConfigMessage() {
    $enabled = array();
    foreach (eqLogic::byType(__CLASS__) as $eqLogic) {
      if ($eqLogic->getIsEnable()) { $enabled[] = $eqLogic; }
    }
    if (count($enabled) === 0) {
      return __('Aucune imprimante configurée', __FILE__);
    }
    foreach ($enabled as $eqLogic) {
      $mode = trim((string)$eqLogic->getConfiguration('conn_mode', 'lan'));
      $miss = array();
      if ($mode === 'cloud') {
        if (trim((string)$eqLogic->getSecret('cloud_token')) === '')          { $miss[] = __('jeton cloud', __FILE__); }
        if (trim((string)$eqLogic->getConfiguration('cloud_username', '')) === '') { $miss[] = __('identifiant cloud', __FILE__); }
        if (trim((string)$eqLogic->getConfiguration('cloud_mqtt_host', '')) === '') { $miss[] = __('serveur MQTT', __FILE__); }
        if (trim((string)$eqLogic->getConfiguration('serial', '')) === '')     { $miss[] = __('n° de série (imprimante non sélectionnée)', __FILE__); }
        if (count($miss) > 0) {
          return sprintf(__('« %1$s » (cloud) : %2$s manquant. Reconnectez-vous, choisissez l\'imprimante dans la liste, puis Sauvegardez.', __FILE__),
                         $eqLogic->getName(), implode(', ', $miss));
        }
      } else {
        if (trim((string)$eqLogic->getConfiguration('ip', '')) === '') { $miss[] = __('adresse IP', __FILE__); }
        if (trim((string)$eqLogic->getSecret('access_code')) === '')   { $miss[] = __('code d\'accès', __FILE__); }
        if (count($miss) > 0) {
          return sprintf(__('« %1$s » (LAN) : %2$s manquant.', __FILE__),
                         $eqLogic->getName(), implode(', ', $miss));
        }
      }
    }
    return __('Aucune imprimante configurée', __FILE__);
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
        $token = trim((string)$eqLogic->getSecret('cloud_token'));
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
          // IP locale (optionnelle) pour sonder la joignabilité (veille vs éteinte)
          'probe_ip' => trim((string)$eqLogic->getConfiguration('camera_ip', '')),
        );
      } else {
        // LAN : IP + access code ; serial optionnel (auto-découverte).
        $ip   = trim((string)$eqLogic->getConfiguration('ip', ''));
        $code = trim((string)$eqLogic->getSecret('access_code'));
        if ($ip === '' || $code === '') { continue; }
        $instances[] = array(
          'id'          => $eqLogic->getId(),
          'mode'        => 'lan',
          'ip'          => $ip,
          'serial'      => $serial,
          'access_code' => $code,
          'probe_ip'    => $ip, // sonde de joignabilité (veille vs éteinte)
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
    $cmd .= ' --lang '       . escapeshellarg(substr((string)config::byKey('language', 'core', 'fr_FR'), 0, 2));
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
    $code = trim((string)$this->getSecret('access_code'));
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

  /** Cron : vérifie la validité des jetons cloud et les renouvelle (refresh token)
   *  avant expiration. Sinon, alerte (cloud_token_ok = 0) pour éviter une panne
   *  silencieuse quand le jeton Bambu expire (~3 mois). */
  public static function cronCloudToken() {
    foreach (eqLogic::byType(__CLASS__) as $eqLogic) {
      if (!$eqLogic->getIsEnable()) { continue; }
      if ($eqLogic->getConfiguration('conn_mode', 'lan') !== 'cloud') { continue; }
      $token  = trim((string)$eqLogic->getSecret('cloud_token'));
      $region = $eqLogic->getConfiguration('cloud_region', 'global');
      if ($token === '') { continue; }

      $res = self::cloudTool('check', array('BAMBU_TOKEN' => $token, 'BAMBU_REGION' => $region));
      $valid = $res['valid'] ?? null;
      if ($valid === true) { $eqLogic->checkAndUpdateCmd('cloud_token_ok', 1); continue; }
      if ($valid === null)  { continue; } // réseau incertain : on ne conclut rien

      // Jeton expiré -> tenter un renouvellement via le refresh token
      $refresh = trim((string)$eqLogic->getSecret('cloud_refresh_token'));
      if ($refresh !== '') {
        $r = self::cloudTool('refresh', array('BAMBU_REFRESH' => $refresh, 'BAMBU_REGION' => $region));
        if (!empty($r['ok']) && !empty($r['token'])) {
          $eqLogic->setConfiguration('cloud_token', $r['token']); // preSave chiffrera au save()
          if (!empty($r['refresh']))  { $eqLogic->setConfiguration('cloud_refresh_token', $r['refresh']); }
          if (!empty($r['username'])) { $eqLogic->setConfiguration('cloud_username', $r['username']); }
          $eqLogic->save(); // preSave (chiffrement) + postSave (redémarre le démon via signature)
          $eqLogic->checkAndUpdateCmd('cloud_token_ok', 1);
          log::add(__CLASS__, 'info', 'bambujab.class.php::cronCloudToken() — jeton cloud renouvelé pour #' . $eqLogic->getId());
          continue;
        }
      }
      // Pas de renouvellement possible -> alerte
      $eqLogic->checkAndUpdateCmd('cloud_token_ok', 0);
      log::add(__CLASS__, 'warning', 'bambujab.class.php::cronCloudToken() — jeton cloud expiré pour #'
        . $eqLogic->getId() . ' : reconnexion requise (Ajouter/rouvrir l\'équipement, mode Cloud).');
    }
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

  /** Vignette (aperçu du plateau) de l'impression en cours, extraite du .3mf via FTPS
   *  (partiel). Mise en cache par job. Retourne le chemin du PNG. LAN & Cloud (IP locale). */
  public function getThumbnail() {
    $jobCmd = $this->getCmd('info', 'job_name');
    $job = is_object($jobCmd) ? trim((string)$jobCmd->execCmd()) : '';
    if ($job === '') { throw new Exception(__('Aucune impression en cours', __FILE__)); }
    $python = __DIR__ . '/../../resources/venv/bin/python3';
    $tool   = __DIR__ . '/../../resources/bambujabd/thumb_tool.py';
    if (!file_exists($python) || !file_exists($tool)) {
      throw new Exception(__('Dépendances non installées', __FILE__));
    }
    $dir = jeedom::getTmpFolder('bambujab');
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $out = $dir . '/thumb_' . $this->getId() . '.png';
    // Cache : ne re-télécharge que si le job a changé
    $metaKey = 'bambujab::thumbjob::' . $this->getId();
    if (file_exists($out) && is_readable($out) && cache::byKey($metaKey)->getValue('') === $job) {
      return $out;
    }
    if (file_exists($out) && !is_writable($out)) { @unlink($out); }
    $cmd = $this->ftpEnvPrefix() . 'BAMBU_JOB=' . escapeshellarg($job) . ' timeout 25 '
         . escapeshellarg($python) . ' ' . escapeshellarg($tool) . ' ' . escapeshellarg($out) . ' 2>/dev/null';
    $res = json_decode(trim((string)shell_exec($cmd)), true);
    if (!is_array($res) || empty($res['ok']) || !file_exists($out)) {
      throw new Exception(__('Vignette indisponible', __FILE__) . ' : ' . ($res['error'] ?? '—'));
    }
    cache::set($metaKey, $job, 86400 * 30);
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
      // Historisation des grandeurs utiles aux courbes (progression, températures)
      if (in_array($logicalId, array('progress', 'nozzle_temp', 'bed_temp', 'chamber_temp'), true)) {
        $cmd->setIsHistorized(1);
      }
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
    // Type de widget : 'custom' (carte BambuJab, défaut) ou 'standard' (widget Jeedom
    // natif, entièrement configurable via l'interface de widget de Jeedom).
    if ($this->getConfiguration('widget_type', 'custom') === 'standard') {
      return parent::toHtml($_version);
    }
    $state    = (string)$this->val('printer_state', '—');
    $stage    = (string)$this->val('stage', '');
    $progress = (float)$this->val('progress', 0);
    $layer    = (int)$this->val('layer_num', 0);
    $total    = (int)$this->val('total_layer', 0);
    $remain   = (int)$this->val('remaining_time', 0);
    $online   = (int)$this->val('online', 0);
    $connected = (int)$this->val('connected', 0); // lien MQTT au broker (plugin ↔ imprimante)
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
    // Vignette (aperçu du plateau) : dispo si accès local (FTPS) + une impression avec job.
    $jobName = (string)$this->val('job_name', '');
    // Vignette affichée dès qu'un job est connu (impression en cours ou dernier job)
    // et que l'imprimante est joignable en local (FTPS).
    $showThumb = ($hasCamera && $jobName !== '' && $online === 1);
    $thumbTag = $showThumb
      ? '<div class="jbb-thumb"><img src="plugins/bambujab/core/php/thumb.php?id=' . $id . '&j=' . substr(md5($jobName), 0, 8)
        . '" alt="" onerror="this.parentNode.style.display=\'none\'"></div>'
      : '';
    $modeBadge = ($mode === 'cloud')
      ? '<span class="jbb-mode" title="Cloud BambuLab">☁ Cloud</span>'
      : '<span class="jbb-mode" title="Réseau local">🏠 LAN</span>';

    // Couleur d'état
    $stateColors = array(
      'Impression' => '#16a34a', 'En pause' => '#d97706', 'Terminé' => '#0ea5e9',
      'Échec' => '#dc2626', 'Inactif' => '#64748b', 'Préparation' => '#0891b2',
    );
    $sc = isset($stateColors[$state]) ? $stateColors[$state] : '#64748b';
    // Plus de données : l'imprimante BambuLab finit puis se met en veille (elle
    // reste joignable et se reconnecte au réveil) — on garde son dernier état réel
    // et on ajoute un badge "En veille" plutôt qu'un "Hors ligne" trompeur.
    $reachable = (int)$this->val('reachable', 1); // 1 par défaut tant que non sondé
    $sleepBadge = '';
    if ($online !== 1) {
      if ($reachable === 0) {
        // Injoignable sur le réseau -> réellement éteinte
        $sc = '#dc2626';
        $sleepBadge = '<span class="jbb-mode jbb-off" title="' . __('Injoignable sur le réseau — imprimante éteinte', __FILE__) . '">🔌 ' . __('Éteinte', __FILE__) . '</span>';
        if ($state === '' || $state === '—') { $state = __('Éteinte', __FILE__); }
      } else {
        // Joignable mais muette -> en veille
        $sc = '#64748b';
        $sleepBadge = '<span class="jbb-mode jbb-sleep" title="' . __('Joignable mais aucune donnée — imprimante en veille', __FILE__) . '">💤 ' . __('En veille', __FILE__) . '</span>';
        if ($state === '' || $state === '—') { $state = __('En veille', __FILE__); }
      }
    }

    // Temps restant lisible
    $rt = '';
    if ($remain > 0) {
      $h = intdiv($remain, 60); $m = $remain % 60;
      $rt = ($h > 0 ? $h . 'h' : '') . sprintf('%02dmin', $m);
    }

    // Slots AMS : on collecte d'abord (numéro = unité*4 + slot + 1 ; bobine externe = "Ext")
    $slots = array();
    foreach ($this->getCmd('info') as $cmd) {
      $lid = $cmd->getLogicalId();
      if (preg_match('/^ams_(\d+)_(\d+)_color$/', $lid, $m)) {
        $slots[] = array('sort' => intval($m[1]) * 4 + intval($m[2]),
                         'num'  => intval($m[1]) * 4 + intval($m[2]) + 1,
                         'base' => 'ams_' . $m[1] . '_' . $m[2]);
      } elseif ($lid === 'vt_tray_color') {
        $slots[] = array('sort' => 9999, 'num' => __('Ext', __FILE__), 'base' => 'vt_tray');
      }
    }
    usort($slots, function ($a, $b) { return $a['sort'] - $b['sort']; });

    $chips = '';
    foreach ($slots as $s) {
      $color = (string)$this->val($s['base'] . '_color', '');
      if ($color === '' || strtoupper($color) === '#00000000') { continue; }
      $type = (string)$this->val($s['base'] . '_type', '');
      if ($type === '' || $type === 'Vide') { continue; }
      $chips .= '<div class="jbb-spool" title="' . htmlspecialchars($type) . '">'
              . '<span class="jbb-spool-num">' . htmlspecialchars((string)$s['num']) . '</span>'
              . '<span class="jbb-spool-disc" style="background:' . htmlspecialchars($color) . ';"></span>'
              . '<span class="jbb-spool-type">' . htmlspecialchars($type) . '</span></div>';
    }
    if ($chips === '') { $chips = '<span class="jbb-muted">' . __('Aucun filament détecté', __FILE__) . '</span>'; }

    $hmsBadge = ($hms !== 'Aucune' && $hms !== '')
      ? '<span class="jbb-hms" title="HMS">⚠ ' . htmlspecialchars($hms) . '</span>' : '';

    // Bannière d'erreur : alerte HMS Fatal/Sérieux (erreur d'impression) mise en avant.
    $hmsMsg = (string)$this->val('hms_messages', '');
    $errBanner = '';
    if (in_array($hms, array('Fatal', 'Sérieux'), true)) {
      $errBanner = '<div class="jbb-err">⚠ <b>' . htmlspecialchars($hms) . '</b> — '
        . htmlspecialchars($hmsMsg !== '' ? $hmsMsg : __('Erreur signalée par l\'imprimante', __FILE__)) . '</div>';
    }

    // CSS externalisé dans desktop/css/bambujab.css. Sur le dashboard, ce fichier
    // n'est pas chargé automatiquement : on injecte le <link> une seule fois par
    // rendu (drapeau statique) — un éventuel doublon est dédupliqué par le navigateur.
    static $cssLinked = false;
    $cssTag = '';
    if (!$cssLinked) {
      $cssTag = '<link rel="stylesheet" href="plugins/bambujab/desktop/css/bambujab.css?v=' . self::WIDGET_CSS_VERSION . '">';
      $cssLinked = true;
    }

    ob_start(); ?>
<div class="jbb-wrap" id="jbbW<?php echo $id; ?>">
<div class="jbb-card" data-state="<?php echo htmlspecialchars($state); ?>">
  <?php echo $cssTag; ?>
  <div class="jbb-titlebar">
    <a class="jbb-name" href="index.php?v=d&p=bambujab&m=bambujab&id=<?php echo $id; ?>" title="<?php echo __('Ouvrir la configuration', __FILE__); ?>"><span class="jbb-conn <?php echo $connected === 1 ? 'jbb-conn-ok' : 'jbb-conn-ko'; ?>" title="<?php echo $connected === 1 ? __('Connecté à l\'imprimante', __FILE__) : __('Non connecté à l\'imprimante', __FILE__); ?>"></span><?php echo htmlspecialchars($this->getName()); ?></a>
    <span class="jbb-tools">
      <?php if ($hasCamera) { ?>
      <span class="jbb-tool jbb-camtoggle<?php echo $cameraOn === 1 ? ' jbb-on' : ''; ?>" title="<?php echo __('Caméra : allumer / éteindre le flux', __FILE__); ?>" onclick="(function(el){var id=<?php echo $id; ?>;var w=document.getElementById('jbbCamWrap'+id);var i=document.getElementById('jbbCam'+id);if(!w||!i){return;}var on=(i.getAttribute('src')||'').indexOf('stream.php')!==-1;if(on){i.src='';w.style.display='none';el.classList.remove('jbb-on');}else{w.style.display='block';i.src='plugins/bambujab/core/php/stream.php?id='+id;el.classList.add('jbb-on');}})(this);return false;"><i class="fas fa-video"></i></span>
      <?php } ?>
      <a class="jbb-tool" href="https://ko-fi.com/aldarande" target="_blank" rel="noopener" title="<?php echo __('Faire un don', __FILE__); ?>"><i class="fas fa-mug-hot"></i></a>
      <span class="jbb-tool" title="<?php echo __('Rafraîchir', __FILE__); ?>" onclick="(function(){var id=<?php echo $id; ?>;try{$.ajax({type:'POST',url:'plugins/bambujab/core/ajax/bambujab.ajax.php',data:{action:'pushall',id:id},dataType:'json'});}catch(e){}setTimeout(function(){try{$.ajax({type:'POST',url:'plugins/bambujab/core/ajax/bambujab.ajax.php',data:{action:'widget',id:id},dataType:'json',success:function(d){if(d&&d.state==='ok'&&d.result){var w=document.getElementById('jbbW'+id);if(w){var t=document.createElement('div');t.innerHTML=d.result;var n=t.querySelector('#jbbW'+id);w.innerHTML=n?n.innerHTML:d.result;}}}});}catch(e){}},1300);})();return false;"><i class="fas fa-sync"></i></span>
    </span>
  </div>
  <?php if ($hasCamera) { ?>
  <div class="jbb-cam" id="jbbCamWrap<?php echo $id; ?>" style="display:<?php echo $cameraOn === 1 ? 'block' : 'none'; ?>;"><img id="jbbCam<?php echo $id; ?>" <?php if ($cameraOn === 1) { echo 'src="plugins/bambujab/core/php/stream.php?id=' . $id . '"'; } ?> alt="<?php echo __('Caméra', __FILE__); ?>"></div>
  <?php } ?>
  <div class="jbb-head">
    <div><span class="jbb-model">🖨 <?php echo htmlspecialchars($model); ?></span>
      <i class="fas fa-lightbulb jbb-light" title="<?php echo __('Allumer / éteindre la lumière', __FILE__); ?>" data-on="<?php echo $light ? '1' : '0'; ?>" style="color:<?php echo $light ? '#fbbf24' : '#475569'; ?>;" onclick="(function(el){var id=<?php echo $id; ?>;var ns=el.getAttribute('data-on')==='1'?0:1;el.setAttribute('data-on',ns);el.style.color=ns?'#fbbf24':'#475569';try{$.ajax({type:'POST',url:'plugins/bambujab/core/ajax/bambujab.ajax.php',data:{action:'light',id:id,state:ns?'on':'off'},dataType:'json'});}catch(e){}})(this);return false;"></i>
      <?php echo $modeBadge; ?><?php echo $sleepBadge; ?><?php echo $hmsBadge; ?></div>
    <span class="jbb-badge" style="background:<?php echo $sc; ?>;"><?php echo htmlspecialchars($state); ?></span>
  </div>
  <?php echo $errBanner; ?>
  <?php echo $thumbTag; ?>
  <div class="jbb-barwrap"><div class="jbb-bar" style="width:<?php echo max(0, min(100, $progress)); ?>%;background:<?php echo $sc; ?>;"></div></div>
  <div class="jbb-row">
    <span><?php echo round($progress); ?>% <span class="jbb-stage"><?php echo htmlspecialchars($stage); ?></span></span>
    <span><?php if ($total > 0) { echo __('Couche', __FILE__) . ' ' . $layer . '/' . $total; } ?><?php if ($rt !== '') { echo ' · ⏱ ' . $rt; } ?></span>
  </div>
  <?php
  // Boutons de pilotage : pause/reprise + stop selon l'état d'impression.
  $doAct = function ($cmd) use ($id) {
    return "try{\$.ajax({type:'POST',url:'plugins/bambujab/core/ajax/bambujab.ajax.php',data:{action:'doAction',cmd:'" . $cmd . "',id:" . $id . "},dataType:'json'});}catch(e){}";
  };
  if ($state === 'Impression' || $state === 'En pause') { ?>
  <div class="jbb-ctrl">
    <?php if ($state === 'Impression') { ?>
      <span class="jbb-abtn" onclick="(function(){<?php echo $doAct('pause'); ?>})();return false;">⏸ <?php echo __('Pause', __FILE__); ?></span>
    <?php } else { ?>
      <span class="jbb-abtn" onclick="(function(){<?php echo $doAct('resume'); ?>})();return false;">▶ <?php echo __('Reprendre', __FILE__); ?></span>
    <?php } ?>
    <span class="jbb-abtn jbb-stop" onclick="(function(){if(!confirm('<?php echo __('Arrêter l\'impression ?', __FILE__); ?>'))return;<?php echo $doAct('stop'); ?>})();return false;">⏹ <?php echo __('Arrêter', __FILE__); ?></span>
  </div>
  <?php } ?>
  <div class="jbb-temps">
    <div class="jbb-temp"><small>🔥 <?php echo __('Buse', __FILE__); ?></small><b><?php echo round((float)$nozzle); ?>°</b><small><?php echo $nozzleT > 0 ? '→ ' . round((float)$nozzleT) . '°' : ''; ?></small></div>
    <div class="jbb-temp"><small>▬ <?php echo __('Plateau', __FILE__); ?></small><b><?php echo round((float)$bed); ?>°</b><small><?php echo $bedT > 0 ? '→ ' . round((float)$bedT) . '°' : ''; ?></small></div>
  </div>
  <div class="jbb-ams"><?php echo $chips; ?></div>
</div>
<img alt="" style="display:none" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" onload="(function(){var id=<?php echo $id; ?>;window.jbbT=window.jbbT||{};if(window.jbbT[id]){return;}window.jbbT[id]=setInterval(function(){var c=document.getElementById('jbbCam'+id);if(c&&(''+c.getAttribute('src')).indexOf('stream.php')>-1){return;}try{$.ajax({type:'POST',url:'plugins/bambujab/core/ajax/bambujab.ajax.php',data:{action:'widget',id:id},dataType:'json',success:function(d){if(d&&d.state==='ok'&&d.result){var w=document.getElementById('jbbW'+id);if(w){var t=document.createElement('div');t.innerHTML=d.result;var n=t.querySelector('#jbbW'+id);w.innerHTML=n?n.innerHTML:d.result;}}}});}catch(e){}},7000);})();">
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
