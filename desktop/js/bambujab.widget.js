/* BambuJab — rafraîchissement des widgets « carte imprimante » du dashboard.
 * Plugin BambuJab - Aldarande — Licence AGPL v3
 *
 * Chargé une seule fois par page (balise <script id="jbbJs">), quel que soit le
 * nombre d'imprimantes affichées. Chaque widget s'enregistre au premier paint
 * via jbbInit.push([id, sig, active]) — voir bambujab.class.php::toHtml().
 *
 * Stratégie de rafraîchissement (le démon pousse déjà les valeurs aux cmds ;
 * ici on ne s'occupe que de redessiner la carte) :
 *   1. on envoie à `widgetSig` la signature actuellement affichée. Si elle n'a pas
 *      bougé, la réponse tient en quelques dizaines d'octets ; sinon le HTML est
 *      joint à cette même réponse. Une requête par tour dans les deux cas —
 *      auparavant on transportait tout le HTML à chaque tour pour découvrir
 *      qu'il n'avait pas changé ;
 *   2. onglet en arrière-plan (document.hidden) -> aucun sondage. Un dashboard
 *      resté ouvert dans un onglet inactif ne coûte plus rien ;
 *   3. cadence adaptée à l'état : rapide pendant une impression, lente à l'arrêt ;
 *   4. flux caméra affiché -> on ne touche pas au DOM (le <img> serait recréé
 *      et le flux coupé).
 */
;(function () {
  'use strict';

  var AJAX = 'plugins/bambujab/core/ajax/bambujab.ajax.php';
  var FAST_MS = 7000;   // impression / pause / préparation en cours
  var SLOW_MS = 60000;  // imprimante inactive, terminée, en veille ou éteinte

  var widgets = {};

  function node(id) {
    return document.getElementById('jbbW' + id);
  }

  /** Flux caméra en cours d'affichage : on gèle le rafraîchissement du widget. */
  function streaming(id) {
    var cam = document.getElementById('jbbCam' + id);
    return !!(cam && ('' + cam.getAttribute('src')).indexOf('stream.php') > -1);
  }

  function Widget(id, sig, active) {
    this.id = id;
    this.sig = sig || '';
    this.active = !!active;
    this.timer = null;
    this.busy = false;
  }

  Widget.prototype.stop = function () {
    if (this.timer) {
      clearTimeout(this.timer);
      this.timer = null;
    }
  };

  /** Programme le prochain sondage, sauf onglet caché ou widget retiré du DOM. */
  Widget.prototype.schedule = function () {
    this.stop();
    if (!node(this.id)) {           // carte supprimée (changement de page) : on oublie
      delete widgets[this.id];
      return;
    }
    if (document.hidden) {
      return;                       // reprise assurée par l'écouteur visibilitychange
    }
    var self = this;
    this.timer = setTimeout(function () { self.tick(); }, this.active ? FAST_MS : SLOW_MS);
  };

  Widget.prototype.done = function () {
    this.busy = false;
    this.schedule();
  };

  /**
   * Un tour de boucle : on soumet la signature affichée ; le serveur ne renvoie
   * le HTML que si elle a changé.
   */
  Widget.prototype.tick = function () {
    this.timer = null;
    if (!node(this.id)) {
      delete widgets[this.id];
      return;
    }
    if (this.busy || streaming(this.id)) {
      this.schedule();
      return;
    }
    this.busy = true;
    var self = this;
    try {
      $.ajax({
        type: 'POST', url: AJAX, dataType: 'json',
        data: { action: 'widgetSig', id: this.id, sig: this.sig },
        success: function (d) {
          var r = (d && d.state === 'ok') ? d.result : null;
          if (r) {
            self.active = (r.active == 1);
            if (r.html) { self.apply(r.html); }
            else if (r.sig) { self.sig = r.sig; }
          }
          self.done();
        },
        error: function () { self.done(); }
      });
    } catch (e) {
      this.done();
    }
  };

  /** Rendu complet forcé, sans passer par la signature (bouton « Rafraîchir »). */
  Widget.prototype.render = function () {
    var self = this;
    try {
      $.ajax({
        type: 'POST', url: AJAX, dataType: 'json',
        data: { action: 'widget', id: this.id },
        success: function (d) {
          if (d && d.state === 'ok' && d.result) { self.apply(d.result); }
          self.done();
        },
        error: function () { self.done(); }
      });
    } catch (e) {
      this.done();
    }
  };

  Widget.prototype.apply = function (html) {
    var id = this.id;
    // gridStack peut avoir laissé des copies de la carte : on ne garde que la première.
    var dup = document.querySelectorAll('#jbbW' + id);
    for (var j = 1; j < dup.length; j++) {
      if (dup[j].parentNode) { dup[j].parentNode.removeChild(dup[j]); }
    }
    var w = node(id);
    if (!w) { return; }
    var tmp = document.createElement('div');
    tmp.innerHTML = html;
    var fresh = tmp.querySelector('#jbbW' + id);
    w.innerHTML = fresh ? fresh.innerHTML : html;
    if (fresh && fresh.getAttribute('data-sig')) {
      this.sig = fresh.getAttribute('data-sig');
    }
    w.setAttribute('data-sig', this.sig);
  };

  /**
   * Enregistre (ou réenregistre) un widget. Appelé à chaque paint de la carte,
   * y compris après un remplacement de HTML : idempotent, un seul timer par id.
   */
  function init(id, sig, active) {
    var w = widgets[id];
    if (w) {
      if (sig) { w.sig = sig; }
      w.active = !!active;
      if (!w.busy && !w.timer) { w.schedule(); }
      return;
    }
    w = widgets[id] = new Widget(id, sig, active);
    w.schedule();
  }

  /** Rafraîchissement immédiat (bouton « Rafraîchir » de la carte). */
  function refresh(id) {
    var w = widgets[id];
    if (!w) { init(id, '', true); w = widgets[id]; }
    if (w.busy) { return; }
    w.stop();
    w.busy = true;
    w.render();
  }

  // Onglet masqué -> on coupe tous les timers ; au retour, rattrapage immédiat
  // (le sondage de signature est peu coûteux et l'affichage doit être à jour).
  document.addEventListener('visibilitychange', function () {
    for (var id in widgets) {
      if (!widgets.hasOwnProperty(id)) { continue; }
      var w = widgets[id];
      if (document.hidden) { w.stop(); }
      else if (!w.busy && !w.timer) { w.tick(); }
    }
  });

  // Les cartes déjà peintes ont empilé leurs enregistrements dans un tableau ;
  // on le vide, puis on remplace le tableau par un objet dont le push() enregistre
  // directement — le bootstrap inline reste identique dans les deux cas.
  var pending = window.jbbInit || [];
  window.jbbInit = { push: function (a) { init(a[0], a[1], a[2]); } };
  for (var i = 0; i < pending.length; i++) {
    init(pending[i][0], pending[i][1], pending[i][2]);
  }

  window.jbbWidget = { init: init, refresh: refresh };
})();
