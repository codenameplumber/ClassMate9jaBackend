/* student-tutor.js — the full-page AI tutor. */

import { boot } from '../core/boot.js';
import { hydrateIcons, icon } from '../core/icons.js';
import { $, $$, esc, toast, confirmDialog, plainMaths } from '../core/ui.js';
import { param } from '../core/paths.js';
import { requireAuth } from '../core/auth.js';
import { mountShell, refreshTopbarStats } from '../components/shell.js';
import * as store from '../core/store.js';
import { byId } from '../data/subjects.js';
import { addPoints } from '../data/progress.js';
import { tutorReply, greeting, SUGGESTIONS } from '../data/tutor.js';
import * as api from '../core/api.js';

boot();
const me = requireAuth('student');
const HISTORY_KEY = 'tutor-history';

function render() {
  const mySubjects = (me.subjects?.length ? me.subjects : ['maths']).map(byId).filter(Boolean);
  let subject = byId(param('subject')) || mySubjects[0];
  let lang = me.language === 'pidgin' ? 'pidgin' : 'english';
  let history = store.get(HISTORY_KEY, []) || [];

  const page = mountShell({
    user: me,
    active: 'tutor',
    title: 'AI tutor',
    subtitle: 'Explanations, examples and marking — offline',
  });

  page.innerHTML = `
    <div class="page-head">
      <div>
        <h1>Your AI teacher 🤖</h1>
        <p class="sub">Ask anything. It works with your data off.</p>
      </div>
      <div class="row gap-3 wrap">
        <div class="segmented" id="langToggle">
          <button data-lang="english" class="${lang === 'english' ? 'is-active' : ''}">English</button>
          <button data-lang="pidgin"  class="${lang === 'pidgin' ? 'is-active' : ''}">Pidgin</button>
        </div>
        <button class="btn btn-secondary" id="clearBtn">${icon('refresh', 16)} New chat</button>
      </div>
    </div>

    <div class="row gap-2 wrap" style="margin-bottom:var(--s-4)" id="subjectRow">
      <span class="t-xs subtle fw-semibold" style="align-self:center">Context:</span>
      ${mySubjects.map((s) => `
        <button class="pick ${s.id === subject.id ? 'is-active' : ''}" data-subject="${s.id}">
          ${s.emoji} ${esc(s.short)}
        </button>`).join('')}
    </div>

    <div class="chat">
      <div class="chat-log" id="log"></div>

      <div class="chat-foot">
        <div class="suggest" style="margin-bottom:var(--s-3)" id="suggest">
          ${SUGGESTIONS.map((s) => `<button class="chip chip-outline" data-ask="${esc(s)}">${esc(s)}</button>`).join('')}
        </div>
        <div class="chat-input">
          <textarea class="textarea grow" id="input" rows="1"
                    placeholder="Ask about any topic… e.g. explain mole concept with a market example"></textarea>
          <button class="btn btn-primary btn-icon" id="send" aria-label="Send message"
                  style="width:46px;height:46px">${icon('send', 18)}</button>
        </div>
        <div class="row between t-2xs subtle" style="margin-top:var(--s-2)">
          <span>${icon('wifiOff', 12)} Answers generated on this device</span>
          <span>Enter to send · Shift+Enter for a new line</span>
        </div>
      </div>
    </div>`;

  const log = $('#log');

  /* --- Painting the transcript -------------------------------------------- */
  function paintLog() {
    if (!history.length) {
      log.innerHTML = `
        <div class="bubble ai"><span class="who">AI tutor</span>${esc(greeting({ language: lang }))}</div>`;
      return;
    }
    // Answers are cached server-side for thirty days, so a reply written
    // before the model was told to avoid LaTeX is still being served. What the
    // student types is shown exactly as typed.
    log.innerHTML = history.map((m) => m.role === 'me'
      ? `<div class="bubble me">${esc(m.text)}</div>`
      : `<div class="bubble ai"><span class="who">AI tutor</span>${esc(plainMaths(m.text))}</div>`).join('');
    log.scrollTop = log.scrollHeight;
  }
  paintLog();

  /* --- Sending ------------------------------------------------------------- */
  function send(text) {
    const q = (text ?? $('#input').value).trim();
    if (!q) return;

    history.push({ role: 'me', text: q });
    store.set(HISTORY_KEY, history.slice(-40));
    $('#input').value = '';
    $('#input').style.height = 'auto';
    paintLog();

    log.insertAdjacentHTML('beforeend',
      `<div class="bubble ai" id="thinking"><span class="typing"><i></i><i></i><i></i></span></div>`);
    log.scrollTop = log.scrollHeight;

    /* Two tiers. Online, a real model answers and the reply is cached
       server-side so the next student asking the same thing gets it free.
       Offline — or with no key configured — the on-device tutor answers
       instead, which is why this app is usable in flight mode at all. */
    (async () => {
      const remote = await api.askTutor({
        question: q,
        subject: subject.id,
        locale: lang === 'pidgin' ? 'pcm' : 'en',
      });

      const reply = (remote.ok && remote.data?.answered && remote.data.reply)
        ? remote.data.reply
        : tutorReply(q, { subject: subject.name, language: lang });

      history.push({ role: 'ai', text: reply });
      store.set(HISTORY_KEY, history.slice(-40));
      paintLog();
      addPoints(2, '');
      refreshTopbarStats();
    })();
  }

  $('#send').addEventListener('click', () => send());
  $('#input').addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
  });
  /* Grow the textarea with the question, up to the CSS max-height. */
  $('#input').addEventListener('input', (e) => {
    e.target.style.height = 'auto';
    e.target.style.height = `${Math.min(e.target.scrollHeight, 130)}px`;
  });

  $$('[data-ask]').forEach((b) => b.addEventListener('click', () => send(b.dataset.ask)));

  $$('[data-subject]').forEach((b) => b.addEventListener('click', () => {
    subject = byId(b.dataset.subject);
    $$('[data-subject]').forEach((x) => x.classList.toggle('is-active', x === b));
    toast(`Context set to ${subject.name}`);
  }));

  $$('#langToggle button').forEach((b) => b.addEventListener('click', () => {
    lang = b.dataset.lang;
    $$('#langToggle button').forEach((x) => x.classList.toggle('is-active', x === b));
    toast(lang === 'pidgin' ? 'Tutor go dey talk Pidgin now' : 'Tutor switched to English');
  }));

  $('#clearBtn').addEventListener('click', async () => {
    if (!history.length) return;
    const yes = await confirmDialog({
      title: 'Start a new chat?',
      message: 'This clears the questions and answers on this screen. Your points and progress stay.',
      confirmText: 'Clear chat',
      danger: true,
    });
    if (!yes) return;
    history = [];
    store.set(HISTORY_KEY, history);
    paintLog();
  });

  hydrateIcons();
}

/* Start only once every module-level constant above is initialised.
   Calling render() higher up would hit the temporal dead zone on any
   const declared below it — a hoisted function can be called early,
   the constants it closes over cannot. */
if (me) render();
