/* student-subjects.js — the subject grid and the "add a subject" tray. */

import { boot } from '../core/boot.js';
import { hydrateIcons, icon } from '../core/icons.js';
import { $, $$, esc, toast } from '../core/ui.js';
import { url } from '../core/paths.js';
import { requireAuth, updateUser } from '../core/auth.js';
import { mountShell } from '../components/shell.js';
import { getProgress } from '../data/progress.js';
import { SUBJECTS, byId, toneClass } from '../data/subjects.js';
import { schemeFor, nextTopic } from '../data/scheme.js';
import { countFor } from '../data/questions.js';

boot();
let me = requireAuth('student');
function render() {
  const page = mountShell({
    user: me,
    active: 'subjects',
    title: 'My subjects',
    subtitle: `${me.level || 'SS2'} · scheme of work and lessons`,
  });
  page.innerHTML = '<div id="body"></div>';
  paint();
}

function paint() {
  const p = getProgress();
  const mine = (me.subjects || []).map(byId).filter(Boolean);
  const rest = SUBJECTS.filter((s) => !(me.subjects || []).includes(s.id));

  $('#body').innerHTML = `
    <div class="page-head">
      <div>
        <h1>My subjects</h1>
        <p class="sub">${mine.length} subject${mine.length === 1 ? '' : 's'} · every scheme runs 12 weeks</p>
      </div>
      <button class="btn btn-secondary" id="manageBtn">${icon('plus', 16)} Add a subject</button>
    </div>

    ${mine.length ? `
    <div class="subj-grid" style="margin-bottom:var(--s-10)">
      ${mine.map((s) => {
        const d = p.subjects[s.id] || { mastery: 0, done: 0, total: 24, weak: [] };
        const weeks = schemeFor(s.id, s.name, p.doneTopics);
        const totalTopics = weeks.reduce((a, w) => a + w.topics.length, 0);
        const doneTopics = weeks.reduce((a, w) => a + w.doneCount, 0);
        const pct = Math.round((doneTopics / totalTopics) * 100);
        const nxt = nextTopic(s.id, s.name, p.doneTopics);
        const tone = d.mastery >= 70 ? '' : d.mastery >= 50 ? 'amber' : 'danger';
        return `
        <div class="subj">
          <div class="row between">
            <span class="itile itile-lg ${toneClass(s.tone)}">${s.emoji}</span>
            <span class="chip ${d.mastery >= 70 ? 'chip-brand' : d.mastery >= 50 ? 'chip-amber' : 'chip-danger'}">
              ${d.mastery}% mastery
            </span>
          </div>

          <div>
            <div class="name">${esc(s.name)}</div>
            <div class="blurb clamp-2">${esc(s.blurb)}</div>
          </div>

          <div>
            <div class="row between t-xs subtle" style="margin-bottom:6px">
              <span>${doneTopics}/${totalTopics} topics</span><span>${pct}%</span>
            </div>
            <div class="bar"><i class="${tone}" style="width:${pct}%"></i></div>
          </div>

          <div class="row gap-2 wrap t-2xs subtle">
            <span class="chip chip-outline">${countFor(s.id)}+ past questions</span>
            <span class="chip chip-outline">${s.packMb} MB offline</span>
          </div>

          ${nxt ? `<div class="hint clamp-1">Next: ${esc(nxt.title)}</div>` : ''}

          <div class="row gap-2 mt-auto">
            <a class="btn btn-primary grow" href="${url(`student/scheme.html?subject=${s.id}`)}">
              Open scheme
            </a>
            <a class="btn btn-secondary btn-icon" title="Practice this subject"
               href="${url(`student/practice.html?subject=${s.id}`)}">${icon('clipboard', 18)}</a>
          </div>
        </div>`;
      }).join('')}
    </div>` : `
    <div class="empty card">
      <div class="itile">📚</div>
      <h3>No subject yet</h3>
      <p class="t-sm">Add the subjects you are offering and your schemes will be built straight away.</p>
      <button class="btn btn-primary" id="emptyAdd">Add subjects</button>
    </div>`}

    ${rest.length ? `
    <section id="tray">
      <div class="section-head">
        <h2>Add another subject</h2>
        <span class="t-xs subtle">Tap to add — the 12-week scheme builds instantly, offline</span>
      </div>
      <div class="subj-grid">
        ${rest.map((s) => `
          <button class="subj" data-add="${s.id}" style="text-align:left;cursor:pointer">
            <div class="row between">
              <span class="itile itile-lg ${toneClass(s.tone)}">${s.emoji}</span>
              ${s.core ? '<span class="chip chip-brand">Core</span>' : '<span class="chip">Elective</span>'}
            </div>
            <div>
              <div class="name">${esc(s.name)}</div>
              <div class="blurb clamp-2">${esc(s.blurb)}</div>
            </div>
            <div class="row gap-2 wrap t-2xs subtle mt-auto">
              <span class="chip chip-outline">${s.topics} topics</span>
              <span class="chip chip-outline">${s.packMb} MB</span>
            </div>
            <span class="btn btn-soft btn-block">${icon('plus', 16)} Add subject</span>
          </button>`).join('')}
      </div>
    </section>` : ''}`;

  $('#manageBtn')?.addEventListener('click', () =>
    $('#tray')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  );
  $('#emptyAdd')?.addEventListener('click', () =>
    $('#tray')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  );

  $$('[data-add]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.add;
      me = updateUser({ subjects: [...(me.subjects || []), id] });
      toast(`${byId(id).name} added — scheme ready`, 'success');
      paint();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  });

  hydrateIcons();
}

/* Start only once every module-level constant above is initialised.
   Calling render() higher up would hit the temporal dead zone on any
   const declared below it — a hoisted function can be called early,
   the constants it closes over cannot. */
if (me) render();
