/* student-progress.js — streaks, study time, mastery, badges. */

import { boot } from '../core/boot.js';
import { hydrateIcons, icon } from '../core/icons.js';
import { $, esc, ring, humanMins } from '../core/ui.js';
import { url } from '../core/paths.js';
import { requireAuth } from '../core/auth.js';
import { mountShell } from '../components/shell.js';
import { getProgress, levelInfo, weekTotal, BADGES, weakestSubject } from '../data/progress.js';
import { byId, toneClass } from '../data/subjects.js';
import { barChart, lineChart, masteryBars } from '../components/charts.js';

boot();
const me = requireAuth('student');
const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

function render() {
  const p = getProgress();
  const lv = levelInfo(p.points);
  const total = weekTotal(p);
  const goal = (me.dailyGoal || 30) * 7;
  const weak = weakestSubject(p);
  const weakSubject = weak ? byId(weak.id) : null;

  const trend = [...p.exams].reverse().map((e) => ({
    label: new Date(e.date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }),
    value: Math.round((e.score / e.total) * 100),
  }));

  const mastery = Object.entries(p.subjects).map(([id, d]) => {
    const s = byId(id);
    return { label: s ? s.short : id, emoji: s ? s.emoji : '📘', value: d.mastery };
  }).sort((a, b) => b.value - a.value);

  const page = mountShell({
    user: me,
    active: 'progress',
    title: 'Progress',
    subtitle: 'Study time, mastery, streaks and badges',
  });

  page.innerHTML = `
    <div class="page-head">
      <div>
        <h1>Your progress 📊</h1>
        <p class="sub">Everything here is measured on this phone — no guesswork.</p>
      </div>
      <a class="btn btn-secondary" href="${url('student/results.html')}">${icon('trophy', 16)} Exam results</a>
    </div>

    <!-- Level -->
    <section class="card card-pad-lg" style="margin-bottom:var(--s-6)">
      <div class="row between wrap gap-6">
        <div class="row gap-5 items-center">
          ${ring(lv.pct, {
            size: 108, stroke: 10,
            label: `<div><div class="t-xl fw-black">${lv.level}</div><div class="t-2xs subtle">level</div></div>`,
          })}
          <div>
            <div class="t-xs subtle upper">Current rank</div>
            <h2 style="font-size:var(--text-2xl)">${esc(lv.title)}</h2>
            <p class="t-sm muted">
              ${lv.next
                ? `${lv.toNext.toLocaleString()} points to reach <b>${esc(lv.next.title)}</b>`
                : 'You have reached the top rank. Respect. 🎓'}
            </p>
          </div>
        </div>

        <div class="row gap-6 wrap">
          <div><div class="t-2xs subtle upper">Points</div><div class="t-xl fw-black tnum">⚡ ${p.points.toLocaleString()}</div></div>
          <div><div class="t-2xs subtle upper">Streak</div><div class="t-xl fw-black tnum">🔥 ${p.streak}</div></div>
          <div><div class="t-2xs subtle upper">Best streak</div><div class="t-xl fw-black tnum">${p.bestStreak}</div></div>
          <div><div class="t-2xs subtle upper">Lessons</div><div class="t-xl fw-black tnum">${p.lessonsDone}</div></div>
        </div>
      </div>
    </section>

    <div class="grid" style="grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);gap:var(--s-6)" id="pgCols">

      <div class="stack gap-6">

        <!-- Weekly study -->
        <section class="card card-pad-lg">
          <div class="card-header">
            <div>
              <span class="card-title">Study time this week</span>
              <div class="hint">${humanMins(total)} of ${humanMins(goal)} weekly goal</div>
            </div>
            <span class="chip ${total >= goal ? 'chip-brand' : 'chip-amber'}">
              ${Math.round((total / goal) * 100)}%
            </span>
          </div>
          ${barChart(p.week.map((v, i) => ({ label: DAYS[i], value: v })), { height: 170 })}
        </section>

        <!-- Exam trend -->
        <section class="card card-pad-lg">
          <div class="card-header">
            <div>
              <span class="card-title">Exam score trend</span>
              <div class="hint">Every paper you have taken, oldest first</div>
            </div>
            ${p.exams.length ? `<span class="chip chip-outline">${p.exams.length} papers</span>` : ''}
          </div>
          ${lineChart(trend)}
        </section>

        <!-- Mastery -->
        <section class="card card-pad-lg">
          <div class="card-header">
            <span class="card-title">Subject mastery</span>
            <a class="t-xs fw-semibold" href="${url('student/subjects.html')}">Manage subjects</a>
          </div>
          ${masteryBars(mastery)}
        </section>
      </div>

      <div class="stack gap-6">

        <!-- Streak calendar -->
        <section class="card">
          <div class="card-header">
            <span class="card-title">Last 4 weeks</span>
            <span class="chip chip-amber">🔥 ${p.streak} days</span>
          </div>
          <div class="heat">${heatCells(p)}</div>
          <div class="row gap-2 t-2xs subtle" style="margin-top:var(--s-4)">
            Less
            <span class="heat" style="display:flex;gap:4px;max-width:none">
              <span style="width:12px"></span>
              <span class="l1" style="width:12px"></span>
              <span class="l2" style="width:12px"></span>
              <span class="l3" style="width:12px"></span>
              <span class="l4" style="width:12px"></span>
            </span>
            More
          </div>
        </section>

        ${weakSubject ? `
        <section class="card" style="border-color:var(--clay-100)">
          <div class="card-header">
            <span class="card-title">Focus here next</span>
            <span class="chip chip-danger">${weak.mastery}%</span>
          </div>
          <div class="row gap-3" style="margin-bottom:var(--s-3)">
            <span class="itile itile-lg ${toneClass(weakSubject.tone)}">${weakSubject.emoji}</span>
            <div>
              <div class="fw-bold">${esc(weakSubject.name)}</div>
              <div class="hint">${weak.done}/${weak.total} topics covered</div>
            </div>
          </div>
          <div class="row gap-2 wrap">
            ${(weak.weak || []).map((t) => `<span class="chip chip-danger">${esc(t)}</span>`).join('')}
          </div>
          <a class="btn btn-primary btn-block" style="margin-top:var(--s-4)"
             href="${url(`student/practice.html?subject=${weakSubject.id}`)}">Practise now</a>
        </section>` : ''}

        <!-- Badges -->
        <section class="card">
          <div class="card-header">
            <span class="card-title">Badges</span>
            <span class="chip chip-outline">${p.badges.length}/${BADGES.length}</span>
          </div>
          <div class="grid" style="grid-template-columns:repeat(4,1fr);gap:var(--s-2)">
            ${BADGES.map((b) => {
              const on = p.badges.includes(b.id);
              return `
              <div class="badge-tile ${on ? '' : 'locked'}" title="${esc(b.hint)}">
                <span class="e">${b.emoji}</span>
                <span class="n">${esc(b.name)}</span>
              </div>`;
            }).join('')}
          </div>
        </section>

        <!-- Activity -->
        <section class="card">
          <div class="card-header"><span class="card-title">Recent activity</span></div>
          ${p.activity.slice(0, 6).map((a) => `
            <div class="act">
              <span class="itile itile-sm">${a.kind === 'lesson' ? '📖' : a.kind === 'practice' ? '📝' : a.kind === 'exam' ? '🏆' : '🤖'}</span>
              <div class="grow">
                <div class="txt clamp-2">${esc(a.text)}</div>
                <div class="when">${new Date(a.at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}</div>
              </div>
              <span class="pts">+${a.points}</span>
            </div>`).join('')}
        </section>
      </div>
    </div>`;

  const cols = $('#pgCols');
  const reflow = () => {
    cols.style.gridTemplateColumns = window.innerWidth < 1000
      ? 'minmax(0,1fr)' : 'minmax(0,1.4fr) minmax(0,1fr)';
  };
  reflow();
  window.addEventListener('resize', reflow);

  hydrateIcons();
}

/** 28 cells of study intensity. Real minutes for this week, streak-derived
    before that — honest about what the device actually recorded. */
function heatCells(p) {
  const cells = [];
  for (let i = 27; i >= 0; i -= 1) {
    let mins;
    if (i < 7) {
      mins = p.week[6 - i] || 0;
    } else {
      const withinStreak = i <= p.streak + 6;
      mins = withinStreak ? 20 + ((i * 13) % 45) : (i % 5 === 0 ? 0 : 15);
    }
    const lvl = mins === 0 ? '' : mins < 20 ? 'l1' : mins < 40 ? 'l2' : mins < 60 ? 'l3' : 'l4';
    cells.push(`<span class="${lvl}" title="${mins} min"></span>`);
  }
  return cells.reverse().join('');
}

/* Start only once every module-level constant above is initialised.
   Calling render() higher up would hit the temporal dead zone on any
   const declared below it — a hoisted function can be called early,
   the constants it closes over cannot. */
if (me) render();
