/* ==========================================================================
   student-assignment-runner.js — answering the work a teacher set.

   Deliberately not the practice exam runner: this paper mixes objective
   questions with written answers, the questions come from the server rather
   than the local bank, and submitting it puts a real script in a real
   teacher's queue.

   What it shares with the exam runner is the part that matters on a cheap
   phone: every keystroke is saved locally, so a dead battery halfway through
   costs nothing, and a submission made with no network waits in a queue
   instead of being lost.
   ========================================================================== */

import { hydrateIcons, icon } from '../core/icons.js';
import { $, $$, esc, toast, confirmDialog } from '../core/ui.js';
import { url, param, goReplace } from '../core/paths.js';
import { mountShell } from '../components/shell.js';
import * as store from '../core/store.js';
import * as api from '../core/api.js';
import { queueSubmission } from '../core/sync.js';

const DRAFT = 'assignment-draft';

export async function run(me) {
  const id = Number(param('assignment'));

  const page = mountShell({
    user: me,
    active: 'assignments',
    title: 'Classwork',
    subtitle: 'Set by your teacher',
  });

  page.innerHTML = `<div class="card"><div class="skel" style="height:120px"></div></div>`;

  const paper = await loadPaper(id);

  if (!paper) {
    page.innerHTML = `
      <div class="empty card">
        <div class="itile">📴</div>
        <h3>Cannot open this paper</h3>
        <p class="t-sm">
          Your phone has not downloaded this assignment yet, and there is no
          network to fetch it now. Open it again once you have a connection.
        </p>
        <a class="btn btn-primary" href="${url('student/assignments.html')}">Back to classwork</a>
      </div>`;
    hydrateIcons();
    return;
  }

  if (paper.submission) {
    return renderAlreadySubmitted(page, paper);
  }

  renderPaper(page, paper, me);
}

/* --- Loading ------------------------------------------------------------- */

async function loadPaper(id) {
  const res = await api.assignment(id);

  if (res.ok && res.data?.assignment) {
    /* Cache it so the student can still answer after losing signal. */
    store.set(`assignment:${id}`, res.data);
    return res.data;
  }

  return store.get(`assignment:${id}`, null);
}

const draftKey = (id) => `${DRAFT}:${id}`;

/* --- Already done -------------------------------------------------------- */

function renderAlreadySubmitted(page, paper) {
  const { assignment, submission } = paper;
  const released = submission.released;
  const pct = released && submission.maxScore
    ? Math.round((submission.score / submission.maxScore) * 100)
    : null;

  page.innerHTML = `
    <a class="row gap-2 t-sm fw-semibold muted" href="${url('student/assignments.html')}"
       style="margin-bottom:var(--s-4)">${icon('arrowL', 16)} All classwork</a>

    <div class="card card-pad-lg">
      <div class="row gap-4 items-start" style="margin-bottom:var(--s-5)">
        <span class="itile itile-lg">${released ? '✅' : '⏳'}</span>
        <div>
          <h1 style="font-size:var(--text-xl)">${esc(assignment.title)}</h1>
          <p class="hint">${esc(assignment.classroom)} · already submitted</p>
        </div>
      </div>

      ${released ? `
        <div class="score-hero" style="margin-bottom:var(--s-5)">
          <div class="center-text">
            <div style="font-size:var(--text-3xl);font-weight:800;line-height:1"
                 class="${pct >= 50 ? 'success-text' : 'danger-text'}">${pct}%</div>
            <div class="t-2xs subtle">${submission.score} of ${submission.maxScore}</div>
          </div>
          <div>
            <div class="fw-bold t-lg">Marked and approved by your teacher</div>
            <p class="t-sm muted">Every comment below was checked by a person before you saw it.</p>
          </div>
        </div>

        <div class="stack gap-3">
          ${(submission.answers || []).map((a, i) => `
            <article class="review-q ${a.mark === a.maxMark ? '' : 'wrong'}">
              <div class="row between" style="margin-bottom:var(--s-2)">
                <span class="t-xs fw-bold subtle">QUESTION ${i + 1}</span>
                <span class="chip ${a.mark === a.maxMark ? 'chip-brand' : a.mark === 0 ? 'chip-danger' : 'chip-amber'}">
                  ${a.mark}/${a.maxMark}
                </span>
              </div>
              <div class="fw-medium" style="margin-bottom:var(--s-2)">${esc(a.prompt || '')}</div>
              <div class="given" style="margin-bottom:var(--s-2)">${esc(a.given || '')}</div>
              ${a.comment ? `<div class="alert alert-info t-sm"><span>${esc(a.comment)}</span></div>` : ''}
            </article>`).join('')}
        </div>` : `
        <div class="alert alert-info">
          ${icon('clock', 18)}
          <span>
            Your teacher has not released the marks yet. Nothing is wrong — a
            person checks every script before you see a score.
          </span>
        </div>`}
    </div>`;

  hydrateIcons();
}

/* --- The paper ----------------------------------------------------------- */

function renderPaper(page, paper, me) {
  const { assignment } = paper;
  const saved = store.get(draftKey(assignment.id), {}) || {};
  const answers = { ...saved };

  const save = () => store.set(draftKey(assignment.id), answers);

  const answeredCount = () =>
    assignment.questions.filter((q) => {
      const a = answers[q.id];
      return q.kind === 'written' ? (a || '').trim().length > 0 : a !== undefined && a !== null;
    }).length;

  page.innerHTML = `
    <a class="row gap-2 t-sm fw-semibold muted" href="${url('student/assignments.html')}"
       style="margin-bottom:var(--s-4)">${icon('arrowL', 16)} All classwork</a>

    <div class="exam-bar">
      <span class="itile itile-sm">${assignment.kind === 'test' ? '📊' : '✍️'}</span>
      <div class="grow" style="min-width:0">
        <div class="fw-bold t-sm clamp-1">${esc(assignment.title)}</div>
        <div class="hint" id="progressText"></div>
      </div>
      <span class="chip chip-outline">${assignment.maxScore} marks</span>
      <button class="btn btn-primary btn-sm" id="submitBtn">Submit</button>
    </div>

    <div class="alert alert-info" style="margin-bottom:var(--s-5)">
      ${icon('info', 18)}
      <span>
        Everything you type saves on this phone as you go. If the app closes, or
        there is no network when you submit, nothing is lost.
      </span>
    </div>

    <div class="stack gap-5" id="questions">
      ${assignment.questions.map((q, i) => questionHtml(q, i, answers[q.id])).join('')}
    </div>

    <div class="card" style="margin-top:var(--s-6)">
      <div class="row between wrap gap-3">
        <div>
          <div class="fw-bold t-sm" id="summaryText"></div>
          <p class="hint">Your teacher reviews every mark before you see it.</p>
        </div>
        <button class="btn btn-primary btn-lg" id="submitBtn2">Submit my work</button>
      </div>
    </div>`;

  const paintProgress = () => {
    const done = answeredCount();
    const total = assignment.questions.length;
    $('#progressText').textContent = `${done} of ${total} answered`;
    $('#summaryText').textContent = done === total
      ? 'All questions answered.'
      : `${total - done} question${total - done === 1 ? '' : 's'} still blank.`;
  };

  /* Objective options */
  $$('#questions [data-pick]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const qid = Number(btn.dataset.for);
      answers[qid] = Number(btn.dataset.pick);
      save();

      $$(`[data-options="${qid}"] .choice`).forEach((c) =>
        c.classList.toggle('is-active', c === btn));

      paintProgress();
    });
  });

  /* Written answers */
  $$('#questions [data-written]').forEach((box) => {
    box.addEventListener('input', () => {
      answers[Number(box.dataset.written)] = box.value;
      save();
      paintProgress();
    });
  });

  const submit = async () => {
    const total = assignment.questions.length;
    const blank = total - answeredCount();

    if (blank > 0) {
      const go = await confirmDialog({
        title: `Submit with ${blank} unanswered?`,
        message: 'Your teacher sees a blank as zero. Answering something always beats leaving it empty.',
        confirmText: 'Submit anyway',
        cancelText: 'Go back',
      });
      if (!go) return;
    }

    const payload = {
      clientUuid: uuid(),
      assignmentId: assignment.id,
      answers: assignment.questions.map((q) => ({
        questionId: q.id,
        chosen: q.kind === 'written' ? null : (answers[q.id] ?? null),
        text: q.kind === 'written' ? (answers[q.id] || '') : null,
      })),
    };

    $('#submitBtn').disabled = true;
    $('#submitBtn2').disabled = true;
    $('#submitBtn2').textContent = 'Submitting…';

    const res = await api.pushSubmission(payload);

    if (res.ok) {
      store.remove(draftKey(assignment.id));
      toast('Submitted. Your teacher will mark it shortly.', 'success', 3600);
      return setTimeout(() => goReplace('student/assignments.html'), 700);
    }

    if (res.offline) {
      /* The whole point of the queue: a submission made in a dead spot is
         still a submission. It sends itself when the network returns. */
      queueSubmission(payload);
      store.remove(draftKey(assignment.id));
      toast('No network — saved. It will send itself when you are online.', 'warn', 4200);
      return setTimeout(() => goReplace('student/assignments.html'), 900);
    }

    $('#submitBtn').disabled = false;
    $('#submitBtn2').disabled = false;
    $('#submitBtn2').textContent = 'Submit my work';
    toast(res.data?.message || 'That did not send. Try again in a moment.', 'danger', 4000);
  };

  $('#submitBtn').addEventListener('click', submit);
  $('#submitBtn2').addEventListener('click', submit);

  paintProgress();
  hydrateIcons();
}

function questionHtml(q, index, current) {
  return `
    <section class="card card-pad-lg" data-question="${q.id}">
      <div class="row between gap-3 wrap" style="margin-bottom:var(--s-4)">
        <span class="t-xs fw-bold subtle">
          QUESTION ${index + 1} · ${q.maxMark} ${q.maxMark === 1 ? 'mark' : 'marks'} ·
          ${q.kind === 'written' ? 'written answer' : 'choose one'}
        </span>
      </div>

      <div class="q-text" style="margin-bottom:var(--s-5)">${esc(q.prompt)}</div>

      ${q.kind === 'written' ? `
        <textarea class="textarea" data-written="${q.id}" rows="5"
                  placeholder="Write your answer, and show your working where there is any."
        >${esc(current || '')}</textarea>
        <p class="hint" style="margin-top:var(--s-2)">
          Show every step. Method earns marks even when the final answer is wrong.
        </p>` : `
        <div class="stack gap-3" data-options="${q.id}">
          ${(q.options || []).map((option, k) => `
            <button class="choice ${current === k ? 'is-active' : ''}"
                    data-pick="${k}" data-for="${q.id}">
              <span class="choice-key">${'ABCD'[k] || k + 1}</span>
              <span class="grow">${esc(option)}</span>
            </button>`).join('')}
        </div>`}
    </section>`;
}

/** RFC-4122-shaped id; crypto.randomUUID is missing on some older Androids. */
function uuid() {
  if (crypto.randomUUID) return crypto.randomUUID();

  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}
