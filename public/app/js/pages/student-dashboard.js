/* student-dashboard.js — the screen a student opens 5 times a day. */

import { boot } from '../core/boot.js';
import { hydrateIcons, icon } from '../core/icons.js';
import { $, esc, ring, humanMins, toast } from '../core/ui.js';
import { url } from '../core/paths.js';
import { requireAuth } from '../core/auth.js';
import { mountShell } from '../components/shell.js';
import { getProgress, levelInfo, weakestSubject, weekTotal } from '../data/progress.js';
import { byId, toneClass } from '../data/subjects.js';
import { nextTopic, schemeFor } from '../data/scheme.js';

boot();
const me = requireAuth('student');
function greeting() {
  const h = new Date().getHours();
  if (h < 12) return 'Good morning';
  if (h < 16) return 'Good afternoon';
  return 'Good evening';
}

function render() {
  const p = getProgress();
  const lv = levelInfo(p.points);
  const first = me.name.split(' ')[0];

  const page = mountShell({
    user: me,
    active: 'dashboard',
    title: `${greeting()}, ${first} 👋`,
    subtitle: `${me.level || 'SS2'}${me.school ? ` · ${me.school}` : ''}`,
  });

  /* --- Continue where you stopped ---------------------------------------- */
  const mySubjects = (me.subjects?.length ? me.subjects : ['maths', 'english']).map(byId).filter(Boolean);
  const active = mySubjects.find((s) => p.subjects[s.id]) || mySubjects[0];
  const nxt = nextTopic(active.id, active.name, p.doneTopics);
  const weeks = schemeFor(active.id, active.name, p.doneTopics);
  const subjPct = Math.round(
    (weeks.reduce((a, w) => a + w.doneCount, 0) / weeks.reduce((a, w) => a + w.topics.length, 0)) * 100
  );

  /* --- Today's plan: next unfinished topic per subject, capped at 3 -------- */
  const plan = mySubjects.map((s) => {
    const t = nextTopic(s.id, s.name, p.doneTopics);
    return t ? { subject: s, topic: t } : null;
  }).filter(Boolean).slice(0, 3);

  const weak = weakestSubject(p);
  const weakSubject = weak ? byId(weak.id) : null;
  const goalPct = Math.min(100, Math.round((p.minutesToday / (me.dailyGoal || 30)) * 100));

  page.innerHTML = `
    <div class="page-head">
      <div>
        <h1>${greeting()}, ${esc(first)} 👋</h1>
        <p class="sub">
          ${goalPct >= 100
            ? 'You don hit today goal. Anything extra na bonus. 🎉'
            : `${(me.dailyGoal || 30) - p.minutesToday} minutes left to hit today's study goal.`}
        </p>
      </div>
      <div class="row gap-3">
        <a class="btn btn-secondary" href="${url('student/practice.html')}">
          ${icon('clipboard', 16)} Practice
        </a>
        <a class="btn btn-primary" href="${url('student/tutor.html')}">
          ${icon('sparkle', 16)} Ask the tutor
        </a>
      </div>
    </div>

    <!-- Continue learning -->
    <section class="continue" style="margin-bottom:var(--s-6)">
      <div>
        <div class="eye">Continue where you stop</div>
        <h2>${esc(nxt ? nxt.title : `${active.name} complete!`)}</h2>
        <div class="meta">${esc(active.name)}${nxt ? ` · Week ${nxt.week} · ${nxt.mins} min` : ''}</div>

        <div style="margin-block:var(--s-5) var(--s-4);max-width:420px">
          <div class="row between t-xs" style="margin-bottom:6px;opacity:.9">
            <span>Subject progress</span><span class="fw-bold">${subjPct}%</span>
          </div>
          <div class="bar"><i style="width:${subjPct}%"></i></div>
        </div>

        <div class="row gap-3 wrap">
          <a class="btn btn-amber" href="${url(`student/lesson.html?subject=${active.id}&topic=${nxt ? nxt.id : 'w1t1'}`)}">
            ${nxt ? 'Continue lesson' : 'Revise a topic'} ${icon('arrowR', 16)}
          </a>
          <a class="btn btn-secondary" href="${url(`student/scheme.html?subject=${active.id}`)}"
             style="background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.3);color:#fff">
            See full scheme
          </a>
        </div>
      </div>

      <div class="center-text" style="display:grid;place-items:center">
        ${ring(goalPct, {
          size: 132, stroke: 11, colour: '#fff',
          label: `<div style="color:#fff">
                    <div class="t-2xl fw-black tnum">${p.minutesToday}</div>
                    <div class="t-2xs" style="opacity:.85">of ${me.dailyGoal || 30} min today</div>
                  </div>`,
        })}
      </div>
    </section>

    <!-- Stats -->
    <section class="stat-grid" style="margin-bottom:var(--s-6)">
      <div class="stat-card">
        <div class="k">Study streak</div>
        <div class="v">🔥 ${p.streak}</div>
        <div class="d">Best ever: ${p.bestStreak} days</div>
      </div>
      <div class="stat-card">
        <div class="k">Level ${lv.level}</div>
        <div class="v">${esc(lv.title)}</div>
        <div class="bar bar-sm" style="margin-top:var(--s-2)"><i class="gradient" style="width:${lv.pct}%"></i></div>
        <div class="d">${lv.next ? `${lv.toNext} pts to ${esc(lv.next.title)}` : 'Maxed out 🎉'}</div>
      </div>
      <div class="stat-card">
        <div class="k">This week</div>
        <div class="v">${humanMins(weekTotal(p))}</div>
        <div class="d">Across ${mySubjects.length} subjects</div>
      </div>
      <div class="stat-card">
        <div class="k">Questions answered</div>
        <div class="v">${p.questionsAnswered.toLocaleString()}</div>
        <div class="d">${p.lessonsDone} lessons completed</div>
      </div>
    </section>

    <!-- Quick actions -->
    <section class="qa-grid" style="margin-bottom:var(--s-6)">
      <a class="qa" href="${url('student/tutor.html')}">
        <span class="itile">🤖</span>
        <span class="t">Ask anything</span>
        <span class="s">Your AI teacher, 24/7</span>
      </a>
      <a class="qa" href="${url('student/practice.html')}">
        <span class="itile itile-sky">📝</span>
        <span class="t">Past questions</span>
        <span class="s">WAEC & JAMB, 2010–2025</span>
      </a>
      <a class="qa" href="${url('student/practice.html?mode=mock')}">
        <span class="itile itile-amber">⏱️</span>
        <span class="t">Timed mock</span>
        <span class="s">40 questions, real timing</span>
      </a>
      <a class="qa" href="${url('student/downloads.html')}">
        <span class="itile itile-violet">📥</span>
        <span class="t">Offline packs</span>
        <span class="s">Download before you travel</span>
      </a>
    </section>

    <div class="grid" style="grid-template-columns:minmax(0,1.55fr) minmax(0,1fr);gap:var(--s-6)" id="dashCols">

      <!-- Left column -->
      <div class="stack gap-6">

        <section class="card">
          <div class="card-header">
            <span class="card-title">Today's plan</span>
            <span class="chip chip-brand">${plan.length} lessons</span>
          </div>
          ${plan.length ? plan.map(({ subject, topic }) => `
            <a class="topic-row" href="${url(`student/lesson.html?subject=${subject.id}&topic=${topic.id}`)}">
              <span class="itile itile-sm ${toneClass(subject.tone)}">${subject.emoji}</span>
              <span class="grow" style="min-width:0">
                <span class="fw-semibold t-sm clamp-1" style="display:block">${esc(topic.title)}</span>
                <span class="hint">${esc(subject.short)} · Week ${topic.week} · ${topic.mins} min</span>
              </span>
              <span class="chip chip-outline">Start</span>
            </a>`).join('') : `
            <div class="empty"><div class="itile">🎉</div><p>You have cleared every scheme. Go run a mock exam.</p></div>`}
        </section>

        <section class="card">
          <div class="card-header">
            <span class="card-title">Subject progress</span>
            <a class="t-xs fw-semibold" href="${url('student/subjects.html')}">See all</a>
          </div>
          <div class="stack gap-4">
            ${mySubjects.map((s) => {
              const d = p.subjects[s.id] || { mastery: 0, done: 0, total: 24 };
              const tone = d.mastery >= 70 ? '' : d.mastery >= 50 ? 'amber' : 'danger';
              return `
              <div>
                <div class="row between" style="margin-bottom:6px">
                  <span class="row gap-2 t-sm fw-medium">
                    <span>${s.emoji}</span>${esc(s.short)}
                  </span>
                  <span class="t-xs subtle tnum">${d.mastery}% mastery · ${d.done}/${d.total} topics</span>
                </div>
                <div class="bar"><i class="${tone}" style="width:${d.mastery}%"></i></div>
              </div>`;
            }).join('')}
          </div>
        </section>
      </div>

      <!-- Right column -->
      <div class="stack gap-6">

        ${weakSubject ? `
        <section class="card" style="border-color:var(--clay-100)">
          <div class="card-header">
            <span class="card-title">Where you dey lose marks</span>
            <span class="chip chip-danger">${weak.mastery}%</span>
          </div>
          <div class="row gap-3" style="margin-bottom:var(--s-4)">
            <span class="itile itile-lg ${toneClass(weakSubject.tone)}">${weakSubject.emoji}</span>
            <div>
              <div class="fw-bold">${esc(weakSubject.name)}</div>
              <div class="hint">Your weakest subject this term</div>
            </div>
          </div>
          <div class="row gap-2 wrap" style="margin-bottom:var(--s-4)">
            ${(weak.weak || []).map((t) => `<span class="chip chip-danger">${esc(t)}</span>`).join('')}
          </div>
          <a class="btn btn-primary btn-block"
             href="${url(`student/practice.html?subject=${weakSubject.id}`)}">
            Drill these topics
          </a>
        </section>` : ''}

        <section class="card">
          <div class="card-header">
            <span class="card-title">Recent activity</span>
            <a class="t-xs fw-semibold" href="${url('student/progress.html')}">History</a>
          </div>
          ${p.activity.slice(0, 5).map((a) => `
            <div class="act">
              <span class="itile itile-sm ${a.kind === 'exam' ? 'itile-amber' : a.kind === 'practice' ? 'itile-sky' : ''}">
                ${a.kind === 'lesson' ? '📖' : a.kind === 'practice' ? '📝' : a.kind === 'exam' ? '🏆' : '🤖'}
              </span>
              <div class="grow">
                <div class="txt">${esc(a.text)}</div>
                <div class="when">${when(a.at)}</div>
              </div>
              <span class="pts">+${a.points}</span>
            </div>`).join('')}
        </section>

        <section class="card card-flat" style="background:var(--surface-brand);border-color:var(--border-brand)">
          <div class="row gap-3">
            <span class="itile">📴</span>
            <div>
              <div class="fw-bold t-sm">Travelling this weekend?</div>
              <p class="t-xs muted" style="margin-top:4px">
                Download your subject packs while you still get network. Everything
                you download today works with data off.
              </p>
              <a class="btn btn-soft btn-sm" style="margin-top:var(--s-3)"
                 href="${url('student/downloads.html')}">Manage downloads</a>
            </div>
          </div>
        </section>
      </div>
    </div>`;

  /* Single column on narrow screens without another media query in the CSS. */
  const cols = $('#dashCols');
  const reflow = () => {
    cols.style.gridTemplateColumns = window.innerWidth < 1000
      ? 'minmax(0,1fr)'
      : 'minmax(0,1.55fr) minmax(0,1fr)';
  };
  reflow();
  window.addEventListener('resize', reflow);

  hydrateIcons();

  if (!navigator.onLine) {
    toast('You are offline — everything here still works.', 'default', 3600);
  }
}

/** "2h ago", "Yesterday", "17 Aug" — short enough for a phone row. */
function when(iso) {
  const then = new Date(iso);
  const mins = Math.round((Date.now() - then.getTime()) / 60000);
  if (mins < 1) return 'Just now';
  if (mins < 60) return `${mins}m ago`;
  if (mins < 60 * 24) return `${Math.round(mins / 60)}h ago`;
  if (mins < 60 * 48) return 'Yesterday';
  return then.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
}

/* Start only once every module-level constant above is initialised.
   Calling render() higher up would hit the temporal dead zone on any
   const declared below it — a hoisted function can be called early,
   the constants it closes over cannot. */
if (me) render();
