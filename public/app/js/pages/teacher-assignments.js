/* teacher-assignments.js — the assignment list and the AI drafting flow. */

import { boot } from '../core/boot.js';
import { hydrateIcons, icon } from '../core/icons.js';
import { $, $$, esc, toast, modal, closeOverlay } from '../core/ui.js';
import { url } from '../core/paths.js';
import { requireAuth } from '../core/auth.js';
import { mountShell } from '../components/shell.js';
import * as store from '../core/store.js';
import { CLASSES, ASSIGNMENTS, submissionsFor } from '../data/classroom.js';
import { SCHEMES } from '../data/scheme.js';
import { buildSet } from '../data/questions.js';
import { byId } from '../data/subjects.js';

boot();
const me = requireAuth('teacher');
const DRAFTS_KEY = 'teacher-drafts';

const drafts = () => store.get(DRAFTS_KEY, []) || [];
const saveDraft = (d) => store.set(DRAFTS_KEY, [d, ...drafts()]);

function render() {
  const page = mountShell({
    user: me,
    active: 'assignments',
    title: 'Assignments',
    subtitle: 'Set by you or drafted by the AI',
  });
  page.innerHTML = '<div id="body"></div>';
  paint();
}

function paint() {
  const mine = [...drafts(), ...ASSIGNMENTS];
  const subject = byId(me.subject || 'maths');

  $('#body').innerHTML = `
    <div class="page-head">
      <div>
        <h1>Assignments</h1>
        <p class="sub">${mine.length} set this term · ${esc(subject ? subject.name : 'Mathematics')}</p>
      </div>
      <button class="btn btn-primary" id="newBtn">${icon('sparkle', 16)} Draft with AI</button>
    </div>

    <section class="card card-flat" style="background:var(--surface-blue, var(--surface-brand));
             border-color:var(--border-brand);margin-bottom:var(--s-6)">
      <div class="row gap-4 wrap items-center">
        <span class="itile itile-lg">🤖</span>
        <div class="grow" style="min-width:220px">
          <div class="fw-bold">You do not write the questions any more</div>
          <p class="t-sm muted" style="margin-top:4px">
            Pick a class, a topic and how many questions. The AI drafts the paper,
            the marking scheme and the model answers from the WAEC syllabus. You
            review it before a single student sees it.
          </p>
        </div>
        <button class="btn btn-primary" id="newBtn2">Draft an assignment</button>
      </div>
    </section>

    <section class="card">
      <div class="card-header">
        <span class="card-title">All assignments</span>
        <span class="chip chip-outline">${mine.length}</span>
      </div>

      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th>Title</th><th>Class</th><th>Type</th><th>Set by</th><th>Due</th><th>Progress</th><th></th></tr>
          </thead>
          <tbody>
            ${mine.map((a) => {
              const cls = CLASSES.find((c) => c.id === a.classId);
              const subs = a.id.startsWith('d') ? [] : submissionsFor(a.id);
              const approved = subs.filter((s) => s.status === 'approved').length;
              const pending = subs.length - approved;
              const pct = subs.length ? Math.round((approved / subs.length) * 100) : 0;
              return `
              <tr>
                <td>
                  <div class="fw-semibold">${esc(a.title)}</div>
                  <div class="hint">${a.questions} questions</div>
                </td>
                <td>${esc(cls ? cls.name : '—')}</td>
                <td>
                  <span class="chip ${a.type === 'test' ? 'chip-amber' : ''}">
                    ${a.type === 'test' ? 'Test' : 'Classwork'}
                  </span>
                </td>
                <td>
                  <span class="chip ${a.setBy === 'ai' ? 'chip-brand' : 'chip-outline'}">
                    ${a.setBy === 'ai' ? '🤖 AI' : '👩🏾‍🏫 You'}
                  </span>
                </td>
                <td class="subtle">${fmt(a.due)}</td>
                <td style="min-width:140px">
                  ${subs.length ? `
                    <div class="row gap-2">
                      <span class="tnum t-xs">${approved}/${subs.length}</span>
                      <span class="bar bar-sm grow" style="min-width:50px"><i style="width:${pct}%"></i></span>
                    </div>
                    ${pending ? `<span class="hint danger-text">${pending} to mark</span>` : '<span class="hint">All marked</span>'}
                  ` : '<span class="hint">Not sent yet</span>'}
                </td>
                <td>
                  ${pending
                    ? `<a class="btn btn-primary btn-sm" href="${url(`teacher/marking.html?assignment=${a.id}`)}">Mark</a>`
                    : `<button class="btn btn-ghost btn-sm" data-preview="${esc(a.id)}">View</button>`}
                </td>
              </tr>`;
            }).join('')}
          </tbody>
        </table>
      </div>
    </section>`;

  $('#newBtn').addEventListener('click', openDrafter);
  $('#newBtn2').addEventListener('click', openDrafter);
  $$('[data-preview]').forEach((b) => b.addEventListener('click', () => {
    const a = mine.find((x) => x.id === b.dataset.preview);
    previewPaper(a);
  }));

  hydrateIcons();
}

/* --- The drafting flow ------------------------------------------------------ */

function openDrafter() {
  const subjectId = me.subject || 'maths';
  const weeks = SCHEMES[subjectId] || SCHEMES.maths;

  const cfg = {
    classId: CLASSES[0].id,
    week: 3,
    type: 'classwork',
    count: 10,
    due: new Date(Date.now() + 4 * 864e5).toISOString().slice(0, 10),
  };

  modal(`
    <h3 style="margin-bottom:var(--s-2)">Draft an assignment</h3>
    <p class="t-sm muted" style="margin-bottom:var(--s-5)">
      The AI pulls from the same scheme of work your students are following.
    </p>

    <div class="stack gap-5">
      <div class="field">
        <span class="label">Class</span>
        <div class="row gap-2 wrap" id="clsPicks">
          ${CLASSES.map((c) => `
            <button class="pick ${c.id === cfg.classId ? 'is-active' : ''}" data-cls="${c.id}">
              ${esc(c.name)}
            </button>`).join('')}
        </div>
      </div>

      <div class="field">
        <label class="label" for="weekSel">Week / topic</label>
        <select class="select" id="weekSel">
          ${weeks.map((w) => `
            <option value="${w.week}" ${w.week === cfg.week ? 'selected' : ''}>
              Week ${w.week} — ${esc(w.title)}
            </option>`).join('')}
        </select>
      </div>

      <div class="grid grid-2">
        <div class="field">
          <span class="label">Type</span>
          <div class="row gap-2">
            <button class="pick is-active" data-type="classwork">Classwork</button>
            <button class="pick" data-type="test">Test</button>
          </div>
        </div>
        <div class="field">
          <label class="label" for="dueInput">Due date</label>
          <input class="input" id="dueInput" type="date" value="${cfg.due}">
        </div>
      </div>

      <div class="field">
        <span class="label">Number of questions</span>
        <div class="row gap-2 wrap" id="cntPicks">
          ${[5, 10, 20, 40].map((n) => `
            <button class="pick ${n === cfg.count ? 'is-active' : ''}" data-cnt="${n}">${n}</button>`).join('')}
        </div>
      </div>

      <div class="row gap-3">
        <button class="btn btn-secondary grow" data-close>Cancel</button>
        <button class="btn btn-primary grow" id="draftBtn">${icon('sparkle', 16)} Draft it</button>
      </div>
    </div>`, { wide: true });

  $$('[data-cls]').forEach((b) => b.addEventListener('click', () => {
    cfg.classId = b.dataset.cls;
    $$('[data-cls]').forEach((x) => x.classList.toggle('is-active', x === b));
  }));
  $$('[data-type]').forEach((b) => b.addEventListener('click', () => {
    cfg.type = b.dataset.type;
    $$('[data-type]').forEach((x) => x.classList.toggle('is-active', x === b));
  }));
  $$('[data-cnt]').forEach((b) => b.addEventListener('click', () => {
    cfg.count = Number(b.dataset.cnt);
    $$('[data-cnt]').forEach((x) => x.classList.toggle('is-active', x === b));
  }));
  $('#weekSel').addEventListener('change', (e) => { cfg.week = Number(e.target.value); });
  $('#dueInput').addEventListener('change', (e) => { cfg.due = e.target.value; });

  $('#draftBtn').addEventListener('click', () => {
    const btn = $('#draftBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="typing"><i></i><i></i><i></i></span> Drafting…';
    /* The pause is the "AI is working" beat; the work itself is instant. */
    setTimeout(() => showDraft(cfg, weeks), 900);
  });

  hydrateIcons();
}

function showDraft(cfg, weeks) {
  const subjectId = me.subject || 'maths';
  const week = weeks.find((w) => w.week === cfg.week) || weeks[0];
  const cls = CLASSES.find((c) => c.id === cfg.classId);
  const questions = buildSet({ subject: subjectId, count: Math.min(cfg.count, 8), offset: cfg.week });
  const title = `Week ${week.week} ${cfg.type === 'test' ? 'Test' : 'Classwork'} · ${week.title}`;

  closeOverlay();
  modal(`
    <div class="row between gap-3" style="margin-bottom:var(--s-4)">
      <div>
        <h3>${esc(title)}</h3>
        <p class="hint">${esc(cls.name)} · ${cfg.count} questions · due ${fmt(cfg.due)}</p>
      </div>
      <span class="chip chip-brand">🤖 AI draft</span>
    </div>

    <div class="alert alert-success" style="margin-bottom:var(--s-4)">
      ${icon('check', 18)}
      <span>Drafted from the ${esc(week.title)} scheme, with a marking scheme and model
      answers attached to every question.</span>
    </div>

    <div class="stack gap-3" style="max-height:340px;overflow-y:auto;margin-bottom:var(--s-5)">
      ${questions.map((q, i) => `
        <div class="script-q">
          <div class="row between gap-2" style="margin-bottom:var(--s-2)">
            <span class="t-2xs fw-bold subtle">QUESTION ${i + 1}</span>
            <span class="chip chip-outline t-2xs">${esc(q.topic)}</span>
          </div>
          <div class="t-sm fw-medium" style="margin-bottom:var(--s-2)">${esc(q.q)}</div>
          <div class="t-xs subtle">Answer: ${esc(q.options[q.answer])}</div>
        </div>`).join('')}
      ${cfg.count > 8 ? `
        <div class="empty" style="padding:var(--s-4)">
          <p class="t-xs">+ ${cfg.count - 8} more questions in the full draft</p>
        </div>` : ''}
    </div>

    <div class="row gap-3">
      <button class="btn btn-secondary grow" data-close>Discard</button>
      <button class="btn btn-primary grow" id="publishBtn">Send to ${esc(cls.name)}</button>
    </div>`, { wide: true });

  $('#publishBtn').addEventListener('click', () => {
    saveDraft({
      id: `d${Date.now().toString(36)}`,
      title,
      subject: subjectId,
      classId: cfg.classId,
      type: cfg.type,
      setBy: 'ai',
      questions: cfg.count,
      assigned: new Date().toISOString().slice(0, 10),
      due: cfg.due,
      submitted: 0,
      marked: 0,
    });
    closeOverlay();
    toast(`Sent to ${cls.name}. Students see it the moment they open the app.`, 'success', 3600);
    paint();
  });

  hydrateIcons();
}

function previewPaper(a) {
  if (!a) return;
  const cls = CLASSES.find((c) => c.id === a.classId);
  modal(`
    <h3 style="margin-bottom:var(--s-2)">${esc(a.title)}</h3>
    <p class="t-sm muted" style="margin-bottom:var(--s-5)">
      ${esc(cls ? cls.name : '')} · ${a.questions} questions · due ${fmt(a.due)}
    </p>
    <div class="stack gap-3">
      <div class="doc-row"><span class="muted">Set by</span><span class="fw-semibold">${a.setBy === 'ai' ? 'AI draft, approved by you' : 'You'}</span></div>
      <div class="doc-row"><span class="muted">Submitted</span><span class="fw-semibold">${a.submitted}</span></div>
      <div class="doc-row"><span class="muted">Marked</span><span class="fw-semibold">${a.marked}</span></div>
      <div class="doc-row"><span class="muted">Assigned</span><span class="fw-semibold">${fmt(a.assigned)}</span></div>
    </div>
    <button class="btn btn-primary btn-block" style="margin-top:var(--s-5)" data-close>Close</button>`);
}

const fmt = (d) => new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });

/* Start only once every module-level constant above is initialised.
   Calling render() higher up would hit the temporal dead zone on any
   const declared below it — a hoisted function can be called early,
   the constants it closes over cannot. */
if (me) render();
