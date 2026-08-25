/* ==========================================================================
   student-assignments.js — the classwork a teacher actually set.

   This is the screen that closes the loop: teacher sets work here, the student
   answers it, the submission lands in the marking queue, and the approved
   result comes back to this same screen.

   Assignments are pulled when there is network and cached on the device, so
   the list still renders offline — a student can read what is due even when
   they cannot submit it yet.
   ========================================================================== */

import { boot } from '../core/boot.js';
import { hydrateIcons, icon } from '../core/icons.js';
import { $, esc, toast } from '../core/ui.js';
import { url } from '../core/paths.js';
import { requireAuth } from '../core/auth.js';
import { mountShell } from '../components/shell.js';
import * as store from '../core/store.js';
import * as api from '../core/api.js';

boot();
const me = requireAuth('student');

const CACHE = 'assignments';
const RESULTS = 'submissions';

function cached() { return store.get(CACHE, []) || []; }
function cachedResults() { return store.get(RESULTS, []) || []; }

function render() {
  const page = mountShell({
    user: me,
    active: 'assignments',
    title: 'Classwork',
    subtitle: 'Set by your teacher',
  });

  page.innerHTML = '<div id="body"></div>';
  paint({ loading: api.isLinked() && navigator.onLine });
  refresh();
}

/** Pull the latest list, then repaint. Silent when there is no network. */
async function refresh() {
  if (!api.isLinked() || !navigator.onLine) return;

  const [list, results] = await Promise.all([api.assignments(), api.submissions()]);

  if (list.ok && list.data) store.set(CACHE, list.data.assignments || []);
  if (results.ok && results.data) store.set(RESULTS, results.data.submissions || []);

  paint({ loading: false });
}

function paint({ loading }) {
  const items = cached();
  const results = cachedResults();
  const resultFor = (id) => results.find((r) => r.assignmentId === id) || null;

  const todo = items.filter((a) => !a.submitted);
  const done = items.filter((a) => a.submitted);

  $('#body').innerHTML = `
    <div class="page-head">
      <div>
        <h1>Classwork</h1>
        <p class="sub">
          ${items.length
            ? `${todo.length} to do · ${done.length} submitted`
            : 'Work your teacher sets appears here'}
        </p>
      </div>
      <button class="btn btn-secondary" id="refreshBtn">
        ${icon('refresh', 16)} Refresh
      </button>
    </div>

    ${!api.isLinked() ? `
      <div class="alert alert-warn" style="margin-bottom:var(--s-5)">
        ${icon('info', 18)}
        <span>
          This device is not linked to a school account yet, so no teacher can
          send you work. Log in again while you have network to link it.
        </span>
      </div>` : ''}

    ${!navigator.onLine ? `
      <div class="alert alert-info" style="margin-bottom:var(--s-5)">
        ${icon('wifiOff', 18)}
        <span>
          You are offline. This is the last list your phone downloaded — you can
          read it, and answers you write will send when network returns.
        </span>
      </div>` : ''}

    ${loading && !items.length ? `
      <div class="card"><div class="skel" style="height:72px"></div></div>` : ''}

    ${todo.length ? `
      <section style="margin-bottom:var(--s-8)">
        <div class="section-head">
          <h2>To do</h2>
          <span class="chip chip-amber">${todo.length}</span>
        </div>
        <div class="stack gap-3">
          ${todo.map(cardFor).join('')}
        </div>
      </section>` : ''}

    ${done.length ? `
      <section>
        <div class="section-head">
          <h2>Submitted</h2>
          <span class="chip chip-outline">${done.length}</span>
        </div>
        <div class="stack gap-3">
          ${done.map((a) => submittedCard(a, resultFor(a.id))).join('')}
        </div>
      </section>` : ''}

    ${!items.length && !loading ? `
      <div class="empty card">
        <div class="itile">📋</div>
        <h3>No classwork yet</h3>
        <p class="t-sm">
          When your teacher sets work for your class it lands here. In the
          meantime, your scheme of work and past questions are always available.
        </p>
        <a class="btn btn-primary" href="${url('student/subjects.html')}">Open my subjects</a>
      </div>` : ''}`;

  $('#refreshBtn').addEventListener('click', async () => {
    if (!navigator.onLine) return toast('No network right now — try again later.', 'warn');
    toast('Checking for new work…');
    await refresh();
  });

  hydrateIcons();
}

function dueLabel(a) {
  if (!a.dueOn) return 'No due date';

  const due = new Date(a.dueOn + 'T23:59:59');
  const days = Math.ceil((due - Date.now()) / 86400000);

  if (days < 0) return `Overdue by ${Math.abs(days)} day${Math.abs(days) === 1 ? '' : 's'}`;
  if (days === 0) return 'Due today';
  if (days === 1) return 'Due tomorrow';

  return `Due in ${days} days`;
}

function cardFor(a) {
  const overdue = a.dueOn && new Date(a.dueOn + 'T23:59:59') < Date.now();

  return `
    <a class="card card-link" href="${url(`student/exam.html?assignment=${a.id}`)}">
      <div class="row gap-4 items-start">
        <span class="itile itile-lg ${a.kind === 'test' ? 'itile-amber' : ''}">
          ${a.kind === 'test' ? '📊' : '✍️'}
        </span>
        <div class="grow" style="min-width:0">
          <div class="row gap-2 wrap" style="margin-bottom:4px">
            <span class="chip ${a.kind === 'test' ? 'chip-amber' : 'chip-brand'}">
              ${a.kind === 'test' ? 'Test' : 'Classwork'}
            </span>
            <span class="chip ${overdue ? 'chip-danger' : 'chip-outline'}">${esc(dueLabel(a))}</span>
          </div>
          <div class="fw-bold clamp-2">${esc(a.title)}</div>
          <div class="hint">${esc(a.classroom)} · ${a.questions?.length || 0} questions</div>
        </div>
        <span class="btn btn-primary btn-sm shrink-0">Start</span>
      </div>
    </a>`;
}

function submittedCard(a, result) {
  const released = result?.released;
  const pct = released && result.maxScore
    ? Math.round((result.score / result.maxScore) * 100)
    : null;

  return `
    <div class="card">
      <div class="row gap-4 items-start">
        <span class="itile itile-lg ${released ? '' : 'itile-sky'}">
          ${released ? '✅' : '⏳'}
        </span>
        <div class="grow" style="min-width:0">
          <div class="fw-bold clamp-2">${esc(a.title)}</div>
          <div class="hint">${esc(a.classroom)}</div>

          ${released ? `
            <div class="row gap-3 items-center" style="margin-top:var(--s-3)">
              <span class="chip ${pct >= 50 ? 'chip-brand' : 'chip-danger'}">
                ${result.score}/${result.maxScore} · ${pct}%
              </span>
            </div>
            <details style="margin-top:var(--s-3)">
              <summary class="t-sm fw-semibold brand" style="cursor:pointer">
                See what your teacher said
              </summary>
              <div class="stack gap-3" style="margin-top:var(--s-3)">
                ${(result.answers || []).map((ans) => `
                  <div class="script-q">
                    <div class="t-2xs fw-bold subtle" style="margin-bottom:6px">
                      ${ans.mark}/${ans.maxMark} marks
                    </div>
                    <div class="t-sm fw-medium" style="margin-bottom:6px">${esc(ans.prompt || '')}</div>
                    <div class="given" style="margin-bottom:8px">${esc(ans.given || '')}</div>
                    ${ans.comment
                      ? `<div class="alert ${ans.mark === ans.maxMark ? 'alert-success' : ans.mark === 0 ? 'alert-danger' : 'alert-warn'}">
                           <span>${esc(ans.comment)}</span>
                         </div>`
                      : ''}
                  </div>`).join('')}
              </div>
            </details>` : `
            <p class="t-sm muted" style="margin-top:var(--s-3)">
              Submitted. Your teacher is reviewing it — your score appears here
              once they approve it.
            </p>`}
        </div>
      </div>
    </div>`;
}

/* Start only once every module-level constant above is initialised. */
if (me) render();
