/* This file is part of Jeedom.
 * Plugin BambuJab - Aldarande — Licence AGPL v3
 */

/* Recherche des imprimantes BambuLab sur le réseau local */
$('#bt_discoverPrinters').off('click').on('click', function () {
  $('#bt_discoverPrinters span').html('{{Recherche en cours...}}');
  $.ajax({
    type: 'POST',
    url: 'plugins/bambujab/core/ajax/bambujab.ajax.php',
    data: { action: 'discover' },
    dataType: 'json',
    error: function (request, status, error) {
      handleAjaxError(request, status, error);
      $('#bt_discoverPrinters span').html('{{Rechercher sur le réseau}}');
    },
    success: function (data) {
      $('#bt_discoverPrinters span').html('{{Rechercher sur le réseau}}');
      if (data.state !== 'ok') {
        $('#div_alert').showAlert({ message: data.result, level: 'danger' });
        return;
      }
      var printers = data.result || [];
      if (printers.length === 0) {
        $('#div_alert').showAlert({ message: '{{Aucune imprimante détectée. Vérifiez le Mode LAN et le réseau.}}', level: 'warning' });
        return;
      }
      var msg = '{{Imprimantes détectées}} : ';
      msg += printers.map(function (p) { return (p.model || '?') + ' (' + (p.ip || '?') + ')'; }).join(', ');
      $('#div_alert').showAlert({ message: msg, level: 'success' });
    }
  });
});

/* ───────── Mode de connexion LAN / Cloud ───────── */
function bjbApplyConnMode() {
  var mode = $('#bjb_connMode').value() || 'lan';
  if (mode === 'cloud') {
    $('#bjb_lanFields').hide(); $('#bjb_cloudFields').show();
  } else {
    $('#bjb_lanFields').show(); $('#bjb_cloudFields').hide();
  }
  // Caméra et Fichiers restent disponibles dans les deux modes : ils passent en
  // local via l'IP (LAN) ou l'IP locale de la caméra (Cloud). Ils gèrent proprement
  // l'absence d'IP locale (message d'erreur clair).
  $('#bjb_camSection').show(); $('#bjb_btnFiles').show();
}
$('body').off('change', '#bjb_connMode').on('change', '#bjb_connMode', bjbApplyConnMode);
// Appliqué aussi après le rendu de l'équipement (mode + bandeau de statut)
$('body').off('click.bjbmode', '.li_eqLogic, #bt_addBambuJab').on('click.bjbmode', '.li_eqLogic, #bt_addBambuJab', function () {
  setTimeout(function () {
    bjbApplyConnMode();
    bjbRefreshStatus($('.eqLogicAttr[data-l1key=id]').value());
  }, 300);
});

// Bandeau de statut : mode (LAN/Cloud) + en ligne/hors ligne + modèle/état
function bjbRefreshStatus(id) {
  var $b = $('#bjb_statusBanner');
  if (!id) { $b.hide(); return; }
  $.ajax({
    type: 'POST', url: 'plugins/bambujab/core/ajax/bambujab.ajax.php',
    data: { action: 'status', id: id }, dataType: 'json',
    error: function () { $b.hide(); },
    success: function (d) {
      if (d.state !== 'ok' || !d.result) { $b.hide(); return; }
      var r = d.result, on = (parseInt(r.online, 10) === 1);
      var reach = (parseInt(r.reachable, 10) !== 0);
      var mode = (r.mode === 'cloud') ? '☁️ {{Cloud}}' : '🏠 {{LAN (local)}}';
      var dot = on ? '🟢 {{En ligne}}' : (reach ? '💤 {{En veille}}' : '🔌 {{Éteinte}}');
      var tokenWarn = (r.mode === 'cloud' && parseInt(r.tokenOk, 10) === 0);
      var cls = tokenWarn ? 'alert-warning' : (on ? 'alert-success' : (reach ? 'alert-info' : 'alert-danger'));
      var extra = (r.model ? ' · ' + r.model : '') + (r.state ? ' · ' + r.state : '');
      var warn = tokenWarn ? ' &nbsp;·&nbsp; ⚠️ {{Jeton cloud à renouveler (rouvrez l\'équipement)}}' : '';
      $b.removeClass('alert-success alert-warning alert-info alert-danger')
        .addClass(cls)
        .html('<b>' + mode + '</b> &nbsp;·&nbsp; ' + dot + extra + warn).show();
    }
  });
}

function bjbCloudEnv() {
  return {
    email: $('#bjb_cloudEmail').value(),
    password: $('#bjb_cloudPassword').val(),
    code: $('#bjb_cloudCode').val(),
    region: $('#bjb_cloudRegion').value()
  };
}
function bjbCloudFill(data) {
  // Remplit le sélecteur d'imprimantes et stocke le jeton
  $('#bjb_cloudToken').value(data.token || '');
  $('#bjb_cloudRefresh').value(data.refresh || '');
  $('#bjb_cloudUsername').value(data.username || '');
  $('#bjb_cloudMqttHost').value(data.mqtt_host || '');
  var sel = $('#bjb_cloudDevice'); sel.empty();
  (data.devices || []).forEach(function (d) {
    sel.append('<option value="' + d.serial + '" data-model="' + (d.model || '') + '" data-code="' + (d.access_code || '') + '">'
      + (d.name || d.serial) + ' — ' + (d.model || '?') + (d.online ? '' : ' ({{hors ligne}})') + '</option>');
  });
  $('#bjb_cloudDeviceRow').show();
  bjbCloudPickDevice();
}
function bjbCloudPickDevice() {
  var opt = $('#bjb_cloudDevice option:selected');
  $('.eqLogicAttr[data-l2key=serial]').value(opt.val() || '');
  $('.eqLogicAttr[data-l2key=access_code]').value(opt.attr('data-code') || '');
  $('.eqLogicAttr[data-l2key=model]').value(opt.attr('data-model') || '');
}
$('body').off('change', '#bjb_cloudDevice').on('change', '#bjb_cloudDevice', bjbCloudPickDevice);

$('body').off('click', '#bjb_cloudLogin').on('click', '#bjb_cloudLogin', function () {
  var env = bjbCloudEnv();
  if (!env.email || !env.password) { $('#div_alert').showAlert({ message: '{{Email et mot de passe requis}}', level: 'warning' }); return; }
  $('#bjb_cloudLogin').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> {{Connexion…}}');
  $.ajax({
    type: 'POST', url: 'plugins/bambujab/core/ajax/bambujab.ajax.php',
    data: { action: 'cloudLogin', email: env.email, password: env.password, region: env.region }, dataType: 'json',
    complete: function () { $('#bjb_cloudLogin').prop('disabled', false).html('<i class="fas fa-sign-in-alt"></i> {{Se connecter}}'); },
    error: function (req, st, err) { handleAjaxError(req, st, err); },
    success: function (data) {
      if (data.state !== 'ok' || !data.result || data.result.ok === false) {
        $('#div_alert').showAlert({ message: '{{Échec}} : ' + ((data.result && data.result.error) || data.result), level: 'danger' }); return;
      }
      if (data.result.need_code) {
        $('#bjb_cloudCodeRow').show();
        $('#div_alert').showAlert({ message: '{{Un code a été envoyé par email. Saisissez-le ci-dessous.}}', level: 'info' });
      } else {
        bjbCloudFill(data.result);
        $('#div_alert').showAlert({ message: '{{Connecté. Choisissez votre imprimante.}}', level: 'success' });
      }
    }
  });
});

$('body').off('click', '#bjb_cloudVerify').on('click', '#bjb_cloudVerify', function () {
  var env = bjbCloudEnv();
  if (!env.code) { $('#div_alert').showAlert({ message: '{{Saisissez le code reçu}}', level: 'warning' }); return; }
  $('#bjb_cloudVerify').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
  $.ajax({
    type: 'POST', url: 'plugins/bambujab/core/ajax/bambujab.ajax.php',
    data: { action: 'cloudVerify', email: env.email, code: env.code, region: env.region }, dataType: 'json',
    complete: function () { $('#bjb_cloudVerify').prop('disabled', false).html('<i class="fas fa-check"></i> {{Valider}}'); },
    error: function (req, st, err) { handleAjaxError(req, st, err); },
    success: function (data) {
      if (data.state !== 'ok' || !data.result || data.result.ok === false) {
        $('#div_alert').showAlert({ message: '{{Code invalide}} : ' + ((data.result && data.result.error) || ''), level: 'danger' }); return;
      }
      bjbCloudFill(data.result);
      $('#div_alert').showAlert({ message: '{{Connecté. Choisissez votre imprimante puis Sauvegardez.}}', level: 'success' });
    }
  });
});

/* SSO : afficher le champ jeton + connexion par jeton (Google/Apple/Facebook) */
$('body').off('click', '#bjb_cloudSsoToggle').on('click', '#bjb_cloudSsoToggle', function () {
  $('#bjb_cloudSso').slideToggle(150);
});
$('body').off('click', '#bjb_cloudUseToken').on('click', '#bjb_cloudUseToken', function () {
  var token = $('#bjb_cloudTokenInput').val();
  var region = $('#bjb_cloudRegion').value();
  if (!token) { $('#div_alert').showAlert({ message: '{{Collez un jeton d\'accès}}', level: 'warning' }); return; }
  $('#bjb_cloudUseToken').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
  $.ajax({
    type: 'POST', url: 'plugins/bambujab/core/ajax/bambujab.ajax.php',
    data: { action: 'cloudToken', token: token, region: region }, dataType: 'json',
    complete: function () { $('#bjb_cloudUseToken').prop('disabled', false).html('<i class="fas fa-key"></i> {{Utiliser}}'); },
    error: function (req, st, err) { handleAjaxError(req, st, err); },
    success: function (data) {
      if (data.state !== 'ok' || !data.result || data.result.ok === false) {
        $('#div_alert').showAlert({ message: '{{Jeton invalide}} : ' + ((data.result && data.result.error) || ''), level: 'danger' }); return;
      }
      bjbCloudFill(data.result);
      $('#div_alert').showAlert({ message: '{{Jeton accepté. Choisissez votre imprimante puis Sauvegardez.}}', level: 'success' });
    }
  });
});

/* Bouton don */
$('#bt_donBambuJab').off('click').on('click', function () { $('#modal_donBambuJab').modal('show'); });

/* Ouverture de la modal Fichiers */
$('#bjb_btnFiles').off('click').on('click', function () {
  var id = $('.eqLogicAttr[data-l1key=id]').value();
  if (!id) { $('#div_alert').showAlert({ message: '{{Sauvegardez l\'imprimante d\'abord}}', level: 'warning' }); return; }
  $('#modal_filesBambuJab').modal('show');
  bjbLoadFiles(id);
});
$('#bjb_btnRefreshFiles').off('click').on('click', function () {
  bjbLoadFiles($('.eqLogicAttr[data-l1key=id]').value());
});
$('#bjb_fileSearch').off('keyup').on('keyup', function () {
  var q = $(this).val().toLowerCase();
  $('#bjb_filesList .bjb-file-row').each(function () {
    $(this).toggle($(this).attr('data-name').toLowerCase().indexOf(q) !== -1);
  });
});

function bjbLoadFiles(id) {
  $('#bjb_filesList').html('<div class="text-center" style="padding:20px;"><i class="fas fa-spinner fa-spin fa-2x"></i><br>{{Lecture FTPS en cours…}}</div>');
  $.ajax({
    type: 'POST', url: 'plugins/bambujab/core/ajax/bambujab.ajax.php',
    data: { action: 'listFiles', id: id }, dataType: 'json',
    error: function (req, st, err) { handleAjaxError(req, st, err); $('#bjb_filesList').html('<div class="alert alert-danger">{{Lecture impossible}}</div>'); },
    success: function (data) {
      if (data.state !== 'ok') { $('#bjb_filesList').html('<div class="alert alert-danger">' + data.result + '</div>'); return; }
      var files = (data.result || []).filter(function (f) { return !f.dir; });
      if (!files.length) { $('#bjb_filesList').html('<div class="jbb-muted" style="padding:14px;">{{Aucun fichier imprimable}}</div>'); return; }
      var html = '<div class="text-muted" style="margin-bottom:6px;"><i class="fas fa-folder-open"></i> ' + files.length + ' {{fichier(s)}}</div>';
      html += '<table class="table table-condensed table-hover"><tbody>';
      files.forEach(function (f) {
        var ko = f.size > 0 ? ' <small class="text-muted">(' + Math.round(f.size / 1024) + ' Ko)</small>' : '';
        html += '<tr class="bjb-file-row" data-name="' + f.name + '">'
          + '<td style="word-break:break-all;">' + f.name + ko + '</td>'
          + '<td style="width:120px;text-align:right;"><button class="btn btn-xs btn-success bjb-launch" data-path="' + encodeURIComponent(f.path) + '"><i class="fas fa-play"></i> {{Lancer}}</button></td>'
          + '</tr>';
      });
      html += '</tbody></table>';
      $('#bjb_filesList').html(html);
      $('.bjb-launch').off('click').on('click', function () { bjbLaunch(id, decodeURIComponent($(this).attr('data-path'))); });
    }
  });
}

function bjbLaunch(id, path) {
  if (!confirm('{{Lancer l\'impression de}} :\n' + path + ' ?\n\n{{L\'imprimante doit être prête (plateau vide).}}')) return;
  $.ajax({
    type: 'POST', url: 'plugins/bambujab/core/ajax/bambujab.ajax.php',
    data: { action: 'startPrint', id: id, file: path, options: JSON.stringify({ use_ams: true, bed_leveling: true }) },
    dataType: 'json',
    error: function (req, st, err) { handleAjaxError(req, st, err); },
    success: function () { $('#div_alert').showAlert({ message: '{{Impression demandée}} : ' + path, level: 'success' }); $('#modal_filesBambuJab').modal('hide'); }
  });
}

/* Envoi d'un fichier vers l'imprimante */
$('#bjb_btnUpload').off('click').on('click', function () {
  var id = $('.eqLogicAttr[data-l1key=id]').value();
  var input = document.getElementById('bjb_fileUpload');
  if (!input.files.length) { $('#div_alert').showAlert({ message: '{{Choisissez un fichier .3mf/.gcode}}', level: 'warning' }); return; }
  var fd = new FormData();
  fd.append('action', 'uploadFile'); fd.append('id', id); fd.append('file', input.files[0]);
  $('#bjb_btnUpload').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> {{Envoi…}}');
  $.ajax({
    type: 'POST', url: 'plugins/bambujab/core/ajax/bambujab.ajax.php',
    data: fd, processData: false, contentType: false, dataType: 'json',
    complete: function () { $('#bjb_btnUpload').prop('disabled', false).html('<i class="fas fa-upload"></i> {{Envoyer}}'); },
    error: function (req, st, err) { handleAjaxError(req, st, err); },
    success: function (data) {
      if (data.state !== 'ok') { $('#div_alert').showAlert({ message: data.result, level: 'danger' }); return; }
      $('#div_alert').showAlert({ message: '{{Fichier envoyé}} : ' + data.result, level: 'success' });
      bjbLoadFiles(id);
    }
  });
});

/* Caméra : capture d'une image (best-effort) */
var bjbCamTimer = null;
function bjbSnap() {
  var id = $('.eqLogicAttr[data-l1key=id]').value();
  if (!id) { return; }
  var img = document.getElementById('bjb_camImg');
  $('#bjb_camMsg').html('<i class="fas fa-spinner fa-spin"></i> {{Capture…}}');
  var url = 'plugins/bambujab/core/php/snapshot.php?id=' + id + '&t=' + Date.now();
  var probe = new Image();
  probe.onload = function () { img.src = url; img.style.display = 'inline-block'; $('#bjb_camMsg').hide(); };
  probe.onerror = function () { img.style.display = 'none'; $('#bjb_camMsg').show().html('{{Caméra indisponible (modèle/firmware ou Mode LAN).}}'); };
  probe.src = url;
}
$('#bjb_btnSnap').off('click').on('click', bjbSnap);
$('#bjb_camAuto').off('change').on('change', function () {
  if (this.checked) { bjbSnap(); bjbCamTimer = setInterval(bjbSnap, 4000); }
  else { clearInterval(bjbCamTimer); bjbCamTimer = null; }
});

/* Réorganisation des commandes */
$('#table_cmd').sortable({
  axis: 'y', cursor: 'move', items: '.cmd',
  placeholder: 'ui-state-highlight', tolerance: 'intersect', forcePlaceholderSize: true
});

/* Affichage des commandes dans l'équipement */
function addCmdToTable(_cmd) {
  if (!isset(_cmd)) { var _cmd = { configuration: {} }; }
  if (!isset(_cmd.configuration)) { _cmd.configuration = {}; }
  var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
  tr += '<td class="hidden-xs"><span class="cmdAttr" data-l1key="id"></span></td>';
  tr += '<td>';
  tr += '<div class="input-group">';
  tr += '<input class="cmdAttr form-control input-sm roundedLeft" data-l1key="name" placeholder="{{Nom}}">';
  tr += '<span class="input-group-btn"><a class="cmdAction btn btn-sm btn-default" data-l1key="chooseIcon" title="{{Choisir une icône}}"><i class="fas fa-icons"></i></a></span>';
  tr += '<span class="cmdAttr input-group-addon roundedRight" data-l1key="display" data-l2key="icon" style="font-size:19px;padding:0 5px 0 0!important;"></span>';
  tr += '</div>';
  tr += '<select class="cmdAttr form-control input-sm" data-l1key="value" style="display:none;margin-top:5px;" title="{{Commande info liée}}">';
  tr += '<option value="">{{Aucune}}</option>';
  tr += '</select>';
  tr += '</td>';
  tr += '<td><span class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>';
  tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span></td>';
  tr += '<td>';
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked/>{{Afficher}}</label> ';
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isHistorized"/>{{Historiser}}</label> ';
  tr += '<div style="margin-top:7px;">';
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">';
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">';
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="unite" placeholder="Unité" title="{{Unité}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">';
  tr += '</div></td>';
  tr += '<td><span class="cmdAttr" data-l1key="htmlstate"></span></td>';
  tr += '<td>';
  if (is_numeric(_cmd.id)) {
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>';
  }
  tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove" title="{{Supprimer la commande}}"></i></td>';
  tr += '</tr>';
  $('#table_cmd tbody').append(tr);
  var tr = $('#table_cmd tbody tr').last();
  jeedom.eqLogic.buildSelectCmd({
    id: $('.eqLogicAttr[data-l1key=id]').value(),
    filter: { type: 'info' },
    error: function (error) { $('#div_alert').showAlert({ message: error.message, level: 'danger' }); },
    success: function (result) {
      tr.find('.cmdAttr[data-l1key=value]').append(result);
      tr.setValues(_cmd, '.cmdAttr');
      jeedom.cmd.changeType(tr, init(_cmd.subType));
    }
  });
}
