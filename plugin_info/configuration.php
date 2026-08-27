<?php
/* This file is part of Jeedom.
 * Plugin BambuJab - Aldarande — Licence AGPL v3
 */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
/* Garde d'accès : le panneau de configuration est réservé aux administrateurs.
 * Ni include_file('desktop', '404', 'php') (le fichier n'existe plus en Jeedom 4.6)
 * ni throw (la branche `configure` d'index.php n'a pas de try/catch) : les deux
 * produisent une fatale PHP. `return` interrompt proprement l'inclusion.
 */
if (!isConnect('admin')) {
  if (!headers_sent()) {
    http_response_code(401);
  }
  echo '<div class="alert alert-danger">{{401 - Accès non autorisé}}</div>';
  return;
}
?>
<form class="form-horizontal">
  <fieldset>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Port du démon (socket)}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Port TCP local du démon. À changer uniquement en cas de collision avec un autre plugin.}}"></i></sup>
      </label>
      <div class="col-md-2">
        <input class="configKey form-control" data-l1key="socketport" placeholder="55152"/>
      </div>
    </div>
  </fieldset>
</form>
