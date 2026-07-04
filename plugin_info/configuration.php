<?php
/* This file is part of Jeedom.
 * Plugin BambuJab - Aldarande — Licence AGPL v3
 */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
// Configuration du démon (port socket) : réservé aux administrateurs.
if (!isConnect('admin')) {
  include_file('desktop', '404', 'php');
  die();
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
