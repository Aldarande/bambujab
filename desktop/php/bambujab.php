<?php
if (!isConnect('admin')) {
  throw new Exception('{{401 - Accès non autorisé}}');
}
$plugin = plugin::byId('bambujab');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());
?>

<div class="row row-overflow">
  <div class="col-xs-12 eqLogicThumbnailDisplay">
    <legend><i class="fas fa-cog"></i> {{Gestion}}</legend>
    <div class="eqLogicThumbnailContainer">
      <div class="cursor eqLogicAction logoPrimary" data-action="add">
        <i class="fas fa-plus-circle"></i><br><span>{{Ajouter}}</span>
      </div>
      <div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
        <i class="fas fa-wrench"></i><br><span>{{Configuration}}</span>
      </div>
      <div class="cursor logoSecondary" id="bt_discoverPrinters">
        <i class="fas fa-search"></i><br><span>{{Rechercher sur le réseau}}</span>
      </div>
      <div class="cursor logoSecondary" id="bt_donBambuJab" title="{{Faire un don}}">
        <i class="fas fa-mug-hot"></i><br><span>{{Don}}</span>
      </div>
    </div>

    <!-- Modal Don -->
    <div class="modal fade" id="modal_donBambuJab" tabindex="-1" role="dialog">
      <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:10px;overflow:hidden;">
          <div class="modal-header" style="background:linear-gradient(135deg,#0d7d4d,#16a34a);border:none;padding:18px 20px;">
            <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1;font-size:1.4em;"><span>&times;</span></button>
            <h4 class="modal-title" style="color:#fff;font-size:1.1em;">
              <i class="fas fa-heart" style="color:#ff6b6b;margin-right:7px;"></i> {{Soutenir BambuJab}}
            </h4>
          </div>
          <div class="modal-body" style="padding:22px 24px;">
            <p style="font-size:1em;color:#333;margin-bottom:6px;">
              {{BambuJab est un plugin}} <strong>{{gratuit et open-source}}</strong> {{(AGPL v3), développé et maintenu bénévolement.}}
            </p>
            <p style="font-size:0.9em;color:#666;margin-bottom:18px;">
              {{Un don, même modeste, aide à financer le temps de développement, les tests et les mises à jour. Merci !}}
            </p>
            <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin-bottom:18px;">
              <a href="https://ko-fi.com/aldarande" target="_blank" rel="noopener" class="btn btn-lg" style="background:#FF5E5B;color:#fff;border:none;min-width:140px;"><i class="fas fa-mug-hot"></i> Ko-fi</a>
              <a href="https://github.com/sponsors/Aldarande" target="_blank" rel="noopener" class="btn btn-lg" style="background:#24292e;color:#fff;border:none;min-width:140px;"><i class="fab fa-github"></i> Sponsors</a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Fichiers imprimante -->
    <div class="modal fade" id="modal_filesBambuJab" tabindex="-1" role="dialog">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:10px;overflow:hidden;">
          <div class="modal-header" style="background:linear-gradient(135deg,#0d7d4d,#16a34a);border:none;">
            <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1;"><span>&times;</span></button>
            <h4 class="modal-title" style="color:#fff;"><i class="fas fa-folder-open"></i> {{Fichiers de l'imprimante}}</h4>
          </div>
          <div class="modal-body" style="padding:18px 22px;">
            <div style="display:flex;gap:10px;margin-bottom:12px;align-items:center;">
              <input type="file" id="bjb_fileUpload" accept=".3mf,.gcode" style="flex:1;">
              <button class="btn btn-success" id="bjb_btnUpload"><i class="fas fa-upload"></i> {{Envoyer}}</button>
              <button class="btn btn-default" id="bjb_btnRefreshFiles"><i class="fas fa-sync"></i></button>
            </div>
            <input class="form-control" id="bjb_fileSearch" placeholder="{{Filtrer…}}" style="margin-bottom:10px;">
            <div id="bjb_filesList" style="max-height:50vh;overflow:auto;"></div>
          </div>
        </div>
      </div>
    </div>
    <legend><i class="fas fa-print"></i> {{Mes imprimantes BambuLab}}</legend>
    <?php
    if (count($eqLogics) === 0) {
      echo '<br><div class="text-center" style="font-size:1.2em;font-weight:bold;">{{Aucune imprimante. Cliquez sur "Rechercher sur le réseau" ou "Ajouter".}}</div>';
    } else {
      echo '<div class="input-group" style="margin:5px;">';
      echo '<input class="form-control roundedLeft" placeholder="{{Rechercher}}" id="in_searchEqlogic">';
      echo '<div class="input-group-btn">';
      echo '<a id="bt_resetSearch" class="btn" style="width:30px"><i class="fas fa-times"></i></a>';
      echo '<a class="btn roundedRight hidden" id="bt_pluginDisplayAsTable" data-coreSupport="1" data-state="0"><i class="fas fa-grip-lines"></i></a>';
      echo '</div></div>';
      echo '<div class="eqLogicThumbnailContainer">';
      foreach ($eqLogics as $eqLogic) {
        $opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
        echo '<div class="eqLogicDisplayCard cursor ' . $opacity . '" data-eqLogic_id="' . $eqLogic->getId() . '">';
        echo '<img src="' . $eqLogic->getImage() . '"/>';
        echo '<br><span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
        echo '</div>';
      }
      echo '</div>';
    }
    ?>
  </div>

  <div class="col-xs-12 eqLogic" style="display: none;">
    <div class="input-group pull-right" style="display:inline-flex;">
      <span class="input-group-btn">
        <a class="btn btn-sm btn-default eqLogicAction roundedLeft" data-action="configure"><i class="fas fa-cogs"></i><span class="hidden-xs"> {{Configuration avancée}}</span>
        </a><a class="btn btn-sm btn-info" id="bjb_btnFiles"><i class="fas fa-folder-open"></i><span class="hidden-xs"> {{Fichiers}}</span>
        </a><a class="btn btn-sm btn-default eqLogicAction" data-action="copy"><i class="fas fa-copy"></i><span class="hidden-xs"> {{Dupliquer}}</span>
        </a><a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}
        </a><a class="btn btn-sm btn-danger eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}
        </a>
      </span>
    </div>
    <ul class="nav nav-tabs" role="tablist">
      <li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
      <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Équipement}}</a></li>
      <li role="presentation"><a href="#commandtab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-list"></i> {{Commandes}}</a></li>
    </ul>
    <div class="tab-content">
      <div role="tabpanel" class="tab-pane active" id="eqlogictab">
        <form class="form-horizontal">
          <fieldset>
            <div class="col-lg-6">
              <legend><i class="fas fa-wrench"></i> {{Paramètres généraux}}</legend>
              <div class="form-group">
                <label class="col-sm-4 control-label">{{Nom de l'imprimante}}</label>
                <div class="col-sm-6">
                  <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display:none;">
                  <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'imprimante}}">
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label">{{Objet parent}}</label>
                <div class="col-sm-6">
                  <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
                    <option value="">{{Aucun}}</option>
                    <?php
                    foreach ((jeeObject::buildTree(null, false)) as $object) {
                      echo '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
                    }
                    ?>
                  </select>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label">{{Options}}</label>
                <div class="col-sm-6">
                  <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked>{{Activer}}</label>
                  <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked>{{Visible}}</label>
                </div>
              </div>

              <legend><i class="fas fa-plug"></i> {{Connexion}}</legend>
              <div class="form-group">
                <label class="col-sm-4 control-label">{{Mode de connexion}}
                  <sup><i class="fas fa-question-circle tooltips" title="{{LAN : direct sur le réseau local (vie privée, pilotage complet). Cloud : via le compte BambuLab (accès à distance).}}"></i></sup>
                </label>
                <div class="col-sm-6">
                  <select class="eqLogicAttr form-control" id="bjb_connMode" data-l1key="configuration" data-l2key="conn_mode">
                    <option value="lan">{{LAN (réseau local — recommandé)}}</option>
                    <option value="cloud">{{Cloud (compte BambuLab)}}</option>
                  </select>
                </div>
              </div>

              <!-- ───────── Champs LAN ───────── -->
              <div id="bjb_lanFields">
                <div class="form-group">
                  <label class="col-sm-4 control-label">{{Adresse IP}}
                    <sup><i class="fas fa-question-circle tooltips" title="{{Adresse IP locale de l'imprimante (réseau)}}"></i></sup>
                  </label>
                  <div class="col-sm-6">
                    <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="ip" placeholder="192.168.1.50">
                  </div>
                </div>
                <div class="form-group">
                  <label class="col-sm-4 control-label">{{Numéro de série}} <small class="text-muted">({{optionnel}})</small>
                    <sup><i class="fas fa-question-circle tooltips" title="{{Laissez vide pour une détection automatique. Sinon : appli Bambu Handy › Appareil › Device info, ou autocollant de l'imprimante.}}"></i></sup>
                  </label>
                  <div class="col-sm-6">
                    <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="serial" placeholder="{{vide = détection auto}}">
                  </div>
                </div>
                <div class="form-group">
                  <label class="col-sm-4 control-label">{{Code d'accès}}
                    <sup><i class="fas fa-question-circle tooltips" title="{{Access code (Réglages › Réseau › Mode LAN). 8 chiffres.}}"></i></sup>
                  </label>
                  <div class="col-sm-6">
                    <input type="text" class="eqLogicAttr form-control inputPassword" data-l1key="configuration" data-l2key="access_code" placeholder="********">
                  </div>
                </div>
                <div class="alert alert-info" style="margin-top:8px;">
                  <i class="fas fa-info-circle"></i> {{Le « Mode LAN » doit être activé sur l'imprimante (Réglages › Réseau).}}
                </div>
              </div>

              <!-- ───────── Champs Cloud ───────── -->
              <div id="bjb_cloudFields" style="display:none;">
                <div class="form-group">
                  <label class="col-sm-4 control-label">{{Région}}</label>
                  <div class="col-sm-6">
                    <select class="eqLogicAttr form-control" id="bjb_cloudRegion" data-l1key="configuration" data-l2key="cloud_region">
                      <option value="global">{{International (.com)}}</option>
                      <option value="china">{{Chine (.cn)}}</option>
                    </select>
                  </div>
                </div>
                <div class="form-group">
                  <label class="col-sm-4 control-label">{{Email BambuLab}}</label>
                  <div class="col-sm-6">
                    <input type="email" class="eqLogicAttr form-control" id="bjb_cloudEmail" data-l1key="configuration" data-l2key="cloud_email" placeholder="email@exemple.com" autocomplete="off">
                  </div>
                </div>
                <div class="form-group">
                  <label class="col-sm-4 control-label">{{Mot de passe}}</label>
                  <div class="col-sm-6">
                    <input type="password" class="form-control" id="bjb_cloudPassword" placeholder="********" autocomplete="off">
                    <small class="text-muted">{{Le mot de passe n'est pas stocké : seul un jeton d'accès l'est.}}</small>
                  </div>
                </div>
                <div class="form-group">
                  <div class="col-sm-offset-4 col-sm-6">
                    <button type="button" class="btn btn-primary" id="bjb_cloudLogin"><i class="fas fa-sign-in-alt"></i> {{Se connecter}}</button>
                    <a class="cursor" id="bjb_cloudSsoToggle" style="margin-left:12px;font-size:.85em;"><i class="fab fa-google"></i> {{Compte Google/Apple/Facebook ?}}</a>
                  </div>
                </div>
                <div id="bjb_cloudSso" style="display:none;">
                  <div class="alert alert-info" style="margin:0 0 10px;">
                    <i class="fas fa-info-circle"></i> {{Les comptes créés via Google, Apple ou Facebook n'ont pas de mot de passe Bambu. Deux options :}}
                    <ul style="margin:6px 0 0;padding-left:18px;">
                      <li>{{Définissez un mot de passe sur votre compte BambuLab (account.bambulab.com › Sécurité), puis utilisez Email + Mot de passe ci-dessus ;}}</li>
                      <li>{{ou collez ci-dessous un jeton d'accès (access token) que vous avez récupéré.}}</li>
                    </ul>
                  </div>
                  <div class="form-group">
                    <label class="col-sm-4 control-label">{{Jeton d'accès}}</label>
                    <div class="col-sm-6" style="display:flex;gap:8px;">
                      <input type="password" class="form-control" id="bjb_cloudTokenInput" placeholder="eyJ..." autocomplete="off">
                      <button type="button" class="btn btn-info" id="bjb_cloudUseToken"><i class="fas fa-key"></i> {{Utiliser}}</button>
                    </div>
                  </div>
                </div>
                <div class="form-group" id="bjb_cloudCodeRow" style="display:none;">
                  <label class="col-sm-4 control-label">{{Code reçu par email}}</label>
                  <div class="col-sm-6" style="display:flex;gap:8px;">
                    <input type="text" class="form-control" id="bjb_cloudCode" placeholder="000000" maxlength="8">
                    <button type="button" class="btn btn-success" id="bjb_cloudVerify"><i class="fas fa-check"></i> {{Valider}}</button>
                  </div>
                </div>
                <div class="form-group" id="bjb_cloudDeviceRow" style="display:none;">
                  <label class="col-sm-4 control-label">{{Imprimante}}</label>
                  <div class="col-sm-6">
                    <select class="form-control" id="bjb_cloudDevice"></select>
                  </div>
                </div>
                <div class="form-group">
                  <label class="col-sm-4 control-label">{{IP locale (caméra)}} <small class="text-muted">({{optionnel}})</small>
                    <sup><i class="fas fa-question-circle tooltips" title="{{IP locale de l'imprimante sur votre réseau. Permet d'afficher la caméra dans le widget même en mode Cloud (le flux passe en local). Laissez vide si l'imprimante n'est pas sur le même réseau.}}"></i></sup>
                  </label>
                  <div class="col-sm-6">
                    <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="camera_ip" placeholder="192.168.1.50">
                  </div>
                </div>
                <!-- champs cachés persistés -->
                <input type="hidden" class="eqLogicAttr" id="bjb_cloudToken" data-l1key="configuration" data-l2key="cloud_token">
                <input type="hidden" class="eqLogicAttr" id="bjb_cloudUsername" data-l1key="configuration" data-l2key="cloud_username">
                <input type="hidden" class="eqLogicAttr" id="bjb_cloudMqttHost" data-l1key="configuration" data-l2key="cloud_mqtt_host">
                <div class="alert alert-warning" style="margin-top:8px;">
                  <i class="fas fa-info-circle"></i> {{En mode Cloud, le pilotage peut être restreint par BambuLab (Bambu Connect). La caméra reste possible si vous renseignez l'IP locale ci-dessus (le flux passe en local).}}
                </div>
              </div>
            </div>

            <div class="col-lg-6">
              <div id="bjb_camSection">
              <legend><i class="fas fa-video"></i> {{Caméra}} <small class="text-muted">({{LAN}})</small>
                <button type="button" class="btn btn-xs btn-default pull-right" id="bjb_btnSnap"><i class="fas fa-sync"></i> {{Rafraîchir}}</button>
                <label class="pull-right" style="font-weight:normal;margin-right:10px;font-size:.85em;"><input type="checkbox" id="bjb_camAuto"> {{Auto}}</label>
              </legend>
              <div style="text-align:center;background:#0f172a;border-radius:8px;padding:6px;min-height:120px;">
                <img id="bjb_camImg" style="max-width:100%;border-radius:6px;display:none;">
                <div id="bjb_camMsg" class="jbb-muted" style="color:#94a3b8;padding:30px 0;">{{Cliquez sur Rafraîchir pour capturer une image (best-effort, P1/A1).}}</div>
              </div>
              </div>

              <legend style="margin-top:14px;"><i class="fas fa-info"></i> {{Informations}}</legend>
              <div class="form-group">
                <label class="col-sm-4 control-label">{{Description}}</label>
                <div class="col-sm-6">
                  <textarea class="form-control eqLogicAttr autogrow" data-l1key="comment"></textarea>
                </div>
              </div>
            </div>
          </fieldset>
        </form>
      </div>

      <div role="tabpanel" class="tab-pane" id="commandtab">
        <a class="btn btn-default btn-sm pull-right cmdAction" data-action="add" style="margin-top:5px;"><i class="fas fa-plus-circle"></i> {{Ajouter une commande}}</a>
        <br><br>
        <div class="table-responsive">
          <table id="table_cmd" class="table table-bordered table-condensed">
            <thead>
              <tr>
                <th class="hidden-xs" style="min-width:50px;width:70px;">ID</th>
                <th style="min-width:200px;width:350px;">{{Nom}}</th>
                <th>{{Type}}</th>
                <th style="min-width:260px;">{{Options}}</th>
                <th>{{État}}</th>
                <th style="min-width:80px;width:200px;">{{Actions}}</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include_file('desktop', 'bambujab', 'js', 'bambujab'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>
