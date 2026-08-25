/* ==========================================================================
   teacher-marking.js — the batch marking queue.

   The claim this screen has to earn: 30 scripts in 5 minutes. So the AI marks
   first and the teacher adjudicates. Anything the AI is unsure about is pushed
   up and highlighted; everything else is one keystroke away from approved.
   ========================================================================== */

import { boot } from '../core/boot.js';
import { hydrateIcons, icon } from '../core/icons.js';
import { $, $$, esc, initials, toast, confirmDialog, clock } from '../core/ui.js';
import { url, param } from '../core/paths.js';
import { requireAuth } from '../core/auth.js';
import { mountShell } from '../components/shell.js';
import {
  ASSIGNMENTS, CLASSES, submissionsFor, approveScript, approveMany,
  markedScore, resetMarking, paperMax,
} from '../data/classroom.js';

boot();
const me = requireAuth('teacher');
const CONFIDENT = 80;   // below this, the AI asks a human to look

function render() {
  const assignment = ASSIGNMENTS.find((a) => a.id === param('assignment'))
    || ASSIGNMENTS.find((a) => submissionsFor(a.id).some((s) => s.status !== 'approved'))
    || ASSIGNMENTS[0];
  const cls = CLASSES.find((c) => c.id === assignment.classId);

  let scripts = submissionsFor(assignment.id);
  /* Sort: pending first, and inside that the ones the AI flagged. */
  scripts.sort((a, b) => {
    if (a.status !== b.status) return a.status === 'approved' ? 1 : -1;
    return Number(b.lowConfidence) - Number(a.lowConfidence);
  });

  let index = Math.max(0, scripts.findIndex((s) => s.status !== 'approved'));
  /* Marks the teacher has overridden, keyed script → question number. */
  const overrides = {};
  let elapsed = 0;
  let approvedThisSession = 0;

  const page = mountShell({
    user: me,
    active: 'marking',
    title: 'Marking queue',
    subtitle: `${esc(assignment.title)} · ${esc(cls ? cls.name : '')}`,
  });

  page.innerHTML = `
    <div class="page-head">
      <div>
        <h1>Marking queue</h1>
        <p class="sub">${esc(assignment.title)} · ${esc(cls ? cls.name : '')} · ${paperMax()} marks</p>
      </div>
      <div class="row gap-3 wrap">
        <select class="select" id="assignmentSel" style="min-height:44px;width:auto">
          ${ASSIGNMENTS.map((a) => `
            <option value="${a.id}" ${a.id === assignment.id ? 'selected' : ''}>
              ${esc(a.title)} — ${esc((CLASSES.find((c) => c.id === a.classId) || {}).name || '')}
            </option>`).join('')}
        </select>
        <button class="btn btn-secondary" id="bulkBtn">${icon('zap', 16)} Approve confident</button>
      </div>
    </div>

    <!-- Session meter: the proof of the five-minute claim -->
    <section class="card card-pad-sm" style="margin-bottom:var(--s-5)">
      <div class="row between wrap gap-4">
        <div class="row gap-5 wrap">
          <div>
            <div class="t-2xs subtle upper">Approved this session</div>
            <div class="fw-black t-xl tnum" id="sessionCount">0</div>
          </div>
          <div>
            <div class="t-2xs subtle upper">Time spent</div>
            <div class="fw-black t-xl tnum mono" id="sessionTime">0:00</div>
          </div>
          <div>
            <div class="t-2xs subtle upper">Pace</div>
            <div class="fw-black t-xl tnum" id="sessionPace">—</div>
          </div>
        </div>
        <div class="grow" style="min-width:220px;max-width:420px">
          <div class="row between t-xs" style="margin-bottom:6px">
            <span class="muted">Queue progress</span>
            <span class="fw-bold" id="queueLabel"></span>
          </div>
          <div class="bar"><i id="queueBar" style="width:0%"></i></div>
        </div>
      </div>
    </section>

    <div class="mark-grid">

      <!-- Queue -->
      <aside class="card side-card">
        <div class="card-header">
          <span class="card-title">Scripts</span>
          <span class="chip chip-outline" id="pendingChip"></span>
        </div>
        <div class="queue stack gap-1" id="queue"></div>
        <button class="btn btn-ghost btn-block btn-sm danger-text" id="resetBtn"
                style="margin-top:var(--s-3)">Reset demo marking</button>
      </aside>

      <!-- Script -->
      <section class="card card-pad-lg" id="script"></section>
    </div>`;

  /* --- Queue list ---------------------------------------------------------- */

  function paintQueue() {
    const pending = scripts.filter((s) => s.status !== 'approved').length;
    $('#pendingChip').textContent = `${pending} pending`;
    $('#queueLabel').textContent = `${scripts.length - pending}/${scripts.length} approved`;
    $('#queueBar').style.width = `${Math.round(((scripts.length - pending) / scripts.length) * 100)}%`;

    $('#queue').innerHTML = scripts.map((s, i) => `
      <button class="queue-row ${i === index ? 'is-current' : ''} ${s.status === 'approved' ? 'is-approved' : ''}"
              data-open="${i}">
        <span class="avatar avatar-sm">${esc(initials(s.student.name))}</span>
        <span class="grow" style="min-width:0">
          <span class="t-sm fw-semibold clamp-1" style="display:block">${esc(s.student.name)}</span>
          <span class="hint">
            ${s.status === 'approved' ? '✓ Approved' : s.lowConfidence ? '⚠ Needs your eye' : 'AI confident'}
          </span>
        </span>
        <span class="score ${scorePct(s) >= 50 ? 'success-text' : 'danger-text'}">
          ${finalScore(s)}/${s.maxScore}
        </span>
      </button>`).join('');

    $$('#queue [data-open]').forEach((b) => b.addEventListener('click', () => {
      index = Number(b.dataset.open);
      paintQueue(); paintScript();
    }));
  }

  const overrideFor = (s, n) => overrides[s.id]?.[n];

  function finalScore(s) {
    const stored = markedScore(s.id);
    if (stored !== undefined && !overrides[s.id]) return stored;
    return s.answers.reduce((a, x) => a + (overrideFor(s, x.n) ?? x.aiMark), 0);
  }
  const scorePct = (s) => Math.round((finalScore(s) / s.maxScore) * 100);

  /* --- Open script --------------------------------------------------------- */

  function paintScript() {
    const s = scripts[index];
    if (!s) return;
    const flagged = s.answers.filter((a) => a.confidence < CONFIDENT).length;
    const pct = scorePct(s);

    $('#script').innerHTML = `
      <div class="row between wrap gap-4" style="margin-bottom:var(--s-5)">
        <div class="row gap-3">
          <span class="avatar avatar-lg">${esc(initials(s.student.name))}</span>
          <div>
            <div class="fw-bold t-lg">${esc(s.student.name)}</div>
            <div class="hint">
              Submitted ${esc(s.submittedAt)} · ${esc(s.student.classId.toUpperCase())}
              · term average ${s.student.avg}%
            </div>
            <div class="row gap-2" style="margin-top:var(--s-2)">
              ${s.status === 'approved'
                ? '<span class="chip chip-brand">✓ Approved</span>'
                : flagged
                  ? `<span class="chip chip-amber">⚠ ${flagged} answer${flagged === 1 ? '' : 's'} to check</span>`
                  : '<span class="chip chip-brand">AI confident on all answers</span>'}
            </div>
          </div>
        </div>

        <div class="center-text">
          <div class="t-2xs subtle upper">Total</div>
          <div style="font-size:var(--text-3xl);font-weight:800;line-height:1"
               class="${pct >= 50 ? 'success-text' : 'danger-text'}" id="totalScore">
            ${finalScore(s)}<span class="subtle" style="font-size:var(--text-lg)">/${s.maxScore}</span>
          </div>
          <div class="t-xs subtle tnum">${pct}%</div>
        </div>
      </div>

      <div class="alert alert-info" style="margin-bottom:var(--s-5)">
        ${icon('sparkle', 18)}
        <span>
          The AI has marked every answer and written the comment the student will
          see. Change any mark with the +/− buttons; anything you change is what
          the student receives.
        </span>
      </div>

      ${s.answers.map((a) => {
        const mark = overrideFor(s, a.n) ?? a.aiMark;
        const changed = overrideFor(s, a.n) !== undefined;
        const conf = a.confidence >= CONFIDENT ? '' : a.confidence >= 60 ? 'mid' : 'low';
        return `
        <article class="script-q ${a.confidence < CONFIDENT ? 'needs-eye' : ''}">
          <div class="row between gap-3 wrap" style="margin-bottom:var(--s-3)">
            <span class="t-xs fw-bold subtle">QUESTION ${a.n} · ${a.max} mark${a.max === 1 ? '' : 's'} · ${a.type === 'short' ? 'written answer' : 'objective'}</span>
            <span class="conf ${conf}">
              ${a.confidence >= CONFIDENT ? '✓' : '⚠'} AI ${a.confidence}% sure
            </span>
          </div>

          <div class="fw-medium t-sm" style="margin-bottom:var(--s-3)">${esc(a.q)}</div>

          <div class="t-2xs subtle upper" style="margin-bottom:6px">Student wrote</div>
          <div class="given" style="margin-bottom:var(--s-3)">${esc(a.given)}</div>

          <div class="alert ${mark === a.max ? 'alert-success' : mark === 0 ? 'alert-danger' : 'alert-warn'}"
               style="margin-bottom:var(--s-3)">
            <span><b>AI comment:</b> ${esc(a.comment)}</span>
          </div>

          <div class="row between wrap gap-3">
            <span class="t-2xs subtle">Expected: ${esc(a.expected)}</span>
            <div class="mark-stepper">
              ${changed ? '<span class="chip chip-amber">Adjusted</span>' : ''}
              <button class="btn btn-secondary btn-icon btn-sm" data-step="-1" data-n="${a.n}"
                      aria-label="Reduce mark">${icon('minus', 16)}</button>
              <span class="val ${mark === a.max ? 'success-text' : mark === 0 ? 'danger-text' : ''}"
                    data-val="${a.n}">${mark}/${a.max}</span>
              <button class="btn btn-secondary btn-icon btn-sm" data-step="1" data-n="${a.n}"
                      aria-label="Increase mark">${icon('plus', 16)}</button>
            </div>
          </div>
        </article>`;
      }).join('')}

      <div class="mark-actions">
        <button class="btn btn-secondary" id="prevScript">${icon('arrowL', 16)} Previous</button>
        <button class="btn btn-primary grow" id="approveBtn">
          ${s.status === 'approved' ? 'Approved — next script' : `Approve ${finalScore(s)}/${s.maxScore} and go next`}
          ${icon('arrowR', 16)}
        </button>
        <span class="t-2xs subtle hide-mobile">Press <span class="kbd">A</span> to approve · <span class="kbd">↑↓</span> to move</span>
      </div>`;

    /* Mark steppers */
    $$('#script [data-step]').forEach((b) => b.addEventListener('click', () => {
      const n = Number(b.dataset.n);
      const ans = s.answers.find((x) => x.n === n);
      const current = overrideFor(s, n) ?? ans.aiMark;
      const next = Math.max(0, Math.min(ans.max, current + Number(b.dataset.step)));
      overrides[s.id] = { ...(overrides[s.id] || {}), [n]: next };
      paintScript();
      paintQueue();
    }));

    $('#approveBtn').addEventListener('click', approveCurrent);
    $('#prevScript').addEventListener('click', () => {
      if (index > 0) { index -= 1; paintQueue(); paintScript(); }
    });

    hydrateIcons();
  }

  /* --- Actions ------------------------------------------------------------- */

  function approveCurrent() {
    const s = scripts[index];
    if (!s) return;
    if (s.status !== 'approved') {
      approveScript(s.id, finalScore(s));
      s.status = 'approved';
      approvedThisSession += 1;
      paintSession();
    }
    const nextPending = scripts.findIndex((x, i) => i > index && x.status !== 'approved');
    if (nextPending >= 0) {
      index = nextPending;
    } else if (scripts.some((x) => x.status !== 'approved')) {
      index = scripts.findIndex((x) => x.status !== 'approved');
    } else {
      toast('Every script approved. Marking done 🎉', 'success', 4000);
    }
    paintQueue();
    paintScript();
  }

  $('#bulkBtn').addEventListener('click', async () => {
    const confident = scripts.filter((s) => s.status !== 'approved' && !s.lowConfidence);
    const flagged = scripts.filter((s) => s.status !== 'approved' && s.lowConfidence);
    if (!confident.length) return toast('Nothing left that the AI is fully confident about.');

    const yes = await confirmDialog({
      title: `Approve ${confident.length} script${confident.length === 1 ? '' : 's'}?`,
      message: `These are the ones the AI marked with high confidence on every answer.`
        + (flagged.length
          ? ` The ${flagged.length} script${flagged.length === 1 ? '' : 's'} it flagged will stay in the queue for you.`
          : ''),
      confirmText: 'Approve them',
    });
    if (!yes) return;

    approveMany(confident.map((s) => s.id));
    confident.forEach((s) => { s.status = 'approved'; });
    approvedThisSession += confident.length;
    paintSession();
    toast(`${confident.length} scripts approved`, 'success');

    const stillPending = scripts.findIndex((s) => s.status !== 'approved');
    index = stillPending >= 0 ? stillPending : 0;
    paintQueue();
    paintScript();
  });

  $('#resetBtn').addEventListener('click', async () => {
    const yes = await confirmDialog({
      title: 'Reset the demo marking?',
      message: 'Every script goes back to pending so you can run the demo again.',
      confirmText: 'Reset',
      danger: true,
    });
    if (!yes) return;
    resetMarking();
    window.location.reload();
  });

  $('#assignmentSel').addEventListener('change', (e) => {
    window.location.href = url(`teacher/marking.html?assignment=${e.target.value}`);
  });

  /* --- Session meter ------------------------------------------------------- */

  function paintSession() {
    $('#sessionCount').textContent = String(approvedThisSession);
    $('#sessionTime').textContent = clock(elapsed);
    $('#sessionPace').textContent = approvedThisSession
      ? `${Math.round(elapsed / approvedThisSession)}s each`
      : '—';
  }
  setInterval(() => { elapsed += 1; paintSession(); }, 1000);

  /* --- Keyboard ------------------------------------------------------------ */

  document.addEventListener('keydown', (e) => {
    /* Ignore shortcuts while typing. e.target can be the Document itself
       when nothing is focused, and Document has no .matches — hence the
       optional call rather than a bare e.target.matches(). */
    if (e.target?.matches?.('input, textarea, select, [contenteditable]')) return;
    if (e.key.toLowerCase() === 'a') { e.preventDefault(); approveCurrent(); }
    if (e.key === 'ArrowDown' && index < scripts.length - 1) {
      index += 1; paintQueue(); paintScript();
    }
    if (e.key === 'ArrowUp' && index > 0) {
      index -= 1; paintQueue(); paintScript();
    }
  });

  paintQueue();
  paintScript();
  paintSession();
  hydrateIcons();
}

/* Start only once every module-level constant above is initialised.
   Calling render() higher up would hit the temporal dead zone on any
   const declared below it — a hoisted function can be called early,
   the constants it closes over cannot. */
if (me) render();
