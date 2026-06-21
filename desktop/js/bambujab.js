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
      var html = '<table class="table table-condensed table-hover"><tbody>';
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
