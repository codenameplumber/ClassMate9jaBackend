/* parent-report.js — the weekly report card, built to be printed. */

import { boot } from '../core/boot.js';
import { hydrateIcons, icon } from '../core/icons.js';
import { $, esc, humanMins, initials, toast, grade } from '../core/ui.js';
import { requireAuth } from '../core/auth.js';
import { mountShell } from '../components/shell.js';
import { getProgress, weekTotal, weakestSubject } from '../data/progress.js';
import { byId } from '../data/subjects.js';

boot();
const me = requireAuth('parent');
const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

function render() {
  const p = getProgress();
  const childName = me.childName || 'Chidera Okonkwo';
  const first = childName.split(' ')[0];
  const total = weekTotal(p);
  const daysStudied = p.week.filter((m) => m >= 20).length;
  const weak = weakestSubject(p);
  const weakSubject = weak ? byId(weak.id) : null;

  const avg = p.exams.length
    ? Math.round(p.exams.reduce((a, e) => a + (e.score / e.total) * 100, 0) / p.exams.length)
    : 0;
  const g = grade(avg);

  const subjects = Object.entries(p.subjects).map(([id, d]) => ({ subject: byId(id), ...d }))
    .filter((x) => x.subject)
    .sort((a, b) => b.mastery - a.mastery);

  const page = mountShell({
    user: me,
    active: 'report',
    title: 'Weekly report',
    subtitle: `${childName} · week of ${weekLabel()}`,
  });

  page.innerHTML = `
    <div class="page-head no-print">
      <div>
        <h1>Weekly report</h1>
        <p class="sub">Everything below also goes out as one SMS, so it reaches you with no data.</p>
      </div>
      <div class="row gap-3">
        <button class="btn btn-secondary" id="printBtn">${icon('print', 16)} Print / PDF</button>
        <button class="btn btn-primary" id="smsBtn">${icon('send', 16)} Send by SMS</button>
      </div>
    </div>

    <div class="sheet-doc">

      <div class="doc-head">
        <div>
          <div class="brand-mark" style="margin-bottom:var(--s-3)">
            <span class="glyph">C</span>ClassMate&nbsp;<span class="naija">9ja</span>
          </div>
          <h2>Weekly progress report</h2>
          <p class="t-sm muted">Week of ${weekLabel()} · prepared for ${esc(me.name)}</p>
        </div>
        <div class="row gap-3 items-center">
          <span class="avatar avatar-lg">${esc(initials(childName))}</span>
          <div>
            <div class="fw-bold">${esc(childName)}</div>
            <div class="t-sm muted">SS2 · Government Secondary School, Wuse</div>
            <span class="chip ${avg >= 50 ? 'chip-brand' : 'chip-danger'}" style="margin-top:6px">
              ${g.band} · ${g.note}
            </span>
          </div>
        </div>
      </div>

      <!-- Headline -->
      <section style="margin-bottom:var(--s-8)">
        <h4 style="margin-bottom:var(--s-4)">This week at a glance</h4>
        <div class="grid grid-4">
          <div class="stat-card card-flat">
            <div class="k">Study time</div><div class="v">${humanMins(total)}</div>
          </div>
          <div class="stat-card card-flat">
            <div class="k">Days studied</div><div class="v">${daysStudied}<span class="subtle t-lg">/7</span></div>
          </div>
          <div class="stat-card card-flat">
            <div class="k">Streak</div><div class="v">🔥 ${p.streak}</div>
          </div>
          <div class="stat-card card-flat">
            <div class="k">Test average</div><div class="v">${avg}%</div>
          </div>
        </div>
      </section>

      <!-- Daily -->
      <section style="margin-bottom:var(--s-8)">
        <h4 style="margin-bottom:var(--s-4)">Day by day</h4>
        <div class="stack gap-2">
          ${p.week.map((mins, i) => `
            <div class="doc-row">
              <span>${DAYS[i]}</span>
              <span class="row gap-3 items-center">
                <span class="bar bar-sm" style="width:120px">
                  <i class="${mins >= 30 ? '' : mins > 0 ? 'amber' : 'danger'}"
                     style="width:${Math.min(100, (mins / 60) * 100)}%"></i>
                </span>
                <span class="fw-semibold tnum" style="min-width:56px;text-align:right">
                  ${mins ? `${mins} min` : '—'}
                </span>
              </span>
            </div>`).join('')}
        </div>
      </section>

      <!-- Subjects -->
      <section style="margin-bottom:var(--s-8)">
        <h4 style="margin-bottom:var(--s-4)">Subjects</h4>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Subject</th><th>Mastery</th><th>Topics done</th><th>Status</th></tr></thead>
            <tbody>
              ${subjects.map((s) => `
                <tr>
                  <td class="fw-semibold">${s.subject.emoji} ${esc(s.subject.name)}</td>
                  <td style="min-width:140px">
                    <div class="row gap-2">
                      <span class="tnum fw-bold">${s.mastery}%</span>
                      <span class="bar bar-sm grow" style="min-width:60px">
                        <i class="${s.mastery >= 70 ? '' : s.mastery >= 50 ? 'amber' : 'danger'}"
                           style="width:${s.mastery}%"></i>
                      </span>
                    </div>
                  </td>
                  <td class="tnum">${s.done}/${s.total}</td>
                  <td>
                    <span class="chip ${s.mastery >= 70 ? 'chip-brand' : s.mastery >= 50 ? 'chip-amber' : 'chip-danger'}">
                      ${s.mastery >= 70 ? 'Strong' : s.mastery >= 50 ? 'Getting there' : 'Needs work'}
                    </span>
                  </td>
                </tr>`).join('')}
            </tbody>
          </table>
        </div>
      </section>

      <!-- Tests -->
      <section style="margin-bottom:var(--s-8)">
        <h4 style="margin-bottom:var(--s-4)">Tests and mock exams</h4>
        ${p.exams.length ? `
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Paper</th><th>Score</th><th>Grade</th><th>Date</th></tr></thead>
            <tbody>
              ${p.exams.slice(0, 6).map((e) => {
                const pct = Math.round((e.score / e.total) * 100);
                const eg = grade(pct);
                return `
                <tr>
                  <td class="fw-semibold">${esc(e.title)}</td>
                  <td class="tnum">${e.score}/${e.total} (${pct}%)</td>
                  <td>
                    <span class="chip ${eg.tone === 'success' ? 'chip-brand' : eg.tone === 'danger' ? 'chip-danger' : 'chip-amber'}">
                      ${eg.band}
                    </span>
                  </td>
                  <td class="subtle">${new Date(e.date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}</td>
                </tr>`;
              }).join('')}
            </tbody>
          </table>
        </div>` : '<p class="t-sm muted">No paper written this week.</p>'}
      </section>

      <!-- What this means -->
      <section style="margin-bottom:var(--s-8)">
        <h4 style="margin-bottom:var(--s-4)">What this means</h4>
        <div class="alert ${avg >= 50 ? 'alert-success' : 'alert-warn'}">
          ${icon('sparkle', 18)}
          <span>${esc(verdict(first, daysStudied, total, avg, weakSubject, weak))}</span>
        </div>

        <div class="stack gap-3" style="margin-top:var(--s-5)">
          <div class="doc-row">
            <span class="muted">Strongest subject</span>
            <span class="fw-semibold">${subjects[0] ? `${subjects[0].subject.name} · ${subjects[0].mastery}%` : '—'}</span>
          </div>
          <div class="doc-row">
            <span class="muted">Subject needing help</span>
            <span class="fw-semibold danger-text">
              ${weakSubject ? `${weakSubject.name} · ${weak.mastery}%` : '—'}
            </span>
          </div>
          <div class="doc-row">
            <span class="muted">Topics to revise</span>
            <span class="fw-semibold">${esc((weak?.weak || []).join(', ') || '—')}</span>
          </div>
          <div class="doc-row">
            <span class="muted">Recommended for next week</span>
            <span class="fw-semibold">
              ${weakSubject ? `3 sessions on ${weakSubject.short}, 30 min each` : 'Keep the current routine'}
            </span>
          </div>
        </div>
      </section>

      <!-- SMS -->
      <section class="no-print">
        <h4 style="margin-bottom:var(--s-4)">The SMS version</h4>
        <p class="t-sm muted" style="margin-bottom:var(--s-3)">
          This is what lands on your phone if you never open the app. It costs you nothing to read.
        </p>
        <div class="sms-preview">${esc(smsText(first, total, daysStudied, weakSubject, p.streak))}</div>
      </section>

      <p class="t-2xs subtle" style="margin-top:var(--s-8);text-align:center">
        Generated by ClassMate 9ja on ${new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })}
        · Study time is measured on the device, not self-reported.
      </p>
    </div>`;

  $('#printBtn').addEventListener('click', () => window.print());
  $('#smsBtn').addEventListener('click', () => {
    toast(`Report sent to ${me.phone}`, 'success', 3200);
  });

  hydrateIcons();
}

function verdict(first, days, total, avg, weakSubject, weak) {
  const habit = days >= 5
    ? `${first} studied on ${days} of 7 days — that consistency is the single best predictor of a good WAEC result.`
    : days >= 3
      ? `${first} studied on ${days} days this week. Getting that to five would make the biggest difference of anything on this page.`
      : `${first} only studied on ${days} day${days === 1 ? '' : 's'} this week. The work is not hard yet; the routine is what is missing.`;

  const marks = avg >= 60
    ? ` Test scores are averaging ${avg}%, which is a comfortable credit.`
    : avg >= 50
      ? ` Test scores average ${avg}% — a credit, but not yet safe under exam pressure.`
      : ` Test scores average ${avg}%, which would not secure a credit in WAEC as things stand.`;

  const action = weakSubject
    ? ` The clearest thing you can do is ask about ${weakSubject.name}${weak?.weak?.length ? `, specifically ${weak.weak[0]}` : ''}. Have ${first} explain that topic to you out loud.`
    : '';

  return habit + marks + action;
}

function smsText(first, total, days, weakSubject, streak) {
  return `ClassMate 9ja: ${first} studied ${Math.floor(total / 60)}hrs ${total % 60}mins across ${days} days this week. `
    + `Streak: ${streak} days. Weakest subject: ${weakSubject ? weakSubject.name : 'none'}. `
    + `Ask ${first} to explain one topic to you today. Reply STOP to end.`;
}

function weekLabel() {
  const now = new Date();
  const monday = new Date(now);
  monday.setDate(now.getDate() - ((now.getDay() + 6) % 7));
  return monday.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
}

/* Start only once every module-level constant above is initialised.
   Calling render() higher up would hit the temporal dead zone on any
   const declared below it — a hoisted function can be called early,
   the constants it closes over cannot. */
if (me) render();
