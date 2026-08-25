/* teacher-dashboard.js — what a teacher sees before first period. */

import { boot } from '../core/boot.js';
import { hydrateIcons, icon } from '../core/icons.js';
import { $, esc, humanMins, initials } from '../core/ui.js';
import { url } from '../core/paths.js';
import { requireAuth } from '../core/auth.js';
import { mountShell } from '../components/shell.js';
import { byId } from '../data/subjects.js';
import {
  CLASSES, ASSIGNMENTS, studentsOf, classStats, topicHealth, submissionsFor,
} from '../data/classroom.js';

boot();
const me = requireAuth('teacher');
function render() {
  const subject = byId(me.subject || 'maths');
  const myClasses = CLASSES;

  /* Pending work, counted from live submission state rather than a fixture. */
  const queues = ASSIGNMENTS.map((a) => {
    const subs = submissionsFor(a.id);
    const pending = subs.filter((s) => s.status !== 'approved').length;
    return { assignment: a, pending, total: subs.length };
  }).filter((q) => q.pending > 0);

  const totalPending = queues.reduce((a, q) => a + q.pending, 0);
  const allStats = myClasses.map((c) => ({ cls: c, ...classStats(c.id) }));
  const students = allStats.reduce((a, s) => a + s.size, 0);
  const avg = Math.round(allStats.reduce((a, s) => a + s.avg, 0) / allStats.length);
  const atRisk = allStats.reduce((a, s) => a + s.atRisk, 0);

  const health = topicHealth('ss2a');
  const worst = health[0];
  const risky = studentsOf('ss2a').filter((s) => s.atRisk).slice(0, 5);

  const page = mountShell({
    user: me,
    active: 'dashboard',
    title: `Good day, ${esc(me.name.split(' ').slice(-1)[0])}`,
    subtitle: `${esc(subject ? subject.name : 'Mathematics')}${me.school ? ` · ${esc(me.school)}` : ''}`,
  });

  page.innerHTML = `
    <div class="page-head">
      <div>
        <h1>Good day, ${esc(me.name)}</h1>
        <p class="sub">${myClasses.length} classes · ${students} students · ${esc(subject ? subject.name : 'Mathematics')}</p>
      </div>
      <div class="row gap-3">
        <a class="btn btn-secondary" href="${url('teacher/assignments.html')}">${icon('plus', 16)} New assignment</a>
        <a class="btn btn-primary" href="${url('teacher/marking.html')}">${icon('edit', 16)} Start marking</a>
      </div>
    </div>

    ${totalPending ? `
    <section class="continue" style="margin-bottom:var(--s-6)">
      <div>
        <div class="eye">Waiting for you</div>
        <h2>${totalPending} script${totalPending === 1 ? '' : 's'} already marked by the AI</h2>
        <div class="meta">
          Every answer has a suggested mark and a comment. Your job is to glance,
          adjust what the AI flagged, and approve. Most teachers clear a class in
          under five minutes.
        </div>
        <a class="btn btn-amber" style="margin-top:var(--s-5)" href="${url('teacher/marking.html')}">
          Review and approve ${icon('arrowR', 16)}
        </a>
      </div>
      <div class="center-text">
        <div style="font-size:64px;font-weight:800;line-height:1">${totalPending}</div>
        <div class="t-sm" style="opacity:.85">scripts pending</div>
      </div>
    </section>` : `
    <div class="alert alert-success" style="margin-bottom:var(--s-6)">
      ${icon('check', 18)}
      <span>Marking queue is empty. Every script you have received is approved.</span>
    </div>`}

    <section class="stat-grid" style="margin-bottom:var(--s-6)">
      <div class="stat-card">
        <div class="k">Classes</div>
        <div class="v">${myClasses.length}</div>
        <div class="d">${students} students in total</div>
      </div>
      <div class="stat-card">
        <div class="k">Class average</div>
        <div class="v">${avg}%</div>
        <div class="bar bar-sm" style="margin-top:var(--s-2)">
          <i class="${avg >= 60 ? '' : avg >= 45 ? 'amber' : 'danger'}" style="width:${avg}%"></i>
        </div>
        <div class="d">Across all assessments</div>
      </div>
      <div class="stat-card">
        <div class="k">Need attention</div>
        <div class="v danger-text">${atRisk}</div>
        <div class="d">Students below 45%</div>
      </div>
      <div class="stat-card">
        <div class="k">Study time</div>
        <div class="v">${humanMins(allStats.reduce((a, s) => a + s.minutesWeek, 0))}</div>
        <div class="d">Logged by your classes this week</div>
      </div>
    </section>

    <div class="grid" style="grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);gap:var(--s-6)" id="tdCols">

      <div class="stack gap-6">

        <!-- Marking queue -->
        <section class="card">
          <div class="card-header">
            <span class="card-title">Marking queue</span>
            <a class="t-xs fw-semibold" href="${url('teacher/marking.html')}">Open queue</a>
          </div>
          ${queues.length ? queues.map(({ assignment, pending, total }) => {
            const cls = CLASSES.find((c) => c.id === assignment.classId);
            return `
            <div class="list-row">
              <span class="itile itile-sm ${assignment.type === 'test' ? 'itile-amber' : ''}">
                ${assignment.type === 'test' ? '📊' : '✍️'}
              </span>
              <div class="grow" style="min-width:0">
                <div class="t-sm fw-semibold clamp-1">${esc(assignment.title)}</div>
                <div class="hint">${esc(cls ? cls.name : '')} · ${total - pending}/${total} approved · due ${fmt(assignment.due)}</div>
              </div>
              <span class="chip chip-danger">${pending} left</span>
              <a class="btn btn-secondary btn-sm hide-mobile"
                 href="${url(`teacher/marking.html?assignment=${assignment.id}`)}">Mark</a>
            </div>`;
          }).join('') : '<div class="empty"><div class="itile">✅</div><p class="t-sm">Nothing waiting. Enjoy your break.</p></div>'}
        </section>

        <!-- Class health -->
        <section class="card">
          <div class="card-header">
            <div>
              <span class="card-title">Where SS2A is struggling</span>
              <div class="hint">Class average per topic, weakest first</div>
            </div>
            <a class="t-xs fw-semibold" href="${url('teacher/reports.html')}">Full report</a>
          </div>

          ${worst.avg < 55 ? `
            <div class="alert alert-warn" style="margin-bottom:var(--s-5)">
              ${icon('alert', 18)}
              <span>
                <b>${worst.failing} of ${classStats('ss2a').size} students</b> are below 50% on
                <b>${esc(worst.topic)}</b>. This is a re-teach, not a revision — the AI can
                rebuild that week's lesson for the whole class.
              </span>
            </div>` : ''}

          <div class="stack gap-4">
            ${health.map((t) => {
              const cls = t.avg >= 70 ? '' : t.avg >= 50 ? 'amber' : 'danger';
              return `
              <div>
                <div class="row between t-sm" style="margin-bottom:6px">
                  <span class="fw-medium">${esc(t.topic)}</span>
                  <span class="subtle tnum">${t.avg}% avg · ${t.failing} failing</span>
                </div>
                <div class="bar"><i class="${cls}" style="width:${t.avg}%"></i></div>
              </div>`;
            }).join('')}
          </div>
        </section>

        <!-- Classes -->
        <section class="card">
          <div class="card-header">
            <span class="card-title">My classes</span>
            <a class="t-xs fw-semibold" href="${url('teacher/classes.html')}">Manage</a>
          </div>
          <div class="grid grid-3">
            ${allStats.map(({ cls, size, avg: a, atRisk: risk, activeToday }) => `
              <a class="qa" href="${url(`teacher/classes.html?class=${cls.id}`)}">
                <span class="row between">
                  <span class="itile itile-sm">${cls.name.slice(0, 3)}</span>
                  <span class="chip ${a >= 60 ? 'chip-brand' : a >= 45 ? 'chip-amber' : 'chip-danger'}">${a}%</span>
                </span>
                <span class="t">${esc(cls.name)}</span>
                <span class="s">${size} students · ${activeToday} active today</span>
                <span class="s ${risk ? 'danger-text' : ''}">${risk} need attention</span>
              </a>`).join('')}
          </div>
        </section>
      </div>

      <div class="stack gap-6">

        <!-- At risk -->
        <section class="card">
          <div class="card-header">
            <span class="card-title">Students to watch</span>
            <span class="chip chip-danger">${risky.length}</span>
          </div>
          ${risky.map((s) => `
            <div class="list-row">
              <span class="avatar avatar-sm">${esc(initials(s.name))}</span>
              <div class="grow" style="min-width:0">
                <div class="t-sm fw-semibold clamp-1">${esc(s.name)}</div>
                <div class="hint">Last active ${esc(s.lastActive)} · ${s.streak} day streak</div>
              </div>
              <span class="chip chip-danger">${s.avg}%</span>
            </div>`).join('')}
          <a class="btn btn-secondary btn-block btn-sm" style="margin-top:var(--s-4)"
             href="${url('teacher/classes.html?class=ss2a')}">See full roster</a>
        </section>

        <!-- AI helper -->
        <section class="card card-flat" style="background:var(--surface-brand);border-color:var(--border-brand)">
          <div class="row gap-3">
            <span class="itile">🤖</span>
            <div>
              <div class="fw-bold t-sm">Let the AI set next week's work</div>
              <p class="t-xs muted" style="margin-top:4px">
                Pick a class and a topic; it drafts the questions, the marking
                scheme and the model answers. You review before it goes out.
              </p>
              <a class="btn btn-soft btn-sm" style="margin-top:var(--s-3)"
                 href="${url('teacher/assignments.html')}">Draft an assignment</a>
            </div>
          </div>
        </section>

        <!-- Offline -->
        <section class="card">
          <div class="card-header"><span class="card-title">Works without network</span></div>
          <div class="stack gap-3 t-sm muted">
            <div class="row gap-3">${icon('check', 16)} Mark scripts offline; they sync later</div>
            <div class="row gap-3">${icon('check', 16)} Class lists stay on the device</div>
            <div class="row gap-3">${icon('check', 16)} Reports export to print or PDF</div>
          </div>
        </section>
      </div>
    </div>`;

  const cols = $('#tdCols');
  const reflow = () => {
    cols.style.gridTemplateColumns = window.innerWidth < 1000
      ? 'minmax(0,1fr)' : 'minmax(0,1.4fr) minmax(0,1fr)';
  };
  reflow();
  window.addEventListener('resize', reflow);

  hydrateIcons();
}

const fmt = (d) => new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });

/* Start only once every module-level constant above is initialised.
   Calling render() higher up would hit the temporal dead zone on any
   const declared below it — a hoisted function can be called early,
   the constants it closes over cannot. */
if (me) render();
