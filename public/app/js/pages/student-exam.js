/* ==========================================================================
   student-exam.js — the exam runner.
   Behaves like the real thing: countdown, question palette, flag-for-review,
   and an autosave on every tap so a dead battery never costs a whole paper.
   ========================================================================== */

import { boot } from '../core/boot.js';
import { hydrateIcons, icon } from '../core/icons.js';
import { $, $$, esc, clock, confirmDialog, toast } from '../core/ui.js';
import { param, goReplace } from '../core/paths.js';
import { requireAuth } from '../core/auth.js';
import { mountShell } from '../components/shell.js';
import * as store from '../core/store.js';
import { byId } from '../data/subjects.js';
import { buildSet } from '../data/questions.js';
import { recordExam, addStudyMinutes } from '../data/progress.js';
import { sendAttempt } from '../core/sync.js';

boot();
const me = requireAuth('student');
const RESUME_KEY = 'exam-in-progress';
const LAST_KEY = 'exam-last';

/** RFC-4122-shaped id; crypto.randomUUID is missing on some older Androids. */
function uuid() {
  if (crypto.randomUUID) return crypto.randomUUID();

  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

function run() {
  const subject = byId(param('subject', 'maths')) || byId('maths');
  const count = Math.max(1, Number(param('count', 20)));
  const timed = param('timed', '1') === '1';
  const isMock = param('mock') === '1';
  const body = param('body') || null;
  const year = param('year') || null;
  const topic = param('topic') ? decodeURIComponent(param('topic')) : null;

  const title = `${isMock ? 'Mock exam' : 'Practice'} · ${subject.short}`;
  const seconds = Math.round(count * 90);          // JAMB pace: 1m30 a question

  /* Resume an interrupted paper if it matches this configuration. */
  const saved = store.get(RESUME_KEY);
  const fresh = {
    subject: subject.id, count, timed, isMock, title,
    questions: buildSet({ subject: subject.id, body, year, topic, count, offset: new Date().getDate() }),
    answers: {},
    flags: {},
    index: 0,
    left: seconds,
    startedAt: new Date().toISOString(),
    clientUuid: uuid(),
  };
  const state = (saved && saved.subject === subject.id && saved.count === count && saved.questions?.length)
    ? saved
    : fresh;

  const page = mountShell({
    user: me,
    active: 'practice',
    title,
    subtitle: `${count} questions${timed ? ` · ${Math.round(seconds / 60)} minutes` : ' · untimed'}`,
  });

  page.innerHTML = `
    <div class="exam-bar">
      <span class="itile itile-sm">${subject.emoji}</span>
      <div class="grow" style="min-width:0">
        <div class="fw-bold t-sm clamp-1">${esc(title)}</div>
        <div class="hint" id="progressText"></div>
      </div>
      <span class="timer" id="timer">--:--</span>
      <button class="btn btn-primary btn-sm" id="submitBtn">Submit</button>
    </div>

    <div class="grid" style="grid-template-columns:minmax(0,1fr) 260px;gap:var(--s-6)" id="examCols">

      <section class="card card-pad-lg">
        <div class="row between wrap gap-2" style="margin-bottom:var(--s-5)">
          <div class="row gap-2 wrap" id="qMeta"></div>
          <button class="btn btn-ghost btn-sm" id="flagBtn">${icon('flag', 16)} Flag</button>
        </div>

        <div class="q-text" id="qText" style="margin-bottom:var(--s-6)"></div>

        <div class="stack gap-3" id="options"></div>

        <div class="row between gap-3" style="margin-top:var(--s-8)">
          <button class="btn btn-secondary" id="prevBtn">${icon('arrowL', 16)} Previous</button>
          <span class="t-xs subtle hide-mobile">Tip: press A–D or 1–4 to answer</span>
          <button class="btn btn-primary" id="nextBtn">Next ${icon('arrowR', 16)}</button>
        </div>
      </section>

      <aside class="stack gap-5 side-card">
        <section class="card">
          <div class="card-header"><span class="card-title">Questions</span></div>
          <div class="palette" id="palette"></div>
          <div class="stack gap-2 t-2xs subtle" style="margin-top:var(--s-4)">
            <span class="row gap-2"><i class="badge-dot" style="background:var(--primary)"></i> Answered</span>
            <span class="row gap-2"><i class="badge-dot" style="background:var(--amber-400)"></i> Flagged for review</span>
            <span class="row gap-2"><i class="badge-dot" style="background:var(--border-strong)"></i> Not attempted</span>
          </div>
        </section>

        <section class="card card-flat" style="background:var(--bg-sunken)">
          <div class="t-xs fw-bold" style="margin-bottom:var(--s-2)">${icon('wifiOff', 14)} Offline exam</div>
          <p class="t-2xs muted">
            Your answers save on this phone as you tap. If the app closes, you
            continue from the same question.
          </p>
        </section>
      </aside>
    </div>`;

  /* --- Rendering ----------------------------------------------------------- */

  const q = () => state.questions[state.index];

  function paintQuestion() {
    const item = q();
    $('#qMeta').innerHTML = `
      <span class="chip chip-brand">Q${state.index + 1} of ${state.questions.length}</span>
      <span class="chip">${esc(item.body)} ${item.year}</span>
      <span class="chip chip-outline">${esc(item.topic)}</span>`;

    $('#qText').textContent = item.q;

    $('#options').innerHTML = item.options.map((o, k) => `
      <button class="choice ${state.answers[state.index] === k ? 'is-active' : ''}" data-pick="${k}">
        <span class="choice-key">${'ABCD'[k]}</span>
        <span class="grow">${esc(o)}</span>
      </button>`).join('');

    $$('#options [data-pick]').forEach((b) => b.addEventListener('click', () => {
      state.answers[state.index] = Number(b.dataset.pick);
      save();
      paintQuestion();
      paintPalette();
      /* Auto-advance keeps the rhythm of a real paper going. */
      if (state.index < state.questions.length - 1) setTimeout(next, 180);
    }));

    $('#flagBtn').className = `btn btn-sm ${state.flags[state.index] ? 'btn-amber' : 'btn-ghost'}`;
    $('#flagBtn').innerHTML = `${icon('flag', 16)} ${state.flags[state.index] ? 'Flagged' : 'Flag'}`;

    $('#prevBtn').disabled = state.index === 0;
    $('#nextBtn').innerHTML = state.index === state.questions.length - 1
      ? 'Review'
      : `Next ${icon('arrowR', 16)}`;

    const answered = Object.keys(state.answers).length;
    $('#progressText').textContent =
      `${answered} answered · ${state.questions.length - answered} left`;

    hydrateIcons();
  }

  function paintPalette() {
    $('#palette').innerHTML = state.questions.map((_, i) => {
      const cls = [
        state.answers[i] !== undefined ? 'answered' : '',
        state.flags[i] ? 'flagged' : '',
        i === state.index ? 'current' : '',
      ].filter(Boolean).join(' ');
      return `<button class="${cls}" data-go="${i}">${i + 1}</button>`;
    }).join('');

    $$('#palette [data-go]').forEach((b) => b.addEventListener('click', () => {
      state.index = Number(b.dataset.go);
      save(); paintQuestion(); paintPalette();
    }));
  }

  /* --- Navigation ---------------------------------------------------------- */

  const next = () => {
    if (state.index < state.questions.length - 1) {
      state.index += 1; save(); paintQuestion(); paintPalette();
    } else {
      submit();
    }
  };
  const prev = () => {
    if (state.index > 0) { state.index -= 1; save(); paintQuestion(); paintPalette(); }
  };

  $('#nextBtn').addEventListener('click', next);
  $('#prevBtn').addEventListener('click', prev);
  $('#flagBtn').addEventListener('click', () => {
    state.flags[state.index] = !state.flags[state.index];
    save(); paintQuestion(); paintPalette();
  });

  document.addEventListener('keydown', (e) => {
    /* Ignore shortcuts while typing. e.target can be the Document itself
       when nothing is focused, and Document has no .matches — hence the
       optional call rather than a bare e.target.matches(). */
    if (e.target?.matches?.('input, textarea, select, [contenteditable]')) return;
    const k = e.key.toUpperCase();
    if ('ABCD'.includes(k)) {
      const idx = 'ABCD'.indexOf(k);
      if (idx < q().options.length) {
        state.answers[state.index] = idx; save(); paintQuestion(); paintPalette();
      }
    }
    if ('1234'.includes(k)) {
      const idx = Number(k) - 1;
      if (idx < q().options.length) {
        state.answers[state.index] = idx; save(); paintQuestion(); paintPalette();
      }
    }
    if (e.key === 'ArrowRight') next();
    if (e.key === 'ArrowLeft') prev();
    if (e.key.toLowerCase() === 'f') {
      state.flags[state.index] = !state.flags[state.index];
      save(); paintQuestion(); paintPalette();
    }
  });

  /* --- Timer --------------------------------------------------------------- */

  let ticker;
  function paintTimer() {
    const el = $('#timer');
    if (!timed) {
      el.textContent = clock(state.elapsed || 0);
      el.className = 'timer';
      return;
    }
    el.textContent = clock(state.left);
    el.className = `timer ${state.left <= 60 ? 'danger' : state.left <= 300 ? 'warn' : ''}`;
  }

  function startTimer() {
    ticker = setInterval(() => {
      if (timed) {
        state.left -= 1;
        if (state.left === 300) toast('5 minutes left', 'warn', 4000);
        if (state.left <= 0) { clearInterval(ticker); return submit(true); }
      } else {
        state.elapsed = (state.elapsed || 0) + 1;
      }
      if (state.left % 5 === 0) save();
      paintTimer();
    }, 1000);
  }

  /* --- Persistence --------------------------------------------------------- */

  const save = () => store.set(RESUME_KEY, state);

  /* --- Submit -------------------------------------------------------------- */

  async function submit(auto = false) {
    const answered = Object.keys(state.answers).length;
    const blank = state.questions.length - answered;

    if (!auto && blank > 0) {
      const yes = await confirmDialog({
        title: `Submit with ${blank} unanswered?`,
        message: 'Nothing dey deducted for a wrong answer here, so a guess beats a blank. Are you sure you want to submit?',
        confirmText: 'Submit anyway',
        cancelText: 'Go back',
      });
      if (!yes) return;
    }

    clearInterval(ticker);

    const review = state.questions.map((item, i) => ({
      q: item.q,
      options: item.options,
      answer: item.answer,
      picked: state.answers[i],
      correct: state.answers[i] === item.answer,
      why: item.why,
      topic: item.topic,
      body: item.body,
      year: item.year,
    }));
    const score = review.filter((r) => r.correct).length;
    const minsSpent = timed
      ? Math.max(1, Math.round((state.count * 90 - state.left) / 60))
      : Math.max(1, Math.round((state.elapsed || 60) / 60));

    const result = {
      subject: subject.id,
      title: `${isMock ? 'Mock exam' : 'Practice'} · ${subject.name}`,
      score,
      total: state.questions.length,
      mins: minsSpent,
      date: new Date().toISOString().slice(0, 10),
      autoSubmitted: auto,
      review,
    };

    store.set(LAST_KEY, result);
    recordExam({
      subject: result.subject, title: result.title, score: result.score,
      total: result.total, mins: result.mins, date: result.date,
    });
    addStudyMinutes(minsSpent);
    store.remove(RESUME_KEY);

    /* Send the attempt up so it reaches the parent report and the teacher's
       view of practice. Fire-and-forget: if there is no network it queues
       itself, and the student is never held at a spinner for it. */
    sendAttempt({
      clientUuid: state.clientUuid || uuid(),
      subject: result.subject,
      kind: isMock ? 'mock' : 'practice',
      title: result.title,
      score: result.score,
      total: result.total,
      minutes: result.mins,
      autoSubmitted: auto,
      answers: review.map((r) => ({
        topic: r.topic,
        chosen: r.picked ?? null,
        correct: r.correct,
      })),
    }).catch(() => { /* queued, or not linked — either way the paper is safe */ });

    if (auto) toast('Time up — your paper was submitted automatically', 'warn', 3500);
    goReplace('student/results.html?last=1');
  }

  $('#submitBtn').addEventListener('click', () => submit(false));

  /* Leaving mid-paper should be deliberate. */
  window.addEventListener('beforeunload', (e) => {
    if (Object.keys(state.answers).length && store.get(RESUME_KEY)) {
      e.preventDefault();
      e.returnValue = '';
    }
  });

  const cols = $('#examCols');
  const reflow = () => {
    cols.style.gridTemplateColumns = window.innerWidth < 1000 ? 'minmax(0,1fr)' : 'minmax(0,1fr) 260px';
  };
  reflow();
  window.addEventListener('resize', reflow);

  if (state !== fresh) toast('Continuing where you stopped', 'success');

  paintQuestion();
  paintPalette();
  paintTimer();
  startTimer();
  save();
}

/* Start only once every module-level constant above is initialised.
   Calling render() higher up would hit the temporal dead zone on any
   const declared below it — a hoisted function can be called early,
   the constants it closes over cannot. */
if (me) {
  if (param('assignment')) {
    /* Teacher-set classwork mixes written answers with objective ones and
       submits to a real marking queue, so it gets its own runner. */
    import('./student-assignment-runner.js')
      .then((module) => module.run(me))
      .catch(() => run());
  } else {
    run();
  }
}
