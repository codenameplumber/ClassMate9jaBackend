/* ==========================================================================
   student-downloads.js — what is actually on this phone.

   This screen is where the product promise becomes checkable, so everything on
   it is measured rather than estimated. It used to be theatre: invented pack
   sizes, a pretend 2 GB device, a progress bar driven by Math.random(), and a
   seed that marked two subjects "downloaded" the first time the page opened.
   A student who trusted it and then lost signal found an empty app.

   Now: sizes come from the bytes in local storage, status comes from whether
   the lessons are genuinely there, free space comes from the browser (and is
   simply not shown when the browser will not say), and the download button
   performs a real sync.
   ========================================================================== */

import { boot } from '../core/boot.js';
import { hydrateIcons, icon } from '../core/icons.js';
import { $, $$, esc, toast, confirmDialog } from '../core/ui.js';
import { url } from '../core/paths.js';
import { requireAuth } from '../core/auth.js';
import { mountShell } from '../components/shell.js';
import { byId, toneClass } from '../data/subjects.js';
import {
  packStatus, packSize, packTopics, packQuestions,
  totalSize, deviceStorage, shellCached, downloadAll, removePack, human,
} from '../core/offline.js';

boot();
const me = requireAuth('student');

let busy = false;
let stage = '';

function render() {
  const page = mountShell({
    user: me,
    active: 'downloads',
    title: 'Offline packs',
    subtitle: 'Download once, study without data',
  });

  page.innerHTML = '<div id="body"></div>';
  paint();
}

async function paint() {
  const mine = (me.subjects || []).map(byId).filter(Boolean);
  const ready = mine.filter((s) => packStatus(s.id) === 'ready');
  const missing = mine.filter((s) => packStatus(s.id) !== 'ready');

  const [space, shell] = await Promise.all([deviceStorage(), shellCached()]);
  const held = totalSize();

  $('#body').innerHTML = `
    <!-- What is on the phone -->
    <section class="card" style="margin-bottom:var(--s-8)">
      <div class="card-body">
        <div class="row between wrap gap-4">
          <div>
            <div class="t-xs subtle">On this phone</div>
            <div class="fw-bold tnum" style="font-size:var(--text-2xl)">${human(held)}</div>
            <div class="hint">
              ${ready.length} of ${mine.length} ${mine.length === 1 ? 'subject' : 'subjects'} ready to use offline
            </div>
          </div>

          <div style="text-align:right">
            <div class="t-xs subtle">The app itself</div>
            <div>
              ${shell
                ? '<span class="chip chip-brand">Saved — opens with no network</span>'
                : '<span class="chip chip-amber">Not saved yet</span>'}
            </div>
          </div>
        </div>

        ${space ? `
          <div class="bar" style="margin-top:var(--s-5)">
            <i style="width:${Math.min(100, (space.usage / space.quota) * 100).toFixed(1)}%"></i>
          </div>
          <div class="hint" style="margin-top:var(--s-2)">
            ${human(space.usage)} used of ${human(space.quota)} this browser will allow
          </div>` : `
          <div class="hint" style="margin-top:var(--s-4)">
            This browser will not report free space, so only what is stored is shown.
          </div>`}

        <div class="row gap-3 wrap" style="margin-top:var(--s-5)">
          <button class="btn btn-primary" id="allBtn" ${busy ? 'disabled' : ''}>
            ${icon('download', 16)}
            ${busy ? esc(stage || 'Working…') : missing.length
              ? `Download ${missing.length} missing ${missing.length === 1 ? 'subject' : 'subjects'}`
              : 'Check for updates'}
          </button>
          ${!navigator.onLine ? '<span class="chip chip-amber">You are offline</span>' : ''}
        </div>

        <div class="alert alert-info" style="margin-top:var(--s-5)">
          ${icon('info', 18)}
          <span>
            Download over Wi-Fi or at school if you can. Once it is on the phone
            nothing downloads again — lessons, questions and marking all run locally.
          </span>
        </div>
      </div>
    </section>

    <!-- My subjects -->
    <section style="margin-bottom:var(--s-8)">
      <div class="section-head">
        <h2>My subjects</h2>
        <span class="t-xs subtle">${ready.length}/${mine.length} on phone</span>
      </div>
      ${mine.length
        ? mine.map(packRow).join('')
        : `<div class="card"><div class="card-body">
             <p class="muted">You have not chosen any subjects yet.</p>
             <a class="btn btn-primary" href="${url('student/subjects.html')}">Choose subjects</a>
           </div></div>`}
    </section>

    <section>
      <div class="section-head">
        <h2>Want another subject?</h2>
      </div>
      <div class="dl-row">
        <span class="itile">➕</span>
        <div class="grow" style="min-width:0">
          <div class="fw-semibold t-sm">Add a subject</div>
          <div class="hint">Its lessons and questions download with the next sync</div>
        </div>
        <a class="btn btn-secondary btn-sm" href="${url('student/subjects.html')}">Choose</a>
      </div>
    </section>`;

  wire();
  hydrateIcons();
}

function packRow(subject) {
  const status = packStatus(subject.id);
  const bytes = packSize(subject.id);
  const topics = packTopics(subject.id);
  const questions = packQuestions(subject.id);

  const chip = {
    ready: '<span class="chip chip-brand">On phone</span>',
    partial: '<span class="chip chip-amber">Partly downloaded</span>',
    missing: '',
  }[status];

  const note = status === 'missing'
    ? 'Not downloaded yet'
    : `${topics} ${topics === 1 ? 'topic' : 'topics'} · ${questions} questions · ${human(bytes)}`;

  return `
  <div class="dl-row" data-row="${subject.id}">
    <span class="itile ${toneClass(subject.tone)}">${subject.emoji}</span>

    <div class="grow" style="min-width:0">
      <div class="row gap-2 wrap">
        <span class="fw-semibold t-sm">${esc(subject.name)}</span>
        ${chip}
      </div>
      <div class="hint">${esc(note)}</div>
    </div>

    ${status === 'missing'
      ? '<span class="t-xs subtle">Use the button above</span>'
      : `<button class="btn btn-ghost btn-icon" data-remove="${subject.id}"
                 aria-label="Remove ${esc(subject.name)} from this phone">
           ${icon('close', 18)}
         </button>`}
  </div>`;
}

function wire() {
  $('#allBtn')?.addEventListener('click', async () => {
    if (busy) return;

    busy = true;
    stage = 'Starting…';
    await paint();

    const res = await downloadAll(async (label) => {
      stage = label;
      await paint();
    });

    busy = false;
    stage = '';

    if (!res.ok) {
      toast(
        res.reason === 'offline'
          ? 'You are offline — connect and try again'
          : 'That did not finish. Try again in a moment.',
        'danger'
      );
    } else {
      toast(`Ready to use offline — ${human(res.bytes)} on this phone`, 'success');
    }

    await paint();
  });

  $$('[data-remove]').forEach((b) => b.addEventListener('click', async () => {
    const id = b.dataset.remove;
    const subject = byId(id);

    const yes = await confirmDialog({
      title: `Remove ${subject?.name || 'this pack'}?`,
      message: 'Its lessons and questions leave this phone. You stay enrolled, '
        + 'and they come back the next time you download with network.',
      confirmText: 'Remove',
      danger: true,
    });

    if (!yes) return;

    removePack(id);
    toast('Removed from this phone');
    await paint();
  }));
}

/* Repaint when the network comes back: the button says something different. */
window.addEventListener('online', () => paint());
window.addEventListener('offline', () => paint());

render();
