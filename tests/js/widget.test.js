// Tests de desktop/js/bambujab.widget.js — boucle de rafraîchissement du widget.
//
// Le moteur de rafraîchissement décide à lui seul du nombre de requêtes qu'un
// dashboard ouvert en permanence envoie au serveur. Une régression ici ne casse
// rien de visible : elle multiplie discrètement la charge (timer qui tourne
// onglet masqué, cadence rapide à l'arrêt, HTML complet redemandé à chaque tour).
// D'où ces tests, qui simulent DOM, jQuery et horloge pour compter les requêtes.
//
// Exécution : node tests/js/widget.test.js   (aucune dépendance, aucun réseau)
const fs = require('fs');
const path = require('path');

const CIBLE = path.join(__dirname, '..', '..', 'desktop', 'js', 'bambujab.widget.js');


let now = 0, seq = 0, timers = new Map();
const calls = [];            // requetes AJAX observees
let responses = {};          // reponses programmees par action

function advance(ms) {
  const target = now + ms;
  while (true) {
    let next = null;
    for (const [id, t] of timers) if (t.at <= target && (!next || t.at < next.at)) next = { id, ...t };
    if (!next) break;
    timers.delete(next.id);
    now = next.at;
    next.fn();
  }
  now = target;
}

// --- DOM minimal ------------------------------------------------------------
function makeNode(id) {
  return {
    id, attrs: {}, innerHTML: '',
    getAttribute(k) { return k in this.attrs ? this.attrs[k] : null; },
    setAttribute(k, v) { this.attrs[k] = v; },
    querySelector() { return null; },
    parentNode: null,
  };
}
const nodes = {};
const listeners = {};
const document = {
  hidden: false,
  getElementById: (id) => nodes[id] || null,
  querySelectorAll: (sel) => { const n = nodes[sel.replace('#', '')]; return n ? [n] : []; },
  createElement: (tag) => {
    const n = makeNode('new-' + tag);
    n.tagName = tag;
    Object.defineProperty(n, 'innerHTML', {
      get() { return this._html || ''; },
      set(v) { this._html = v; this._parsed = parseWrap(v); },
    });
    n.querySelector = function (sel) { return this._parsed && '#' + this._parsed.id === sel ? this._parsed : null; };
    return n;
  },
  head: { appendChild() {} },
  addEventListener: (ev, fn) => { (listeners[ev] = listeners[ev] || []).push(fn); },
};
function fire(ev) { (listeners[ev] || []).forEach((f) => f()); }

// Extrait id + data-sig d'un HTML de widget rendu par toHtml().
function parseWrap(html) {
  const m = /id="(jbbW\d+)"[^>]*data-sig="([^"]*)"/.exec(html);
  if (!m) return null;
  const n = makeNode(m[1]);
  n.attrs['data-sig'] = m[2];
  n.innerHTML = html;
  return n;
}

// --- jQuery minimal ---------------------------------------------------------
const $ = { ajax(o) { calls.push(o.data); const r = responses[o.data.action]; if (r === undefined) return; if (r === 'error') { o.error && o.error(); } else { o.success(r); } } };

global.window = { };
global.document = document;
global.$ = $;
global.setTimeout = (fn, ms) => { const id = ++seq; timers.set(id, { at: now + ms, fn }); return id; };
global.clearTimeout = (id) => timers.delete(id);

// --- Chargement du module ---------------------------------------------------
nodes['jbbW7'] = makeNode('jbbW7');
nodes['jbbW7'].attrs['data-sig'] = 'SIG-A';
window.jbbInit = [[7, 'SIG-A', 0]];        // etat initial : imprimante a l'arret
eval(fs.readFileSync(CIBLE, 'utf8'));

// --- Scenarios --------------------------------------------------------------
let ok = 0, ko = 0;
function check(label, cond) { if (cond) { ok++; console.log('  OK   ' + label); } else { ko++; console.log('  ECHEC ' + label); } }

console.log('1. Cadence lente a l\'arret');
responses = { widgetSig: { state: 'ok', result: { sig: 'SIG-A', active: 0 } } };
calls.length = 0;
advance(7000);
check('rien a 7 s quand l\'imprimante est a l\'arret', calls.length === 0);
advance(53000);
check('un sondage de signature a 60 s', calls.length === 1 && calls[0].action === 'widgetSig');
check('aucun HTML complet demande (signature inchangee)', !calls.some((c) => c.action === 'widget'));

console.log('2. Passage en impression -> cadence rapide');
calls.length = 0;
responses = { widgetSig: { state: 'ok', result: { sig: 'SIG-A', active: 1 } } };
advance(60000);                              // sondage qui apprend active=1
calls.length = 0;
advance(7000);
check('sondage a 7 s pendant l\'impression', calls.length === 1);

console.log('3. Signature changee -> HTML joint a la meme reponse');
calls.length = 0;
responses = {
  widgetSig: {
    state: 'ok',
    result: { sig: 'SIG-B', active: 1, html: '<div class="jbb-wrap" id="jbbW7" data-sig="SIG-B"><div>nouveau</div></div>' },
  },
};
advance(7000);
check('une seule requete, sans aller-retour supplementaire', calls.length === 1 && calls[0].action === 'widgetSig');
check('la signature affichee est transmise au serveur', calls[0].sig === 'SIG-A');
check('DOM mis a jour', nodes['jbbW7'].innerHTML.indexOf('nouveau') > -1);
check('nouvelle signature adoptee', nodes['jbbW7'].getAttribute('data-sig') === 'SIG-B');
calls.length = 0;
responses = { widgetSig: { state: 'ok', result: { sig: 'SIG-B', active: 1 } } };
advance(7000);
check('tour suivant : signature renvoyee, aucun HTML', calls.length === 1 && calls[0].sig === 'SIG-B');
check('le DOM reste intact', nodes['jbbW7'].innerHTML.indexOf('nouveau') > -1);

console.log('4. Onglet en arriere-plan');
calls.length = 0;
document.hidden = true;
fire('visibilitychange');
advance(300000);                             // 5 minutes cachees
check('aucune requete onglet masque (5 min)', calls.length === 0);
document.hidden = false;
fire('visibilitychange');
check('rattrapage immediat au retour', calls.length === 1 && calls[0].action === 'widgetSig');

console.log('5. Flux camera affiche');
calls.length = 0;
nodes['jbbCam7'] = makeNode('jbbCam7');
nodes['jbbCam7'].attrs['src'] = 'plugins/bambujab/core/php/stream.php?id=7';
advance(7000);
check('pas de re-rendu pendant le flux camera', calls.length === 0);
delete nodes['jbbCam7'];

console.log('6. Erreur reseau');
calls.length = 0;
responses = { widgetSig: 'error' };
advance(7000);
check('une tentative', calls.length === 1);
advance(7000);
check('la boucle survit a l\'erreur', calls.length === 2);

console.log('7. Widget retire du dashboard');
calls.length = 0;
responses = { widgetSig: { state: 'ok', result: { sig: 'SIG-B', active: 1 } } };
delete nodes['jbbW7'];
advance(60000);
check('la boucle s\'arrete d\'elle-meme', calls.length === 0);

console.log('\n' + ok + ' verifications OK, ' + ko + ' echecs');
process.exit(ko === 0 ? 0 : 1);
