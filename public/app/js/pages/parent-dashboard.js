/* ==========================================================================
   parent-dashboard.js — what a parent paying the fees actually wants to know.
   Deliberately not a smaller copy of the student dashboard: a parent needs a
   verdict and one action, not a lesson list.
   ========================================================================== */

import { boot } from '../core/boot.js';
import { hydrateIcons, icon } from '../core/icons.js';
import { $, $$, esc, humanMins, initials, ring, toast, grade } from '../core/ui.js';
import { url } from '../core/paths.js';
import { requireAuth } from '../core/auth.js';
import { mountShell } from '../components/shell.js';
import { getProgress, weekTotal, weakestSubject, levelInfo } from '../data/progress.js';
import { byId, toneClass } from '../data/subjects.js';
import { barChart, masteryBars } from '../components/charts.js';

boot();
const me = requireAuth('parent');
const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

function render() {
  /* The demo parent is linked to the demo student, so the numbers a parent
     sees are literally the numbers the student generated. */
  const p = getProgress();
  const childName = me.childName || 'Chidera Okonkwo';
  const total = weekTotal(p);
  const goalWeek = 30 * 7;
  const weak = weakestSubject(p);
  const weakSubject = weak ? byId(weak.id) : null;
  const lv = levelInfo(p.points);

  const avg = p.exams.length
    ? Math.round(p.exams.reduce((a, e) => a + (e.score / e.total) * 100, 0) / p.exams.length)
    : 0;
  const g = grade(avg);

  const mastery = Object.entries(p.subjects).map(([id, d]) => {
    const s = byId(id);
    return { label: s ? s.short : id, emoji: s ? s.emoji : '📘', value: d.mastery };
  }).sort((a, b) => b.value - a.value);

  const daysStudied = p.week.filter((m) => m >= 20).length;
  const onTrack = daysStudied >= 4 && total >= goalWeek * 0.6;

  const page = mountShell({
    user: me,
    active: 'dashboard',
    title: `${childName.split(' ')[0]}'s progress`,
    subtitle: 'Updated every time the phone finds network',
  });

  page.innerHTML = `
    <div class="page-head">
      <div>
        <h1>How ${esc(childName.split(' ')[0])} is doing</h1>
        <p class="sub">Week of ${weekLabel()} · SS2</p>
      </div>
      <a class="btn btn-primary" href="${url('parent/report.html')}">
        ${icon('file', 16)} Full weekly report
      </a>
    </div>

    <!-- Child switcher -->
    <div class="row gap-3 wrap" style="margin-bottom:var(--s-6)">
      <div class="child-card is-active">
        <span class="avatar">${esc(initials(childName))}</span>
        <div>
          <div class="fw-bold t-sm">${esc(childName)}</div>
          <div class="hint">SS2 · Government Secondary School, Wuse</div>
        </div>
      </div>
      <button class="child-card" id="addChild" style="cursor:pointer">
        <span class="itile itile-sm">${icon('plus', 18)}</span>
        <span class="t-sm fw-semibold">Add another child</span>
      </button>
    </div>

    <!-- The verdict -->
    <section class="continue" style="margin-bottom:var(--s-6);
             ${onTrack ? '' : 'background:linear-gradient(135deg,var(--clay-600),var(--clay-400))'}">
      <div>
        <div class="eye">This week</div>
        <h2>${onTrack
          ? `${esc(childName.split(' ')[0])} is keeping up`
          : `${esc(childName.split(' ')[0])} needs a push this week`}</h2>
        <div class="meta">
          ${onTrack
            ? `Studied on ${daysStudied} of 7 days, ${humanMins(total)} in total, and kept a ${p.streak}-day streak. The weakest subject is ${esc(weakSubject ? weakSubject.name : '—')} — that is where your encouragement will count.`
            : `Only ${daysStudied} study day${daysStudied === 1 ? '' : 's'} this week and ${humanMins(total)} in total. Nothing has gone wrong yet, but at this pace ${esc(weakSubject ? weakSubject.name : 'the weak subject')} will not be ready for WAEC.`}
        </div>
        <div class="row gap-3 wrap" style="margin-top:var(--s-5)">
          <a class="btn btn-amber" href="${url('parent/report.html')}">Read the full report</a>
          <button class="btn btn-secondary" id="nudgeBtn"
                  style="background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.3);color:#fff">
            Send an encouragement
          </button>
        </div>
      </div>

      <div class="center-text" style="display:grid;place-items:center">
        ${ring(Math.min(100, Math.round((total / goalWeek) * 100)), {
          size: 132, stroke: 11, colour: '#fff',
          label: `<div style="color:#fff">
                    <div class="t-2xl fw-black">${daysStudied}/7</div>
                    <div class="t-2xs" style="opacity:.85">days studied</div>
                  </div>`,
        })}
      </div>
    </section>

    <section class="stat-grid" style="margin-bottom:var(--s-6)">
      <div class="stat-card">
        <div class="k">Study time</div>
        <div class="v">${humanMins(total)}</div>
        <div class="d">This week, measured on the phone</div>
      </div>
      <div class="stat-card">
        <div class="k">Streak</div>
        <div class="v">🔥 ${p.streak}</div>
        <div class="d">Best ever ${p.bestStreak} days</div>
      </div>
      <div class="stat-card">
        <div class="k">Test average</div>
        <div class="v">${avg}%</div>
        <div class="d">${g.band} · ${g.note} · ${p.exams.length} papers</div>
      </div>
      <div class="stat-card">
        <div class="k">Weakest subject</div>
        <div class="v" style="font-size:var(--text-xl)">
          ${weakSubject ? `${weakSubject.emoji} ${esc(weakSubject.short)}` : '—'}
        </div>
        <div class="d danger-text">${weak ? `${weak.mastery}% mastery` : ''}</div>
      </div>
    </section>

    <div class="grid" style="grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);gap:var(--s-6)" id="pdCols">

      <div class="stack gap-6">

        <section class="card card-pad-lg">
          <div class="card-header">
            <div>
              <span class="card-title">Study time, day by day</span>
              <div class="hint">A short session every day beats one long night</div>
            </div>
          </div>
          ${barChart(p.week.map((v, i) => ({ label: DAYS[i], value: v })), { height: 165 })}
        </section>

        <section class="card card-pad-lg">
          <div class="card-header">
            <span class="card-title">Subject by subject</span>
            <span class="chip chip-outline">${mastery.length} subjects</span>
          </div>
          ${masteryBars(mastery)}
          <p class="hint" style="margin-top:var(--s-5)">
            Mastery is measured from marked work, not from time spent. A subject
            below 50% is the one to ask about at dinner.
          </p>
        </section>

        <section class="card">
          <div class="card-header">
            <span class="card-title">What ${esc(childName.split(' ')[0])} has been doing</span>
          </div>
          ${p.activity.slice(0, 6).map((a) => `
            <div class="act">
              <span class="itile itile-sm">
                ${a.kind === 'lesson' ? '📖' : a.kind === 'practice' ? '📝' : a.kind === 'exam' ? '🏆' : '🤖'}
              </span>
              <div class="grow">
                <div class="txt clamp-2">${esc(a.text)}</div>
                <div class="when">${new Date(a.at).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' })}</div>
              </div>
            </div>`).join('')}
          <p class="hint" style="margin-top:var(--s-4)">
            You see what was studied and how it was scored — never the private
            questions asked to the tutor.
          </p>
        </section>
      </div>

      <div class="stack gap-6">

        <!-- How to help -->
        <section class="card">
          <div class="card-header"><span class="card-title">How you can help this week</span></div>
          <div class="stack gap-4">
            ${helpTips(weakSubject, daysStudied, avg).map((t) => `
              <div class="row gap-3 items-start">
                <span class="itile itile-sm">${t.emoji}</span>
                <div>
                  <div class="fw-semibold t-sm">${esc(t.title)}</div>
                  <p class="t-xs muted" style="margin-top:2px">${esc(t.body)}</p>
                </div>
              </div>`).join('')}
          </div>
        </section>

        <!-- SMS -->
        <section class="card">
          <div class="card-header">
            <span class="card-title">Weekly SMS</span>
            <span class="chip chip-brand">On</span>
          </div>
          <p class="t-sm muted" style="margin-bottom:var(--s-4)">
            Every ${esc((me.reportDay || 'sunday'))[0].toUpperCase()}${esc((me.reportDay || 'sunday').slice(1))},
            to ${esc(me.phone)}. One message, no data needed to read it.
          </p>
          <div class="sms-preview">${esc(smsText(childName, total, daysStudied, weakSubject, p.streak))}</div>
          <div class="row gap-2" style="margin-top:var(--s-4)">
            <button class="btn btn-secondary grow btn-sm" id="testSms">Send me one now</button>
            <a class="btn btn-ghost btn-sm" href="${url('parent/report.html')}">Full report</a>
          </div>
        </section>

        <!-- Level -->
        <section class="card card-flat" style="background:var(--surface-brand);border-color:var(--border-brand)">
          <div class="row gap-3">
            <span class="itile">🏅</span>
            <div>
              <div class="fw-bold t-sm">Level ${lv.level} · ${esc(lv.title)}</div>
              <p class="t-xs muted" style="margin-top:4px">
                ${esc(childName.split(' ')[0])} has earned ${p.points.toLocaleString()} points and
                ${p.badges.length} badges. Ask to see them — the recognition matters more
                than we think at that age.
              </p>
            </div>
          </div>
        </section>
      </div>
    </div>`;

  $('#nudgeBtn').addEventListener('click', () => {
    toast(`Encouragement sent to ${childName.split(' ')[0]}'s app 💚`, 'success', 3200);
  });
  $('#testSms').addEventListener('click', () => {
    toast(`Test SMS queued for ${me.phone}`, 'success');
  });
  $('#addChild').addEventListener('click', () => {
    toast('Ask your other child for their link code in Settings → Link a parent', 'default', 4000);
  });

  const cols = $('#pdCols');
  const reflow = () => {
    cols.style.gridTemplateColumns = window.innerWidth < 1000
      ? 'minmax(0,1fr)' : 'minmax(0,1.4fr) minmax(0,1fr)';
  };
  reflow();
  window.addEventListener('resize', reflow);

  hydrateIcons();
}

/* Advice a parent can act on tonight, not generic encouragement. */
function helpTips(weakSubject, daysStudied, avg) {
  const tips = [];
  if (weakSubject) {
    tips.push({
      emoji: '🎯',
      title: `Ask about ${weakSubject.name}`,
      body: `Do not ask "did you read?". Ask them to explain one ${weakSubject.short} topic to you. If they cannot explain it simply, they have not understood it yet.`,
    });
  }
  if (daysStudied < 5) {
    tips.push({
      emoji: '⏰',
      title: 'Fix a time, not a duration',
      body: 'Thirty minutes at the same time each evening builds the habit faster than three hours on Saturday. Protect that half hour from errands.',
    });
  } else {
    tips.push({
      emoji: '💪',
      title: 'Protect the streak',
      body: 'The habit is holding. On a busy day, even ten minutes keeps the streak alive and keeps the routine intact.',
    });
  }
  if (avg < 50) {
    tips.push({
      emoji: '📵',
      title: 'The phone is not the problem',
      body: 'The app works with data off, so there is no need to buy data for study. If scores stay low, it is understanding, not access — ask the teacher for the topic list.',
    });
  } else {
    tips.push({
      emoji: '🗣️',
      title: 'Praise the effort, not the brain',
      body: '"You worked hard on that" builds a student who tries again after a bad score. "You are so brilliant" builds one who avoids hard papers.',
    });
  }
  return tips.slice(0, 3);
}

function smsText(name, total, days, weakSubject, streak) {
  const first = name.split(' ')[0];
  return `ClassMate 9ja: ${first} studied ${Math.round(total / 60)}hrs ${total % 60}mins across ${days} days this week. `
    + `Streak: ${streak} days. Weakest subject: ${weakSubject ? weakSubject.name : 'none'}. `
    + `Ask ${first} to explain one topic to you today. Reply STOP to end.`;
}

function weekLabel() {
  const now = new Date();
  const monday = new Date(now);
  monday.setDate(now.getDate() - ((now.getDay() + 6) % 7));
  return monday.toLocaleDateString('en-GB', { day: 'numeric', month: 'long' });
}

/* Start only once every module-level constant above is initialised.
   Calling render() higher up would hit the temporal dead zone on any
   const declared below it — a hoisted function can be called early,
   the constants it closes over cannot. */
if (me) render();
