/* teacher-reports.js — the report a teacher hands to the head teacher. */

import { boot } from '../core/boot.js';
import { hydrateIcons, icon } from '../core/icons.js';
import { $, $$, esc, initials, humanMins, toast, grade } from '../core/ui.js';
import { param } from '../core/paths.js';
import { requireAuth } from '../core/auth.js';
import { mountShell } from '../components/shell.js';
import { CLASSES, studentsOf, classStats, topicHealth } from '../data/classroom.js';
import { barChart } from '../components/charts.js';
import { byId } from '../data/subjects.js';

boot();
const me = requireAuth('teacher');
/* WAEC-style bands, which is how a Nigerian head teacher reads a class. */
const BANDS = [
  { key: 'A1–B3', min: 65, tone: 'success' },
  { key: 'C4–C6', min: 50, tone: 'brand' },
  { key: 'D7–E8', min: 40, tone: 'warn' },
  { key: 'F9',    min: 0,  tone: 'danger' },
];

function render() {
  let classId = param('class') || CLASSES[0].id;

  const page = mountShell({
    user: me,
    active: 'reports',
    title: 'Reports',
    subtitle: 'Class performance, ready to print',
  });
  page.innerHTML = '<div id="body"></div>';

  function paint() {
    const cls = CLASSES.find((c) => c.id === classId) || CLASSES[0];
    const roster = studentsOf(cls.id);
    const stats = classStats(cls.id);
    const health = topicHealth(cls.id);
    const subject = byId(me.subject || 'maths');

    const bands = BANDS.map((b, i) => {
      const upper = i === 0 ? 101 : BANDS[i - 1].min;
      const list = roster.filter((s) => s.avg >= b.min && s.avg < upper);
      return { ...b, count: list.length, pct: Math.round((list.length / roster.length) * 100) };
    });

    const ranked = [...roster].sort((a, b) => b.avg - a.avg);
    const top = ranked.slice(0, 5);
    const bottom = ranked.slice(-5).reverse();

    $('#body').innerHTML = `
      <div class="page-head no-print">
        <div>
          <h1>Class report</h1>
          <p class="sub">${esc(cls.name)} · ${esc(subject ? subject.name : 'Mathematics')} · this term</p>
        </div>
        <div class="row gap-3">
          <button class="btn btn-secondary" id="printBtn">${icon('print', 16)} Print / PDF</button>
          <button class="btn btn-primary" id="sendBtn">${icon('send', 16)} Send to head teacher</button>
        </div>
      </div>

      <div class="row gap-2 wrap no-print" style="margin-bottom:var(--s-6)" id="classPicks">
        ${CLASSES.map((c) => `
          <button class="pick ${c.id === classId ? 'is-active' : ''}" data-class="${c.id}">${esc(c.name)}</button>`).join('')}
      </div>

      <div class="sheet-doc">
        <div class="doc-head">
          <div>
            <div class="brand-mark" style="margin-bottom:var(--s-3)">
              <span class="glyph">C</span>ClassMate&nbsp;<span class="naija">9ja</span>
            </div>
            <h2>${esc(cls.name)} — ${esc(subject ? subject.name : 'Mathematics')}</h2>
            <p class="t-sm muted">
              ${esc(me.school || 'School')} · prepared by ${esc(me.name)}
            </p>
          </div>
          <div class="center-text">
            <div class="t-2xs subtle upper">Class average</div>
            <div style="font-size:var(--text-3xl);font-weight:800;line-height:1">${stats.avg}%</div>
            <span class="chip ${stats.avg >= 50 ? 'chip-brand' : 'chip-danger'}">
              ${grade(stats.avg).band} · ${grade(stats.avg).note}
            </span>
          </div>
        </div>

        <!-- Summary -->
        <section style="margin-bottom:var(--s-8)">
          <h4 style="margin-bottom:var(--s-4)">Summary</h4>
          <div class="grid grid-4">
            <div class="stat-card card-flat">
              <div class="k">Students</div><div class="v">${stats.size}</div>
            </div>
            <div class="stat-card card-flat">
              <div class="k">Credit or better</div>
              <div class="v">${bands[0].count + bands[1].count}</div>
              <div class="d">${Math.round(((bands[0].count + bands[1].count) / stats.size) * 100)}% of the class</div>
            </div>
            <div class="stat-card card-flat">
              <div class="k">Below pass</div>
              <div class="v danger-text">${bands[3].count}</div>
              <div class="d">Scoring F9</div>
            </div>
            <div class="stat-card card-flat">
              <div class="k">Study time</div>
              <div class="v">${humanMins(stats.minutesWeek)}</div>
              <div class="d">Class total, this week</div>
            </div>
          </div>
        </section>

        <!-- Grade spread -->
        <section style="margin-bottom:var(--s-8)">
          <h4 style="margin-bottom:var(--s-4)">Grade spread</h4>
          ${barChart(bands.map((b) => ({ label: b.key, value: b.count })), { height: 150, unit: '' })}
          <div class="stack gap-2" style="margin-top:var(--s-5)">
            ${bands.map((b) => `
              <div class="doc-row">
                <span>${esc(b.key)}</span>
                <span class="fw-semibold tnum">${b.count} students · ${b.pct}%</span>
              </div>`).join('')}
          </div>
        </section>

        <!-- Topic analysis -->
        <section style="margin-bottom:var(--s-8)">
          <h4 style="margin-bottom:var(--s-2)">Topic analysis</h4>
          <p class="t-sm muted" style="margin-bottom:var(--s-4)">
            Where the marks are being lost, weakest topic first.
          </p>
          <div class="stack gap-4">
            ${health.map((t) => {
              const c = t.avg >= 70 ? '' : t.avg >= 50 ? 'amber' : 'danger';
              return `
              <div>
                <div class="row between t-sm" style="margin-bottom:6px">
                  <span class="fw-medium">${esc(t.topic)}</span>
                  <span class="subtle tnum">${t.avg}% · ${t.failing} below 50%</span>
                </div>
                <div class="bar"><i class="${c}" style="width:${t.avg}%"></i></div>
              </div>`;
            }).join('')}
          </div>

          <div class="alert alert-warn" style="margin-top:var(--s-5)">
            ${icon('alert', 18)}
            <span>
              <b>Recommended action:</b> re-teach ${esc(health[0].topic)} before moving on.
              ${health[0].failing} of ${stats.size} students are below 50% on it, which
              means the gap is with the teaching sequence, not with individual students.
            </span>
          </div>
        </section>

        <!-- Students -->
        <section style="margin-bottom:var(--s-8)">
          <div class="grid grid-2">
            <div>
              <h4 style="margin-bottom:var(--s-4)">Top of the class</h4>
              ${top.map((s, i) => `
                <div class="list-row">
                  <span class="chip chip-brand">${i + 1}</span>
                  <span class="avatar avatar-sm">${esc(initials(s.name))}</span>
                  <span class="grow t-sm fw-semibold clamp-1">${esc(s.name)}</span>
                  <span class="tnum fw-bold">${s.avg}%</span>
                </div>`).join('')}
            </div>
            <div>
              <h4 style="margin-bottom:var(--s-4)">Needs intervention</h4>
              ${bottom.map((s) => `
                <div class="list-row">
                  <span class="avatar avatar-sm">${esc(initials(s.name))}</span>
                  <div class="grow" style="min-width:0">
                    <div class="t-sm fw-semibold clamp-1">${esc(s.name)}</div>
                    <div class="hint">Last active ${esc(s.lastActive)}</div>
                  </div>
                  <span class="chip chip-danger">${s.avg}%</span>
                </div>`).join('')}
            </div>
          </div>
        </section>

        <!-- Engagement -->
        <section>
          <h4 style="margin-bottom:var(--s-4)">Engagement</h4>
          <div class="stack gap-2">
            <div class="doc-row">
              <span>Opened the app today</span>
              <span class="fw-semibold">${stats.activeToday} of ${stats.size}</span>
            </div>
            <div class="doc-row">
              <span>Average study time per student</span>
              <span class="fw-semibold">${humanMins(Math.round(stats.minutesWeek / stats.size))} this week</span>
            </div>
            <div class="doc-row">
              <span>Students on a 7-day streak or better</span>
              <span class="fw-semibold">${roster.filter((s) => s.streak >= 7).length}</span>
            </div>
            <div class="doc-row">
              <span>Report generated</span>
              <span class="fw-semibold">${new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })}</span>
            </div>
          </div>
        </section>
      </div>`;

    $$('[data-class]').forEach((b) => b.addEventListener('click', () => {
      classId = b.dataset.class;
      paint();
    }));

    $('#printBtn').addEventListener('click', () => window.print());
    $('#sendBtn').addEventListener('click', () => {
      toast('Report queued for the head teacher — sends when network returns', 'success', 3600);
    });

    hydrateIcons();
  }

  paint();
}

/* Start only once every module-level constant above is initialised.
   Calling render() higher up would hit the temporal dead zone on any
   const declared below it — a hoisted function can be called early,
   the constants it closes over cannot. */
if (me) render();
