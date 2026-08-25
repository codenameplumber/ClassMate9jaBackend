/* student-lesson.js — one topic: explanation, classwork, and the tutor beside it. */

import { boot } from '../core/boot.js';
import { hydrateIcons, icon } from '../core/icons.js';
import { $, $$, esc, toast, confirmDialog } from '../core/ui.js';
import { url, param } from '../core/paths.js';
import { requireAuth } from '../core/auth.js';
import { mountShell, refreshTopbarStats } from '../components/shell.js';
import { getProgress, markTopicDone, addPoints } from '../data/progress.js';
import { byId, toneClass } from '../data/subjects.js';
import { schemeFor, lessonFor, classworkFor, nextTopic } from '../data/scheme.js';
import { tutorReply } from '../data/tutor.js';
import * as api from '../core/api.js';

boot();
const me = requireAuth('student');
function render() {
  const subject = byId(param('subject', 'physics')) || byId('physics');
  const topicId = param('topic', 'w1t1');
  const key = `${subject.id}:${topicId}`;

  const p = getProgress();
  const weeks = schemeFor(subject.id, subject.name, p.doneTopics);
  const week = weeks.find((w) => w.topics.some((t) => t.id === topicId)) || weeks[0];
  const topic = week.topics.find((t) => t.id === topicId) || week.topics[0];
  const lesson = lessonFor(key, topic.title, week.title, topic.mins);
  const work = classworkFor(key, topic.title);
  const done = p.doneTopics.includes(key);

  /* Language: the student's default, overridable per lesson with one tap. */
  let lang = me.language === 'pidgin' ? 'pidgin' : 'english';

  const page = mountShell({
    user: me,
    active: 'subjects',
    title: topic.title,
    subtitle: `${subject.name} · Week ${week.week} · ${topic.mins} min`,
  });

  page.innerHTML = `
    <a class="row gap-2 t-sm fw-semibold muted" href="${url(`student/scheme.html?subject=${subject.id}`)}"
       style="margin-bottom:var(--s-4)">
      ${icon('arrowL', 16)} ${esc(subject.name)} scheme
    </a>

    <div class="page-head">
      <div style="min-width:0">
        <div class="row gap-2 wrap" style="margin-bottom:var(--s-2)">
          <span class="chip chip-brand">${subject.emoji} ${esc(subject.short)}</span>
          <span class="chip">Week ${week.week}</span>
          <span class="chip">${topic.mins} min</span>
          ${done ? '<span class="chip chip-brand">✓ Completed</span>' : ''}
        </div>
        <h1>${esc(lesson.title)}</h1>
        <p class="sub">${esc(week.title)}</p>
      </div>

      <div class="row gap-3 wrap">
        <div class="segmented" id="langToggle">
          <button data-lang="english" class="${lang === 'english' ? 'is-active' : ''}">English</button>
          <button data-lang="pidgin"  class="${lang === 'pidgin' ? 'is-active' : ''}">Pidgin</button>
        </div>
        <button class="btn ${done ? 'btn-secondary' : 'btn-primary'}" id="doneBtn">
          ${done ? '✓ Completed' : 'Mark as done'}
        </button>
      </div>
    </div>

    <div class="lesson-grid">
      <div class="stack gap-6">

        <!-- Objectives -->
        <section class="card">
          <div class="card-header">
            <span class="card-title">By the end of this lesson you should be able to</span>
            <span class="chip chip-outline">${lesson.objectives.length} objectives</span>
          </div>
          <ul class="stack gap-2">
            ${lesson.objectives.map((o) => `
              <li class="row gap-3 t-sm items-start">
                <span class="tick on" style="width:20px;height:20px;border-radius:999px;background:var(--success);color:#fff;display:grid;place-items:center;font-size:11px;flex-shrink:0">✓</span>
                <span>${esc(o)}</span>
              </li>`).join('')}
          </ul>
        </section>

        <!-- Lesson body -->
        <section class="card card-pad-lg">
          <div class="prose" id="prose"></div>

          <div class="alert alert-success" style="margin-top:var(--s-6)">
            ${icon('wifiOff', 18)}
            <span>This lesson is stored on your phone. You are reading it with no data.</span>
          </div>
        </section>

        <!-- Classwork -->
        <section class="card card-pad-lg" id="classwork">
          <div class="card-header">
            <div>
              <span class="card-title">Classwork</span>
              <div class="hint">${work.length} questions · marked the moment you answer</div>
            </div>
            <span class="chip chip-amber" id="cwScore">0/${work.length}</span>
          </div>

          <div class="stack gap-5" id="cwList">
            ${work.map((q, i) => `
              <div class="stack gap-3" data-q="${i}">
                <div class="row gap-3 items-start">
                  <span class="choice-key" style="background:var(--primary-soft);color:var(--text-brand)">${i + 1}</span>
                  <div class="grow fw-medium">${esc(q.q)}</div>
                </div>
                <div class="stack gap-2" data-options="${i}">
                  ${q.options.map((o, k) => `
                    <button class="choice" data-pick="${k}" data-for="${i}">
                      <span class="choice-key">${'ABCD'[k]}</span>
                      <span class="grow t-sm">${esc(o)}</span>
                    </button>`).join('')}
                </div>
                <div class="alert alert-info hidden" data-why="${i}"></div>
              </div>`).join('')}
          </div>

          <div class="hidden" id="cwDone" style="margin-top:var(--s-6)"></div>
        </section>
      </div>

      <!-- Sidebar -->
      <aside class="stack gap-5 side-card">

        <!-- Tutor -->
        <section class="card">
          <div class="card-header">
            <span class="card-title">${icon('sparkle', 16)} Ask about this topic</span>
          </div>
          <div class="stack gap-3" id="miniLog" style="max-height:320px;overflow-y:auto">
            <div class="bubble ai" style="max-width:100%">
              <span class="who">AI tutor</span>Ask me anything about ${esc(topic.title)}. I dey here 24/7, network or no network.
            </div>
          </div>

          <div class="suggest" style="margin-top:var(--s-3)">
            <button class="chip chip-outline" data-ask="Explain this topic like I am 12">Explain simply</button>
            <button class="chip chip-outline" data-ask="Give me a Nigerian example">Naija example</button>
            <button class="chip chip-outline" data-ask="How does WAEC set this topic?">WAEC style</button>
          </div>

          <div class="row gap-2" style="margin-top:var(--s-3)">
            <input class="input grow" id="miniInput" placeholder="Type your question…">
            <button class="btn btn-primary btn-icon" id="miniSend" aria-label="Send">${icon('send', 18)}</button>
          </div>

          <a class="btn btn-ghost btn-block btn-sm" style="margin-top:var(--s-2)"
             href="${url('student/tutor.html')}">Open full tutor →</a>
        </section>

        <!-- Up next -->
        <section class="card" id="upNext"></section>

        <!-- Week progress -->
        <section class="card">
          <div class="card-header"><span class="card-title">Week ${week.week} progress</span></div>
          <div class="stack gap-2">
            ${week.topics.map((t) => `
              <a class="topic-row" href="${url(`student/lesson.html?subject=${subject.id}&topic=${t.id}`)}"
                 style="padding:var(--s-2)">
                <span class="tick ${t.done ? 'on' : ''}">${t.done ? '✓' : ''}</span>
                <span class="grow t-sm clamp-1 ${t.id === topicId ? 'fw-bold brand' : ''}">${esc(t.title)}</span>
              </a>`).join('')}
          </div>
        </section>
      </aside>
    </div>`;

  /* --- Prose rendering ---------------------------------------------------- */
  const renderProse = () => {
    $('#prose').innerHTML = lesson[lang].map(block2html).join('');
  };
  renderProse();

  $$('#langToggle button').forEach((b) => {
    b.addEventListener('click', () => {
      lang = b.dataset.lang;
      $$('#langToggle button').forEach((x) => x.classList.toggle('is-active', x === b));
      renderProse();
      toast(lang === 'pidgin' ? 'Switched to Pidgin' : 'Switched to English');
    });
  });

  /* --- Classwork marking --------------------------------------------------- */
  let answered = 0;
  let correct = 0;

  $$('#cwList [data-pick]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const qi = Number(btn.dataset.for);
      const group = $(`[data-options="${qi}"]`);
      if (group.dataset.locked) return;
      group.dataset.locked = '1';

      const q = work[qi];
      const picked = Number(btn.dataset.pick);
      const right = picked === q.answer;
      if (right) correct += 1;
      answered += 1;

      $$(`[data-options="${qi}"] .choice`).forEach((c, k) => {
        if (k === q.answer) c.classList.add('is-correct');
        else if (k === picked) c.classList.add('is-wrong');
        c.style.pointerEvents = 'none';
      });

      const why = $(`[data-why="${qi}"]`);
      why.className = `alert ${right ? 'alert-success' : 'alert-danger'}`;
      why.innerHTML = `<span>${right ? '✅ Correct. ' : '❌ Not quite. '}${esc(q.why)}</span>`;

      $('#cwScore').textContent = `${correct}/${work.length}`;

      if (answered === work.length) finishClasswork();
    });
  });

  function finishClasswork() {
    const pct = Math.round((correct / work.length) * 100);
    const pts = correct * 10;
    addPoints(pts, `Classwork · ${topic.title}`);
    refreshTopbarStats();

    const box = $('#cwDone');
    box.className = 'card card-flat';
    box.style.background = pct >= 60 ? 'var(--success-soft)' : 'var(--warning-soft)';
    box.innerHTML = `
      <div class="row gap-4 items-center wrap">
        <span class="itile itile-lg">${pct >= 60 ? '🎉' : '💪'}</span>
        <div class="grow">
          <div class="fw-bold">You scored ${correct}/${work.length} (${pct}%)</div>
          <p class="t-sm muted">
            ${pct >= 60
              ? `Solid. +${pts} points added. Run a timed practice set to lock it in.`
              : `+${pts} points. Read the explanations above, then ask the tutor to explain it another way before you move on.`}
          </p>
        </div>
        <a class="btn btn-primary" href="${url(`student/practice.html?subject=${subject.id}`)}">Practice this topic</a>
      </div>`;
  }

  /* --- Mark as done -------------------------------------------------------- */
  $('#doneBtn').addEventListener('click', async () => {
    if (getProgress().doneTopics.includes(key)) {
      return toast('You have already completed this lesson.');
    }
    markTopicDone(key, subject.id);
    refreshTopbarStats();
    toast('Lesson completed · +40 points 🎉', 'success');
    $('#doneBtn').className = 'btn btn-secondary';
    $('#doneBtn').textContent = '✓ Completed';
    paintNext();

    const nxt = nextTopic(subject.id, subject.name, getProgress().doneTopics);
    if (nxt) {
      const go = await confirmDialog({
        title: 'Next lesson ready',
        message: `Up next: ${nxt.title} (Week ${nxt.week}). Continue now while your head still dey there?`,
        confirmText: 'Continue',
        cancelText: 'Later',
      });
      if (go) window.location.href = url(`student/lesson.html?subject=${subject.id}&topic=${nxt.id}`);
    }
  });

  /* --- Up next card -------------------------------------------------------- */
  function paintNext() {
    const nxt = nextTopic(subject.id, subject.name, getProgress().doneTopics);
    $('#upNext').innerHTML = nxt ? `
      <div class="card-header"><span class="card-title">Up next</span></div>
      <div class="row gap-3">
        <span class="itile ${toneClass(subject.tone)}">${subject.emoji}</span>
        <div class="grow" style="min-width:0">
          <div class="fw-semibold t-sm clamp-2">${esc(nxt.title)}</div>
          <div class="hint">Week ${nxt.week} · ${nxt.mins} min</div>
        </div>
      </div>
      <a class="btn btn-soft btn-block" style="margin-top:var(--s-4)"
         href="${url(`student/lesson.html?subject=${subject.id}&topic=${nxt.id}`)}">Open next lesson</a>` : `
      <div class="empty" style="padding:var(--s-6) 0">
        <div class="itile">🏁</div>
        <p class="t-sm">Scheme complete. Time for the final exam.</p>
        <a class="btn btn-primary btn-sm" href="${url(`student/practice.html?subject=${subject.id}&mode=mock`)}">Take final exam</a>
      </div>`;
    hydrateIcons();
  }
  paintNext();

  /* --- Mini tutor ---------------------------------------------------------- */
  const ask = (text) => {
    if (!text.trim()) return;
    const log = $('#miniLog');
    log.insertAdjacentHTML('beforeend',
      `<div class="bubble me" style="max-width:100%">${esc(text)}</div>`);
    log.insertAdjacentHTML('beforeend',
      `<div class="bubble ai" style="max-width:100%" id="thinking">
         <span class="typing"><i></i><i></i><i></i></span></div>`);
    log.scrollTop = log.scrollHeight;
    $('#miniInput').value = '';

    (async () => {
      const remote = await api.askTutor({
        question: text,
        subject: subject.id,
        topicRef: topicId,
        locale: lang === 'pidgin' ? 'pcm' : 'en',
      });

      const reply = (remote.ok && remote.data?.answered && remote.data.reply)
        ? remote.data.reply
        : tutorReply(text, { topic: topic.title, subject: subject.name, language: lang });

      const bubble = $('#thinking');
      if (bubble) {
        bubble.outerHTML =
          `<div class="bubble ai" style="max-width:100%"><span class="who">AI tutor</span>${esc(reply)}</div>`;
      }
      log.scrollTop = log.scrollHeight;
    })();
  };

  $('#miniSend').addEventListener('click', () => ask($('#miniInput').value));
  $('#miniInput').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') ask($('#miniInput').value);
  });
  $$('[data-ask]').forEach((b) => b.addEventListener('click', () => ask(b.dataset.ask)));

  hydrateIcons();
}

/** Turn one lesson block into HTML. Blocks are tiny on purpose — the same
    shapes are produced by the generator and by the hand-written lessons. */
function block2html(b) {
  if (b.h) return `<h3>${esc(b.h)}</h3>`;
  if (b.p) return `<p>${esc(b.p)}</p>`;
  if (b.f) return `<div class="formula">${esc(b.f)}</div>`;
  if (b.naija) return `<div class="callout naija"><b>Naija example:</b> ${esc(b.naija)}</div>`;
  if (b.note) return `<div class="callout">${esc(b.note)}</div>`;
  if (b.list) return `<ul>${b.list.map((i) => `<li>${esc(i)}</li>`).join('')}</ul>`;
  return '';
}

/* Start only once every module-level constant above is initialised.
   Calling render() higher up would hit the temporal dead zone on any
   const declared below it — a hoisted function can be called early,
   the constants it closes over cannot. */
if (me) render();
