/* student-practice.js — build a practice set or a full mock exam. */

import { boot } from '../core/boot.js';
import { hydrateIcons, icon } from '../core/icons.js';
import { $, $$, esc, grade } from '../core/ui.js';
import { url, param } from '../core/paths.js';
import { requireAuth } from '../core/auth.js';
import { mountShell } from '../components/shell.js';
import { getProgress, weakestSubject } from '../data/progress.js';
import { byId, toneClass } from '../data/subjects.js';
import { YEARS, EXAM_BODIES, topicsFor, countFor } from '../data/questions.js';
import * as api from '../core/api.js';
import * as store from '../core/store.js';

boot();
const me = requireAuth('student');
function render() {
  const p = getProgress();
  const mine = (me.subjects?.length ? me.subjects : ['maths']).map(byId).filter(Boolean);
  const weak = weakestSubject(p);

  /* Pre-fill from the link that brought the student here. */
  const cfg = {
    subject: param('subject') || mine[0].id,
    body: '',
    year: '',
    topic: '',
    count: param('mode') === 'mock' ? 40 : 20,
    timed: true,
    mock: param('mode') === 'mock',
  };

  const page = mountShell({
    user: me,
    active: 'practice',
    title: 'Past questions',
    subtitle: 'WAEC · JAMB · NECO, 2010–2025, stored offline',
  });

  page.innerHTML = `
    <div class="page-head">
      <div>
        <h1>Practice &amp; mock exams</h1>
        <p class="sub">Every question below is on your phone already. No data needed.</p>
      </div>
    </div>

    <!-- Quick starts -->
    <section class="qa-grid" style="margin-bottom:var(--s-8)">
      <a class="qa" href="${url(`student/exam.html?subject=${cfg.subject}&count=10&timed=1`)}">
        <span class="itile">⚡</span>
        <span class="t">Quick 10</span>
        <span class="s">15 minutes, one subject</span>
      </a>
      <a class="qa" href="${url(`student/exam.html?subject=${cfg.subject}&count=40&timed=1&mock=1`)}">
        <span class="itile itile-amber">⏱️</span>
        <span class="t">Full mock</span>
        <span class="s">40 questions, real timing</span>
      </a>
      ${weak ? `
      <a class="qa" href="${url(`student/exam.html?subject=${weak.id}&count=20&timed=1`)}">
        <span class="itile itile-clay">🎯</span>
        <span class="t">Fix weak areas</span>
        <span class="s">${esc(byId(weak.id).short)} · ${weak.mastery}% mastery</span>
      </a>` : ''}
      <a class="qa" href="${url(`student/exam.html?subject=${cfg.subject}&count=20&timed=0`)}">
        <span class="itile itile-sky">🧘🏾</span>
        <span class="t">No timer</span>
        <span class="s">Learn at your own pace</span>
      </a>
    </section>

    <div class="grid" style="grid-template-columns:minmax(0,1.5fr) minmax(0,1fr);gap:var(--s-6)" id="prCols">

      <!-- Builder -->
      <section class="card card-pad-lg">
        <div class="card-header">
          <span class="card-title">Build your own set</span>
          <span class="chip chip-brand" id="poolChip"></span>
        </div>

        <div class="stack gap-6">

          <div class="field">
            <span class="label">Subject</span>
            <div class="row gap-2 wrap" id="subjectPicks">
              ${mine.map((s) => `
                <button class="pick ${s.id === cfg.subject ? 'is-active' : ''}" data-subject="${s.id}">
                  ${s.emoji} ${esc(s.short)}
                </button>`).join('')}
            </div>
          </div>

          <div class="field">
            <span class="label">Exam body</span>
            <div class="row gap-2 wrap" id="bodyPicks">
              <button class="pick is-active" data-body="">All</button>
              ${EXAM_BODIES.map((b) => `<button class="pick" data-body="${b}">${b}</button>`).join('')}
            </div>
          </div>

          <div class="grid grid-2">
            <div class="field">
              <label class="label" for="yearSel">Year</label>
              <select class="select" id="yearSel">
                <option value="">All years (2010–2025)</option>
                ${YEARS.map((y) => `<option value="${y}">${y}</option>`).join('')}
              </select>
            </div>
            <div class="field">
              <label class="label" for="topicSel">Topic</label>
              <select class="select" id="topicSel"></select>
            </div>
          </div>

          <div class="field">
            <span class="label">How many questions?</span>
            <div class="row gap-2 wrap" id="countPicks">
              ${[10, 20, 40, 60].map((n) => `
                <button class="pick ${n === cfg.count ? 'is-active' : ''}" data-count="${n}">${n}</button>`).join('')}
            </div>
            <span class="hint" id="timeHint"></span>
          </div>

          <label class="switch">
            <input type="checkbox" id="timed" checked>
            <span class="track"></span>
            <span class="t-sm">
              <b>Timed like the real exam</b>
              <span class="hint" style="display:block">JAMB pace: about 1 minute 30 per question</span>
            </span>
          </label>

          <a class="btn btn-primary btn-lg btn-block" id="startBtn" href="#">
            Start practice ${icon('arrowR', 18)}
          </a>
        </div>
      </section>

      <!-- Side -->
      <div class="stack gap-6">

        <section class="card">
          <div class="card-header">
            <span class="card-title">Recent attempts</span>
            <a class="t-xs fw-semibold" href="${url('student/results.html')}">All results</a>
          </div>
          ${p.exams.length ? p.exams.slice(0, 4).map((e) => {
            const pct = Math.round((e.score / e.total) * 100);
            const g = grade(pct);
            const s = byId(e.subject);
            return `
            <div class="list-row">
              <span class="itile itile-sm ${s ? toneClass(s.tone) : ''}">${s ? s.emoji : '📝'}</span>
              <div class="grow" style="min-width:0">
                <div class="t-sm fw-semibold clamp-1">${esc(e.title)}</div>
                <div class="hint">${e.score}/${e.total} · ${new Date(e.date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}</div>
              </div>
              <span class="chip ${g.tone === 'success' ? 'chip-brand' : g.tone === 'danger' ? 'chip-danger' : 'chip-amber'}">
                ${g.band}
              </span>
            </div>`;
          }).join('') : '<div class="empty"><div class="itile">📝</div><p class="t-sm">No attempt yet. Start with a quick 10.</p></div>'}
        </section>

        <section class="card card-flat" style="background:var(--surface-brand);border-color:var(--border-brand)">
          <div class="row gap-3">
            <span class="itile">💡</span>
            <div>
              <div class="fw-bold t-sm">Practise the way you will be examined</div>
              <p class="t-xs muted" style="margin-top:4px">
                Turning the timer on is the difference between knowing the work and
                finishing the paper. Most students lose marks to the clock, not the syllabus.
              </p>
            </div>
          </div>
        </section>

        <section class="card">
          <div class="card-header"><span class="card-title">Your question bank</span></div>
          <div class="stack gap-3">
            ${mine.map((s) => {
              const stocked = countFor(s.id);
              const state = (store.get('practice-state', {}) || {})[s.id];

              return `
              <div class="row between t-sm">
                <span class="row gap-2">${s.emoji} ${esc(s.short)}</span>
                <span class="subtle tnum">${
                  stocked
                    ? `${stocked}+ questions`
                    : (state === 'building' ? 'being written…' : 'none yet')
                }</span>
              </div>`;
            }).join('')}
          </div>
          <a class="btn btn-secondary btn-block btn-sm" style="margin-top:var(--s-4)"
             href="${url('student/downloads.html')}">Download more years</a>
        </section>
      </div>
    </div>`;

  /* --- Builder wiring ------------------------------------------------------ */

  function paintTopics() {
    const topics = topicsFor(cfg.subject);
    $('#topicSel').innerHTML = `<option value="">All topics</option>${
      topics.map((t) => `<option value="${esc(t)}" ${t === cfg.topic ? 'selected' : ''}>${esc(t)}</option>`).join('')}`;
  }

  /**
   * Nudge the server when the bank for this subject is thin.
   *
   * Fire-and-forget, and throttled by the server: the point is that the next
   * sync brings questions, not that this call returns any. A student with no
   * network is unaffected — api.js resolves offline rather than throwing.
   */
  function topUp(subjectId) {
    if (countFor(subjectId) >= 10) return;

    // The bank is keyed by subject and level; the topic filter on this screen
    // is a question topic, not a scheme ref, so the whole subject is stocked.
    api.requestPractice(subjectId)
      .then((res) => {
        if (res.ok && res.data?.queued) {
          store.patch('practice-state', { [subjectId]: 'building' });
        }
      })
      .catch(() => { /* never surfaces to the student */ });
  }

  function paintMeta() {
    const mins = Math.round(cfg.count * 1.5);
    $('#timeHint').textContent = cfg.timed
      ? `${cfg.count} questions ≈ ${mins} minutes on the clock`
      : 'No timer — take as long as you need';
    const pool = countFor(cfg.subject);

    $('#poolChip').textContent = pool
      ? `${pool}+ in ${byId(cfg.subject).short}`
      : `Building your ${byId(cfg.subject).short} bank…`;

    topUp(cfg.subject);
    $('#startBtn').setAttribute('href', url(
      `student/exam.html?subject=${cfg.subject}&count=${cfg.count}&timed=${cfg.timed ? 1 : 0}`
      + `${cfg.body ? `&body=${cfg.body}` : ''}${cfg.year ? `&year=${cfg.year}` : ''}`
      + `${cfg.topic ? `&topic=${encodeURIComponent(cfg.topic)}` : ''}${cfg.count >= 40 ? '&mock=1' : ''}`
    ));
    $('#startBtn').innerHTML = `${cfg.count >= 40 ? 'Start mock exam' : 'Start practice'} ${icon('arrowR', 18)}`;
  }

  $$('[data-subject]').forEach((b) => b.addEventListener('click', () => {
    cfg.subject = b.dataset.subject;
    cfg.topic = '';
    $$('[data-subject]').forEach((x) => x.classList.toggle('is-active', x === b));
    paintTopics(); paintMeta();
  }));

  $$('[data-body]').forEach((b) => b.addEventListener('click', () => {
    cfg.body = b.dataset.body;
    $$('[data-body]').forEach((x) => x.classList.toggle('is-active', x === b));
    paintMeta();
  }));

  $$('[data-count]').forEach((b) => b.addEventListener('click', () => {
    cfg.count = Number(b.dataset.count);
    $$('[data-count]').forEach((x) => x.classList.toggle('is-active', x === b));
    paintMeta();
  }));

  $('#yearSel').addEventListener('change', (e) => { cfg.year = e.target.value; paintMeta(); });
  $('#topicSel').addEventListener('change', (e) => { cfg.topic = e.target.value; paintMeta(); });
  $('#timed').addEventListener('change', (e) => { cfg.timed = e.target.checked; paintMeta(); });

  const cols = $('#prCols');
  const reflow = () => {
    cols.style.gridTemplateColumns = window.innerWidth < 1000
      ? 'minmax(0,1fr)' : 'minmax(0,1.5fr) minmax(0,1fr)';
  };
  reflow();
  window.addEventListener('resize', reflow);

  paintTopics();
  paintMeta();
  hydrateIcons();
}

/* Start only once every module-level constant above is initialised.
   Calling render() higher up would hit the temporal dead zone on any
   const declared below it — a hoisted function can be called early,
   the constants it closes over cannot. */
if (me) render();
