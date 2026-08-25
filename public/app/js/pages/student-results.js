/* student-results.js — the marked script: grade, diagnosis, full review. */

import { boot } from '../core/boot.js';
import { hydrateIcons, icon } from '../core/icons.js';
import { $, $$, esc, ring, grade, humanMins } from '../core/ui.js';
import { url, param } from '../core/paths.js';
import { requireAuth } from '../core/auth.js';
import { mountShell } from '../components/shell.js';
import * as store from '../core/store.js';
import { getProgress } from '../data/progress.js';
import { byId, toneClass } from '../data/subjects.js';

boot();
const me = requireAuth('student');
function render() {
  const last = store.get('exam-last');
  const showLast = param('last') === '1' && last;

  const page = mountShell({
    user: me,
    active: 'results',
    title: showLast ? 'Your result' : 'My results',
    subtitle: showLast ? 'Marked by your AI teacher' : 'Every attempt you have made',
  });

  page.innerHTML = showLast ? lastResultHtml(last) : historyHtml();

  if (showLast) wireReview(last);
  hydrateIcons();
}

/* ==========================================================================
   A single marked paper
   ========================================================================== */

function lastResultHtml(r) {
  const pct = Math.round((r.score / r.total) * 100);
  const g = grade(pct);
  const subject = byId(r.subject);
  const tone = g.tone === 'success' ? '' : g.tone === 'danger' ? 'danger' : 'warn';

  /* Per-topic diagnosis — the part that makes a score actionable. */
  const byTopic = {};
  r.review.forEach((q) => {
    byTopic[q.topic] = byTopic[q.topic] || { total: 0, right: 0 };
    byTopic[q.topic].total += 1;
    if (q.correct) byTopic[q.topic].right += 1;
  });
  const topics = Object.entries(byTopic)
    .map(([topic, d]) => ({ topic, ...d, pct: Math.round((d.right / d.total) * 100) }))
    .sort((a, b) => a.pct - b.pct);

  const weakest = topics.filter((t) => t.pct < 60);
  const wrongCount = r.review.filter((q) => !q.correct).length;
  const blanks = r.review.filter((q) => q.picked === undefined).length;

  return `
    <div class="page-head">
      <div>
        <h1>${esc(r.title)}</h1>
        <p class="sub">
          ${new Date(r.date).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })}
          · ${humanMins(r.mins)} spent${r.autoSubmitted ? ' · submitted when time finished' : ''}
        </p>
      </div>
      <div class="row gap-3">
        <a class="btn btn-secondary" href="${url('student/results.html')}">All results</a>
        <a class="btn btn-primary" href="${url(`student/practice.html?subject=${r.subject}`)}">
          Practice again
        </a>
      </div>
    </div>

    <!-- Score -->
    <section class="score-hero" style="margin-bottom:var(--s-6)">
      ${ring(pct, {
        size: 148, stroke: 12,
        colour: pct >= 60 ? 'var(--primary)' : pct >= 45 ? 'var(--amber-400)' : 'var(--danger)',
        label: `<div>
                  <div style="font-size:var(--text-3xl);font-weight:800;line-height:1">${pct}%</div>
                  <div class="t-2xs subtle">${r.score} of ${r.total}</div>
                </div>`,
      })}

      <div class="stack gap-4">
        <div class="row gap-3 wrap items-center">
          <span class="grade-badge ${tone}">${g.band}</span>
          <div>
            <div class="fw-bold t-lg">${esc(g.note)}</div>
            <div class="hint">WAEC grading standard</div>
          </div>
        </div>

        <div class="row gap-6 wrap">
          <div><div class="t-2xs subtle upper">Correct</div><div class="fw-bold t-lg success-text">${r.score}</div></div>
          <div><div class="t-2xs subtle upper">Wrong</div><div class="fw-bold t-lg danger-text">${wrongCount - blanks}</div></div>
          <div><div class="t-2xs subtle upper">Left blank</div><div class="fw-bold t-lg">${blanks}</div></div>
          <div><div class="t-2xs subtle upper">Time used</div><div class="fw-bold t-lg">${humanMins(r.mins)}</div></div>
        </div>
      </div>
    </section>

    <!-- Teacher feedback -->
    <section class="card card-pad-lg" style="margin-bottom:var(--s-6)">
      <div class="card-header">
        <span class="card-title">${icon('sparkle', 16)} What your AI teacher says</span>
        <span class="chip chip-brand">Feedback</span>
      </div>
      <p class="t-sm" style="line-height:1.75">${esc(feedback(pct, g, weakest, blanks, subject))}</p>

      ${weakest.length ? `
        <div class="row gap-2 wrap" style="margin-top:var(--s-4)">
          ${weakest.map((t) => `<span class="chip chip-danger">${esc(t.topic)} · ${t.pct}%</span>`).join('')}
        </div>
        <a class="btn btn-primary" style="margin-top:var(--s-5)"
           href="${url(`student/practice.html?subject=${r.subject}&topic=${encodeURIComponent(weakest[0].topic)}`)}">
          Drill ${esc(weakest[0].topic)} now
        </a>` : ''}
    </section>

    <!-- Topic breakdown -->
    <section class="card" style="margin-bottom:var(--s-6)">
      <div class="card-header">
        <span class="card-title">Topic breakdown</span>
        <span class="chip chip-outline">${topics.length} topics tested</span>
      </div>
      <div class="stack gap-4">
        ${topics.map((t) => {
          const cls = t.pct >= 70 ? '' : t.pct >= 50 ? 'amber' : 'danger';
          return `
          <div>
            <div class="row between t-sm" style="margin-bottom:6px">
              <span class="fw-medium">${esc(t.topic)}</span>
              <span class="subtle tnum">${t.right}/${t.total} · ${t.pct}%</span>
            </div>
            <div class="bar"><i class="${cls}" style="width:${t.pct}%"></i></div>
          </div>`;
        }).join('')}
      </div>
    </section>

    <!-- Question review -->
    <section class="card card-pad-lg">
      <div class="card-header">
        <span class="card-title">Question review</span>
        <div class="segmented" id="reviewFilter">
          <button data-filter="all" class="is-active">All ${r.total}</button>
          <button data-filter="wrong">Wrong ${wrongCount}</button>
        </div>
      </div>

      <div class="stack gap-4" id="reviewList">
        ${r.review.map((q, i) => `
          <article class="review-q ${q.correct ? '' : 'wrong'}" data-correct="${q.correct}">
            <div class="row between gap-3" style="margin-bottom:var(--s-3)">
              <span class="t-xs fw-bold ${q.correct ? 'success-text' : 'danger-text'}">
                ${q.correct ? '✓ Correct' : q.picked === undefined ? '— Not attempted' : '✗ Wrong'}
              </span>
              <span class="row gap-2">
                <span class="chip chip-outline t-2xs">${esc(q.topic)}</span>
                <span class="chip t-2xs">${esc(q.body)} ${q.year}</span>
              </span>
            </div>

            <div class="fw-medium" style="margin-bottom:var(--s-3)">${i + 1}. ${esc(q.q)}</div>

            <div class="stack gap-2" style="margin-bottom:var(--s-3)">
              ${q.options.map((o, k) => {
                const isAnswer = k === q.answer;
                const isPicked = k === q.picked;
                const cls = isAnswer ? 'is-correct' : isPicked ? 'is-wrong' : '';
                return `
                <div class="choice ${cls}" style="cursor:default;padding:var(--s-3)">
                  <span class="choice-key">${'ABCD'[k]}</span>
                  <span class="grow t-sm">${esc(o)}</span>
                  ${isAnswer ? '<span class="chip chip-brand t-2xs">Answer</span>' : ''}
                  ${isPicked && !isAnswer ? '<span class="chip chip-danger t-2xs">You picked</span>' : ''}
                </div>`;
              }).join('')}
            </div>

            <div class="alert alert-info t-sm"><span><b>Why:</b> ${esc(q.why)}</span></div>
          </article>`).join('')}
      </div>
    </section>`;
}

/** The paragraph a student actually reads. Concrete, not "well done!". */
function feedback(pct, g, weakest, blanks, subject) {
  const name = subject ? subject.name : 'this subject';
  const head = pct >= 75
    ? `${g.band} — this is admission-level work in ${name}. You are not just passing, you are competing.`
    : pct >= 60
      ? `${g.band} — a solid credit in ${name}. The foundation is there; what is left is precision under time.`
      : pct >= 45
        ? `${g.band} — you are close to the credit line in ${name}, but not safe yet. This is a fixable gap, not a talent problem.`
        : `${g.band} — this score would hurt you in the real paper. Nothing wey happen; we know exactly where the marks went.`;

  const blankNote = blanks > 0
    ? ` You left ${blanks} question${blanks === 1 ? '' : 's'} blank. Nothing is deducted for a wrong answer, so never leave a blank — eliminate two options and pick between the rest.`
    : ' You attempted every question, which is exactly the right exam habit.';

  const topicNote = weakest.length
    ? ` Your marks leaked mostly in ${weakest.slice(0, 2).map((t) => t.topic).join(' and ')}. Redo those lessons, then run 20 timed questions on them tomorrow — not today, tomorrow, so the recall is real.`
    : ' No single topic dragged you down, so keep the spread and push your speed.';

  return head + blankNote + topicNote;
}

function wireReview(r) {
  $$('#reviewFilter button').forEach((b) => b.addEventListener('click', () => {
    $$('#reviewFilter button').forEach((x) => x.classList.toggle('is-active', x === b));
    const wrongOnly = b.dataset.filter === 'wrong';
    $$('#reviewList .review-q').forEach((card) => {
      card.classList.toggle('hidden', wrongOnly && card.dataset.correct === 'true');
    });
  }));
}

/* ==========================================================================
   History
   ========================================================================== */

function historyHtml() {
  const p = getProgress();
  const exams = p.exams || [];
  const avg = exams.length
    ? Math.round(exams.reduce((a, e) => a + (e.score / e.total) * 100, 0) / exams.length)
    : 0;
  const best = exams.length
    ? Math.max(...exams.map((e) => Math.round((e.score / e.total) * 100)))
    : 0;

  return `
    <div class="page-head">
      <div>
        <h1>My results</h1>
        <p class="sub">${exams.length} attempt${exams.length === 1 ? '' : 's'} recorded on this phone</p>
      </div>
      <a class="btn btn-primary" href="${url('student/practice.html')}">New practice</a>
    </div>

    <section class="stat-grid" style="margin-bottom:var(--s-6)">
      <div class="stat-card"><div class="k">Attempts</div><div class="v">${exams.length}</div><div class="d">All subjects</div></div>
      <div class="stat-card"><div class="k">Average</div><div class="v">${avg}%</div><div class="d">${grade(avg).band} · ${grade(avg).note}</div></div>
      <div class="stat-card"><div class="k">Best score</div><div class="v">${best}%</div><div class="d">${grade(best).band}</div></div>
      <div class="stat-card"><div class="k">Questions</div><div class="v">${p.questionsAnswered.toLocaleString()}</div><div class="d">Answered in total</div></div>
    </section>

    ${exams.length ? `
    <section class="card">
      <div class="card-header"><span class="card-title">All attempts</span></div>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th>Paper</th><th>Subject</th><th>Score</th><th>Grade</th><th>Time</th><th>Date</th></tr>
          </thead>
          <tbody>
            ${exams.map((e) => {
              const pct = Math.round((e.score / e.total) * 100);
              const g = grade(pct);
              const s = byId(e.subject);
              return `
              <tr>
                <td class="fw-semibold">${esc(e.title)}</td>
                <td><span class="row gap-2">
                  <span class="itile itile-sm ${s ? toneClass(s.tone) : ''}">${s ? s.emoji : '📝'}</span>
                  ${esc(s ? s.short : e.subject)}
                </span></td>
                <td class="tnum">${e.score}/${e.total} <span class="subtle">(${pct}%)</span></td>
                <td><span class="chip ${g.tone === 'success' ? 'chip-brand' : g.tone === 'danger' ? 'chip-danger' : 'chip-amber'}">${g.band}</span></td>
                <td class="tnum">${humanMins(e.mins)}</td>
                <td class="subtle">${new Date(e.date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}</td>
              </tr>`;
            }).join('')}
          </tbody>
        </table>
      </div>
    </section>` : `
    <div class="empty card">
      <div class="itile">📝</div>
      <h3>No result yet</h3>
      <p class="t-sm">Take your first practice set and your marked script will appear here.</p>
      <a class="btn btn-primary" href="${url('student/practice.html')}">Start practising</a>
    </div>`}`;
}

/* Start only once every module-level constant above is initialised.
   Calling render() higher up would hit the temporal dead zone on any
   const declared below it — a hoisted function can be called early,
   the constants it closes over cannot. */
if (me) render();
