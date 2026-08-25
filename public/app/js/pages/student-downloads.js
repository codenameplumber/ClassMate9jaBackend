/* ==========================================================================
   student-downloads.js — the offline pack manager.
   This screen is where the product promise becomes visible: what is on the
   phone, how big it is, and what still needs network.
   ========================================================================== */

import { boot } from '../core/boot.js';
import { hydrateIcons, icon } from '../core/icons.js';
import { $, $$, esc, toast, confirmDialog } from '../core/ui.js';
import { requireAuth } from '../core/auth.js';
import { mountShell } from '../components/shell.js';
import * as store from '../core/store.js';
import { SUBJECTS, byId, toneClass } from '../data/subjects.js';

boot();
const me = requireAuth('student');
const PACKS_KEY = 'packs';
const DEVICE_MB = 2048;          // pretend free space on a modest phone

/* Past-question packs are separate from subject packs: a student may want
   every year of Maths but only recent years of Government. */
const QUESTION_PACKS = [
  { id: 'pq-2020-2025', label: 'Past questions 2020–2025', mb: 26, note: 'Most recent — start here' },
  { id: 'pq-2015-2019', label: 'Past questions 2015–2019', mb: 24, note: 'Common repeats' },
  { id: 'pq-2010-2014', label: 'Past questions 2010–2014', mb: 22, note: 'For thorough revision' },
];

function packs() {
  const saved = store.get(PACKS_KEY);
  if (saved) return saved;
  /* Seed: the subjects the student picked at onboarding are already down. */
  const seed = {};
  (me.subjects || []).slice(0, 2).forEach((id) => { seed[id] = 'ready'; });
  seed['pq-2020-2025'] = 'ready';
  store.set(PACKS_KEY, seed);
  return seed;
}

function setPack(id, state) {
  const next = { ...packs(), [id]: state };
  if (state === null) delete next[id];
  store.set(PACKS_KEY, next);
  return next;
}

function render() {
  const page = mountShell({
    user: me,
    active: 'downloads',
    title: 'Offline packs',
    subtitle: 'Download once, study forever without data',
  });
  page.innerHTML = '<div id="body"></div>';
  paint();
}

function paint() {
  const state = packs();
  const mine = (me.subjects || []).map(byId).filter(Boolean);
  const others = SUBJECTS.filter((s) => !(me.subjects || []).includes(s.id));

  const usedSubjects = mine.filter((s) => state[s.id] === 'ready').reduce((a, s) => a + s.packMb, 0);
  const usedQuestions = QUESTION_PACKS.filter((q) => state[q.id] === 'ready').reduce((a, q) => a + q.mb, 0);
  const appMb = 18;
  const used = usedSubjects + usedQuestions + appMb;
  const pctOf = (mb) => (mb / DEVICE_MB) * 100;

  const missing = mine.filter((s) => !state[s.id]);

  $('#body').innerHTML = `
    <div class="page-head">
      <div>
        <h1>Offline packs</h1>
        <p class="sub">Everything marked <b>On phone</b> works at 0% network.</p>
      </div>
      ${missing.length ? `<button class="btn btn-primary" id="allBtn">
        ${icon('download', 16)} Download my ${missing.length} missing pack${missing.length === 1 ? '' : 's'}
      </button>` : `<span class="chip chip-brand">✓ All your subjects are downloaded</span>`}
    </div>

    <!-- Storage -->
    <section class="card card-pad-lg" style="margin-bottom:var(--s-6)">
      <div class="row between" style="margin-bottom:var(--s-4)">
        <span class="card-title">Storage used on this phone</span>
        <span class="fw-bold tnum">${used} MB <span class="subtle fw-normal">of ${(DEVICE_MB / 1024).toFixed(0)} GB free</span></span>
      </div>

      <div class="storage-bar">
        <i style="width:${pctOf(appMb)}%;background:var(--ink-400)"></i>
        <i style="width:${pctOf(usedSubjects)}%;background:var(--primary)"></i>
        <i style="width:${pctOf(usedQuestions)}%;background:var(--amber-400)"></i>
      </div>

      <div class="row gap-6 wrap t-xs subtle" style="margin-top:var(--s-3)">
        <span class="row gap-2"><i class="badge-dot" style="background:var(--ink-400)"></i> App ${appMb} MB</span>
        <span class="row gap-2"><i class="badge-dot" style="background:var(--primary)"></i> Lessons ${usedSubjects} MB</span>
        <span class="row gap-2"><i class="badge-dot" style="background:var(--amber-400)"></i> Past questions ${usedQuestions} MB</span>
      </div>

      <div class="alert alert-info" style="margin-top:var(--s-5)">
        ${icon('info', 18)}
        <span>
          Download over Wi-Fi or at school if you can. Once a pack is on the phone
          it never downloads again — lessons, questions and marking all run locally.
        </span>
      </div>
    </section>

    <!-- My subjects -->
    <section style="margin-bottom:var(--s-8)">
      <div class="section-head">
        <h2>My subjects</h2>
        <span class="t-xs subtle">${mine.filter((s) => state[s.id] === 'ready').length}/${mine.length} on phone</span>
      </div>
      ${mine.map((s) => packRow(s.id, s.emoji, s.name, `${s.topics} topics · 12-week scheme`, s.packMb, state[s.id], toneClass(s.tone))).join('')}
    </section>

    <!-- Question packs -->
    <section style="margin-bottom:var(--s-8)">
      <div class="section-head">
        <h2>Past-question packs</h2>
        <span class="t-xs subtle">WAEC · JAMB · NECO</span>
      </div>
      ${QUESTION_PACKS.map((q) => packRow(q.id, '📝', q.label, q.note, q.mb, state[q.id], 'itile-amber')).join('')}
    </section>

    <!-- Other subjects -->
    ${others.length ? `
    <section>
      <div class="section-head">
        <h2>Other subjects</h2>
        <span class="t-xs subtle">Add a subject first to download its pack</span>
      </div>
      ${others.slice(0, 4).map((s) => `
        <div class="dl-row" style="opacity:.65">
          <span class="itile ${toneClass(s.tone)}">${s.emoji}</span>
          <div class="grow" style="min-width:0">
            <div class="fw-semibold t-sm">${esc(s.name)}</div>
            <div class="hint">${s.packMb} MB · not in your subject list</div>
          </div>
          <a class="btn btn-secondary btn-sm" href="subjects.html">Add subject</a>
        </div>`).join('')}
    </section>` : ''}`;

  wire();
  hydrateIcons();
}

function packRow(id, emoji, name, note, mb, state, tone = '') {
  const ready = state === 'ready';
  const busy = state === 'downloading';
  return `
  <div class="dl-row" data-row="${id}">
    <span class="itile ${tone}">${emoji}</span>

    <div class="grow" style="min-width:0">
      <div class="row gap-2 wrap">
        <span class="fw-semibold t-sm">${esc(name)}</span>
        ${ready ? '<span class="chip chip-brand">On phone</span>' : ''}
      </div>
      <div class="hint">${esc(note)} · ${mb} MB</div>
      ${busy ? `<div class="bar bar-sm" style="margin-top:8px"><i data-bar="${id}" style="width:0%"></i></div>` : ''}
    </div>

    ${busy
      ? `<span class="t-xs fw-bold tnum" data-pct="${id}">0%</span>`
      : ready
        ? `<button class="btn btn-ghost btn-icon" data-remove="${id}" aria-label="Remove ${esc(name)}">
             ${icon('close', 18)}
           </button>`
        : `<button class="btn btn-secondary btn-sm" data-get="${id}">
             ${icon('download', 16)} Get
           </button>`}
  </div>`;
}

function wire() {
  $$('[data-get]').forEach((b) => b.addEventListener('click', () => download(b.dataset.get)));

  $$('[data-remove]').forEach((b) => b.addEventListener('click', async () => {
    const id = b.dataset.remove;
    const yes = await confirmDialog({
      title: 'Remove this pack?',
      message: 'The lessons and questions will leave this phone. You will need network to download them again.',
      confirmText: 'Remove',
      danger: true,
    });
    if (!yes) return;
    setPack(id, null);
    toast('Pack removed');
    paint();
  }));

  $('#allBtn')?.addEventListener('click', () => {
    const missing = (me.subjects || []).filter((id) => !packs()[id]);
    missing.forEach((id, i) => setTimeout(() => download(id), i * 400));
  });
}

/** Simulated download. The progress bar is fake; the state change is real. */
function download(id) {
  setPack(id, 'downloading');
  paint();

  let pct = 0;
  const bar = $(`[data-bar="${id}"]`);
  const label = $(`[data-pct="${id}"]`);

  const tick = setInterval(() => {
    /* Uneven steps read like a real connection rather than a loading toy. */
    pct = Math.min(100, pct + 6 + Math.round(Math.random() * 14));
    if (bar) bar.style.width = `${pct}%`;
    if (label) label.textContent = `${pct}%`;

    if (pct >= 100) {
      clearInterval(tick);
      setPack(id, 'ready');
      const s = byId(id);
      toast(`${s ? s.name : 'Pack'} ready — works offline now`, 'success');
      paint();
    }
  }, 260);
}

/* Start only once every module-level constant above is initialised.
   Calling render() higher up would hit the temporal dead zone on any
   const declared below it — a hoisted function can be called early,
   the constants it closes over cannot. */
if (me) render();
