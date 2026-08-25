/* teacher-classes.js — class rosters and the drill-down on one student. */

import { boot } from '../core/boot.js';
import { hydrateIcons, icon } from '../core/icons.js';
import { $, $$, esc, initials, humanMins, sheet, toast, modal } from '../core/ui.js';
import { url, param } from '../core/paths.js';
import { requireAuth } from '../core/auth.js';
import { mountShell } from '../components/shell.js';
import { CLASSES, studentsOf, classStats, topicHealth } from '../data/classroom.js';

boot();
const me = requireAuth('teacher');
function render() {
  let classId = param('class') || CLASSES[0].id;
  let query = '';
  let sortBy = 'name';

  const page = mountShell({
    user: me,
    active: 'classes',
    title: 'My classes',
    subtitle: 'Rosters, averages and who needs help',
  });
  page.innerHTML = '<div id="body"></div>';

  function paint() {
    const cls = CLASSES.find((c) => c.id === classId) || CLASSES[0];
    const stats = classStats(cls.id);
    const health = topicHealth(cls.id);

    let roster = studentsOf(cls.id);
    if (query) {
      const q = query.toLowerCase();
      roster = roster.filter((s) => s.name.toLowerCase().includes(q));
    }
    roster = [...roster].sort((a, b) => {
      if (sortBy === 'avg') return a.avg - b.avg;          // weakest first
      if (sortBy === 'time') return b.minutesWeek - a.minutesWeek;
      return a.name.localeCompare(b.name);
    });

    $('#body').innerHTML = `
      <div class="page-head">
        <div>
          <h1>${esc(cls.name)}</h1>
          <p class="sub">${esc(cls.level)} · ${esc(cls.room)} · ${stats.size} students</p>
        </div>
        <div class="row gap-3">
          <button class="btn btn-secondary" id="inviteBtn">${icon('plus', 16)} Add students</button>
          <a class="btn btn-primary" href="${url('teacher/assignments.html')}">Set work for ${esc(cls.name)}</a>
        </div>
      </div>

      <div class="row gap-2 wrap" style="margin-bottom:var(--s-6)" id="classPicks">
        ${CLASSES.map((c) => `
          <button class="pick ${c.id === classId ? 'is-active' : ''}" data-class="${c.id}">
            ${esc(c.name)} · ${classStats(c.id).size}
          </button>`).join('')}
      </div>

      <section class="stat-grid" style="margin-bottom:var(--s-6)">
        <div class="stat-card">
          <div class="k">Class average</div>
          <div class="v">${stats.avg}%</div>
          <div class="bar bar-sm" style="margin-top:var(--s-2)">
            <i class="${stats.avg >= 60 ? '' : stats.avg >= 45 ? 'amber' : 'danger'}" style="width:${stats.avg}%"></i>
          </div>
        </div>
        <div class="stat-card">
          <div class="k">Active today</div>
          <div class="v">${stats.activeToday}<span class="subtle t-lg">/${stats.size}</span></div>
          <div class="d">Opened the app today</div>
        </div>
        <div class="stat-card">
          <div class="k">Need attention</div>
          <div class="v danger-text">${stats.atRisk}</div>
          <div class="d">Below 45% average</div>
        </div>
        <div class="stat-card">
          <div class="k">Study time</div>
          <div class="v">${humanMins(stats.minutesWeek)}</div>
          <div class="d">Class total this week</div>
        </div>
      </section>

      <div class="grid" style="grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);gap:var(--s-6)" id="clCols">

        <section class="card">
          <div class="card-header">
            <span class="card-title">Roster</span>
            <div class="row gap-2">
              <div class="input-group" style="width:180px">
                <span class="input-icon">${icon('search', 16)}</span>
                <input class="input" id="search" placeholder="Find a student"
                       value="${esc(query)}" style="min-height:40px">
              </div>
              <select class="select" id="sortSel" style="min-height:40px;width:auto">
                <option value="name" ${sortBy === 'name' ? 'selected' : ''}>A–Z</option>
                <option value="avg" ${sortBy === 'avg' ? 'selected' : ''}>Weakest first</option>
                <option value="time" ${sortBy === 'time' ? 'selected' : ''}>Most study time</option>
              </select>
            </div>
          </div>

          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr><th>Student</th><th>Average</th><th>Streak</th><th>This week</th><th>Last seen</th><th></th></tr>
              </thead>
              <tbody>
                ${roster.map((s) => `
                  <tr>
                    <td>
                      <span class="row gap-3">
                        <span class="avatar avatar-sm">${esc(initials(s.name))}</span>
                        <span class="fw-semibold">${esc(s.name)}</span>
                        ${s.atRisk ? '<span class="chip chip-danger">At risk</span>' : ''}
                      </span>
                    </td>
                    <td style="min-width:120px">
                      <div class="row gap-2">
                        <span class="tnum fw-bold">${s.avg}%</span>
                        <span class="bar bar-sm grow" style="min-width:48px">
                          <i class="${s.avg >= 60 ? '' : s.avg >= 45 ? 'amber' : 'danger'}" style="width:${s.avg}%"></i>
                        </span>
                      </div>
                    </td>
                    <td class="tnum">🔥 ${s.streak}</td>
                    <td class="tnum">${humanMins(s.minutesWeek)}</td>
                    <td class="subtle">${esc(s.lastActive)}</td>
                    <td><button class="btn btn-ghost btn-sm" data-student="${esc(s.id)}">View</button></td>
                  </tr>`).join('')}
              </tbody>
            </table>
          </div>

          ${roster.length === 0 ? '<div class="empty"><p class="t-sm">No student matches that name.</p></div>' : ''}
        </section>

        <div class="stack gap-6">
          <section class="card">
            <div class="card-header">
              <div>
                <span class="card-title">Topic health</span>
                <div class="hint">Class average, weakest first</div>
              </div>
            </div>
            <div class="stack gap-4">
              ${health.map((t) => {
                const c = t.avg >= 70 ? '' : t.avg >= 50 ? 'amber' : 'danger';
                return `
                <div>
                  <div class="row between t-sm" style="margin-bottom:6px">
                    <span class="fw-medium">${esc(t.topic)}</span>
                    <span class="subtle tnum">${t.avg}% · ${t.failing} failing</span>
                  </div>
                  <div class="bar"><i class="${c}" style="width:${t.avg}%"></i></div>
                </div>`;
              }).join('')}
            </div>
            <a class="btn btn-secondary btn-block btn-sm" style="margin-top:var(--s-5)"
               href="${url('teacher/reports.html')}">Open full report</a>
          </section>

          <section class="card card-flat" style="background:var(--surface-brand);border-color:var(--border-brand)">
            <div class="row gap-3">
              <span class="itile">📣</span>
              <div>
                <div class="fw-bold t-sm">Re-teach the weakest topic</div>
                <p class="t-xs muted" style="margin-top:4px">
                  ${esc(health[0].topic)} is sitting at ${health[0].avg}%. The AI can push a
                  fresh lesson and a 10-question drill on it to the whole class.
                </p>
                <button class="btn btn-soft btn-sm" style="margin-top:var(--s-3)" id="reteachBtn">
                  Push re-teach to ${esc(cls.name)}
                </button>
              </div>
            </div>
          </section>
        </div>
      </div>`;

    /* --- wiring ------------------------------------------------------------ */

    $$('[data-class]').forEach((b) => b.addEventListener('click', () => {
      classId = b.dataset.class;
      query = '';
      paint();
    }));

    const search = $('#search');
    search.addEventListener('input', (e) => {
      query = e.target.value;
      paint();
      const box = $('#search');
      box.focus();
      box.setSelectionRange(box.value.length, box.value.length);
    });

    $('#sortSel').addEventListener('change', (e) => { sortBy = e.target.value; paint(); });

    $$('[data-student]').forEach((b) => b.addEventListener('click', () => {
      const s = studentsOf(classId).find((x) => x.id === b.dataset.student);
      openStudent(s);
    }));

    $('#inviteBtn').addEventListener('click', () => {
      modal(`
        <h3 style="margin-bottom:var(--s-2)">Add students to ${esc(cls.name)}</h3>
        <p class="t-sm muted" style="margin-bottom:var(--s-5)">
          Students join by entering this class code in their app. It works offline —
          they sync the next time either of you sees network.
        </p>
        <div class="card card-flat center-text"
             style="background:var(--bg-sunken);border-style:dashed;padding:var(--s-6)">
          <div class="t-2xs subtle upper">Class code</div>
          <div class="mono fw-black" style="font-size:var(--text-3xl);letter-spacing:.15em">
            ${esc(cls.name)}-${String(cls.size).padStart(2, '0')}
          </div>
        </div>
        <button class="btn btn-primary btn-block" style="margin-top:var(--s-5)" data-close>Done</button>`);
    });

    $('#reteachBtn').addEventListener('click', () => {
      toast(`Re-teach on ${health[0].topic} queued for ${cls.name}`, 'success', 3500);
    });

    const cols = $('#clCols');
    const reflow = () => {
      cols.style.gridTemplateColumns = window.innerWidth < 1050
        ? 'minmax(0,1fr)' : 'minmax(0,1.6fr) minmax(0,1fr)';
    };
    reflow();
    window.addEventListener('resize', reflow);

    hydrateIcons();
  }

  function openStudent(s) {
    if (!s) return;
    const topics = Object.entries(s.topics).sort((a, b) => a[1] - b[1]);
    sheet(`
      <div class="row gap-4" style="margin-bottom:var(--s-5)">
        <span class="avatar avatar-lg">${esc(initials(s.name))}</span>
        <div class="grow">
          <div class="fw-bold t-lg">${esc(s.name)}</div>
          <div class="hint">${esc(s.classId.toUpperCase())} · last active ${esc(s.lastActive)}</div>
          <div class="row gap-2" style="margin-top:var(--s-2)">
            <span class="chip ${s.avg >= 60 ? 'chip-brand' : s.avg >= 45 ? 'chip-amber' : 'chip-danger'}">
              ${s.avg}% average
            </span>
            <span class="chip chip-amber">🔥 ${s.streak} day streak</span>
            <span class="chip">${humanMins(s.minutesWeek)} this week</span>
          </div>
        </div>
      </div>

      <div class="card card-flat" style="background:var(--bg-sunken);margin-bottom:var(--s-5)">
        <div class="t-xs fw-bold" style="margin-bottom:var(--s-3)">Topic mastery</div>
        <div class="stack gap-3">
          ${topics.map(([topic, v]) => `
            <div>
              <div class="row between t-xs" style="margin-bottom:4px">
                <span>${esc(topic)}</span><span class="tnum">${v}%</span>
              </div>
              <div class="bar bar-sm">
                <i class="${v >= 70 ? '' : v >= 50 ? 'amber' : 'danger'}" style="width:${v}%"></i>
              </div>
            </div>`).join('')}
        </div>
      </div>

      ${s.atRisk ? `
        <div class="alert alert-warn" style="margin-bottom:var(--s-5)">
          ⚠ <span>This student is below the pass line. Weakest topic is
          <b>${esc(topics[0][0])}</b> at ${topics[0][1]}%.</span>
        </div>` : ''}

      <div class="stack gap-2">
        <button class="btn btn-primary btn-block" data-act="assign">Assign extra work on ${esc(topics[0][0])}</button>
        <button class="btn btn-secondary btn-block" data-act="parent">Send note to parent</button>
        <button class="btn btn-ghost btn-block" data-close>Close</button>
      </div>`);

    $('[data-act="assign"]').addEventListener('click', () => {
      toast(`Extra work on ${topics[0][0]} sent to ${s.name.split(' ')[0]}`, 'success');
    });
    $('[data-act="parent"]').addEventListener('click', () => {
      toast(`Note queued for ${s.name.split(' ')[0]}'s parent`, 'success');
    });
  }

  paint();
}

/* Start only once every module-level constant above is initialised.
   Calling render() higher up would hit the temporal dead zone on any
   const declared below it — a hoisted function can be called early,
   the constants it closes over cannot. */
if (me) render();
