/* ==========================================================================
   onboarding.js — role-aware setup wizard.
   Steps are declared as data so adding a question never means touching the
   navigation, the progress dots, or the validation plumbing.
   ========================================================================== */

import { boot } from '../core/boot.js';
import { hydrateIcons } from '../core/icons.js';
import { $, esc, toast } from '../core/ui.js';
import { goReplace } from '../core/paths.js';
import { currentUser, updateUser, routeHome } from '../core/auth.js';
import * as api from '../core/api.js';
import { SUBJECTS, LEVELS, GOALS, LANGUAGES, DAILY_GOALS, toneClass } from '../data/subjects.js';

boot();

const me = currentUser();
if (!me) goReplace('auth/login.html');

const CLASS_ARMS = ['JSS1A', 'JSS1B', 'JSS2A', 'JSS2B', 'JSS3A', 'SS1A', 'SS1B', 'SS2A', 'SS2B', 'SS3A'];

/* Draft holds the answers until the last step commits them in one write. */
const draft = {
  level: me.level || 'SS2',
  school: me.school || '',
  subjects: [...(me.subjects || [])],
  goal: me.goal || 'both',
  language: me.language || 'both',
  dailyGoal: me.dailyGoal || 30,
  subject: me.subject || 'maths',
  classes: [...(me.classes || [])],
  childName: '',
  reportDay: 'sunday',
};

/* --- Small render helpers -------------------------------------------------- */

const choice = (active, key, label, hint = '', emoji = '') => `
  <button type="button" class="choice ${active ? 'is-active' : ''}" data-pick="${key}">
    ${emoji ? `<span class="itile itile-sm" style="background:transparent;font-size:20px">${emoji}</span>` : ''}
    <span class="grow">
      <span class="fw-semibold t-sm" style="display:block">${esc(label)}</span>
      ${hint ? `<span class="hint">${esc(hint)}</span>` : ''}
    </span>
    <span class="choice-key" style="border-radius:var(--r-full)">${active ? '✓' : ''}</span>
  </button>`;

/* --- Step definitions ------------------------------------------------------ */

const studentSteps = [
  {
    title: 'Which class are you in?',
    sub: 'This decides your scheme of work and the standard of the questions you get.',
    render: () => `
      <div class="level-grid" style="margin-bottom:var(--s-6)">
        ${LEVELS.map((l) => `
          <button type="button" class="choice ${draft.level === l ? 'is-active' : ''}"
                  data-pick="${l}" data-group="level"
                  style="justify-content:center;padding:var(--s-5) var(--s-2)">
            <span class="fw-bold">${l}</span>
          </button>`).join('')}
      </div>
      <div class="field">
        <label class="label" for="school">School name <span class="subtle fw-medium">(optional)</span></label>
        <input class="input" id="school" type="text" value="${esc(draft.school)}"
               placeholder="e.g. Government Secondary School, Wuse">
      </div>`,
    wire: () => {
      $('#school')?.addEventListener('input', (e) => { draft.school = e.target.value; });
    },
    onPick: (val, group) => { if (group === 'level') draft.level = val; },
    valid: () => Boolean(draft.level),
    error: 'Pick your class to continue.',
  },
  {
    title: 'Which subjects are you doing?',
    sub: 'Pick as many as you carry. You can add or drop subjects later in settings.',
    render: () => `
      <div class="row between" style="margin-bottom:var(--s-4)">
        <span class="t-sm muted"><b id="pickCount">${draft.subjects.length}</b> selected</span>
        <button type="button" class="btn btn-ghost btn-sm" data-action="core">Select the 9 core subjects</button>
      </div>
      <div class="subject-row">
        ${SUBJECTS.map((s) => {
          const on = draft.subjects.includes(s.id);
          return `
          <button type="button" class="choice ${on ? 'is-active' : ''}" data-pick="${s.id}" data-group="subject">
            <span class="itile itile-sm ${toneClass(s.tone)}" style="font-size:18px">${s.emoji}</span>
            <span class="grow">
              <span class="fw-semibold t-sm" style="display:block">${esc(s.short)}</span>
              <span class="hint">${s.topics} topics · ${s.packMb} MB offline</span>
            </span>
            <span class="choice-key">${on ? '✓' : '+'}</span>
          </button>`;
        }).join('')}
      </div>`,
    onPick: (val, group) => {
      if (group !== 'subject') return;
      draft.subjects = draft.subjects.includes(val)
        ? draft.subjects.filter((s) => s !== val)
        : [...draft.subjects, val];
    },
    onAction: (action) => {
      if (action === 'core') draft.subjects = SUBJECTS.filter((s) => s.core).map((s) => s.id);
    },
    valid: () => draft.subjects.length > 0,
    error: 'Pick at least one subject.',
  },
  {
    title: 'What are you preparing for?',
    sub: 'The AI weights your practice questions and mock timing around this.',
    render: () => `<div class="stack gap-3">
      ${GOALS.map((g) => choice(draft.goal === g.id, g.id, g.label, g.hint)).join('')}
    </div>`,
    onPick: (val) => { draft.goal = val; },
    valid: () => Boolean(draft.goal),
    error: 'Choose what you are preparing for.',
  },
  {
    title: 'How should your AI teacher talk?',
    sub: 'You can switch language on any lesson with one tap — this is just the default.',
    render: () => `
      <div class="stack gap-3" style="margin-bottom:var(--s-8)">
        ${LANGUAGES.map((l) => choice(draft.language === l.id, l.id, l.label, l.hint)).join('')}
      </div>
      <h4 style="margin-bottom:var(--s-2)">Daily study goal</h4>
      <p class="hint" style="margin-bottom:var(--s-4)">Your streak counts a day once you hit this.</p>
      <div class="row gap-2 wrap">
        ${DAILY_GOALS.map((g) => `
          <button type="button" class="pick ${draft.dailyGoal === g.id ? 'is-active' : ''}"
                  data-pick="${g.id}" data-group="daily">${g.label}</button>`).join('')}
      </div>`,
    onPick: (val, group) => {
      if (group === 'daily') draft.dailyGoal = Number(val);
      else draft.language = val;
    },
    valid: () => true,
  },
];

const teacherSteps = [
  {
    title: 'Where do you teach?',
    sub: 'Your school appears on the reports you send home to parents.',
    render: () => `
      <div class="stack gap-5">
        <div class="field">
          <label class="label" for="school">School name</label>
          <input class="input" id="school" type="text" value="${esc(draft.school)}"
                 placeholder="e.g. Government Secondary School, Wuse">
        </div>
        <div class="field">
          <label class="label" for="subjectSel">Main subject you teach</label>
          <select class="select" id="subjectSel">
            ${SUBJECTS.map((s) => `<option value="${s.id}" ${draft.subject === s.id ? 'selected' : ''}>${esc(s.name)}</option>`).join('')}
          </select>
        </div>
      </div>`,
    wire: () => {
      $('#school')?.addEventListener('input', (e) => { draft.school = e.target.value; });
      $('#subjectSel')?.addEventListener('change', (e) => { draft.subject = e.target.value; });
    },
    valid: () => draft.school.trim().length > 1,
    error: 'Enter your school name.',
  },
  {
    title: 'Which classes do you handle?',
    sub: 'Each class gets its own roster, assignments and marking queue.',
    render: () => `<div class="row gap-2 wrap">
      ${CLASS_ARMS.map((c) => `
        <button type="button" class="pick ${draft.classes.includes(c) ? 'is-active' : ''}"
                data-pick="${c}" data-group="class">${c}</button>`).join('')}
    </div>`,
    onPick: (val, group) => {
      if (group !== 'class') return;
      draft.classes = draft.classes.includes(val)
        ? draft.classes.filter((c) => c !== val)
        : [...draft.classes, val];
    },
    valid: () => draft.classes.length > 0,
    error: 'Pick at least one class.',
  },
  {
    title: 'How should the AI write to your students?',
    sub: 'Applies to explanations and the feedback attached to marked scripts.',
    render: () => `<div class="stack gap-3">
      ${LANGUAGES.map((l) => choice(draft.language === l.id, l.id, l.label, l.hint)).join('')}
    </div>`,
    onPick: (val) => { draft.language = val; },
    valid: () => true,
  },
];

const parentSteps = [
  {
    title: 'Link your child',
    sub: 'Ask your child to open Settings → Link a parent and read out the 6-character code.',
    render: () => `
      <div class="stack gap-5">
        <div class="field">
          <label class="label" for="childName">Child's name</label>
          <input class="input" id="childName" type="text" value="${esc(draft.childName)}"
                 placeholder="e.g. Chidera Okonkwo">
        </div>
        <div class="field">
          <label class="label" for="linkCode">Link code</label>
          <input class="input mono" id="linkCode" type="text" maxlength="6"
                 placeholder="CHD-482" style="text-transform:uppercase;letter-spacing:.2em">
          <span class="hint">Demo: any 6 characters will link the sample student.</span>
        </div>
      </div>`,
    wire: () => {
      $('#childName')?.addEventListener('input', (e) => { draft.childName = e.target.value; });
    },
    valid: () => draft.childName.trim().length > 1,
    error: "Enter your child's name.",
  },
  {
    title: 'When should we send the weekly report?',
    sub: 'One SMS-length summary: study time, streak, weakest subject, and what to push.',
    render: () => `<div class="stack gap-3">
      ${['sunday', 'friday', 'monday'].map((d) =>
        choice(draft.reportDay === d, d, `Every ${d[0].toUpperCase()}${d.slice(1)}`,
          d === 'sunday' ? 'Recommended — before the new school week' : '')
      ).join('')}
    </div>`,
    onPick: (val) => { draft.reportDay = val; },
    valid: () => true,
  },
];

const STEPS = { student: studentSteps, teacher: teacherSteps, parent: parentSteps }[me.role] || studentSteps;

/* --- Wizard engine --------------------------------------------------------- */

let index = 0;

function paint() {
  const step = STEPS[index];

  $('#wizard').innerHTML = `
    <h1>${esc(step.title)}</h1>
    <p class="q-sub">${esc(step.sub)}</p>
    ${step.render()}
    <p class="error-text hidden" id="stepError" role="alert" style="margin-top:var(--s-4)"></p>`;

  $('#stepDots').innerHTML = STEPS.map((_, i) =>
    `<span class="dot ${i < index ? 'is-done' : ''} ${i === index ? 'is-current' : ''}"></span>`
  ).join('');

  $('#stepCount').textContent = `Step ${index + 1} of ${STEPS.length}`;
  $('#backBtn').style.visibility = index === 0 ? 'hidden' : 'visible';
  $('#nextBtn').innerHTML = index === STEPS.length - 1
    ? 'Finish setup ✓'
    : 'Continue <i data-icon="arrowR" data-size="16"></i>';

  step.wire?.();
  hydrateIcons();  // the freshly rendered step brings new icon placeholders
}

/* One delegated listener for every option in every step. */
$('#wizard').addEventListener('click', (e) => {
  const pick = e.target.closest('[data-pick]');
  const action = e.target.closest('[data-action]');
  const step = STEPS[index];

  if (pick) {
    step.onPick?.(pick.dataset.pick, pick.dataset.group);
    return paint();
  }
  if (action) {
    step.onAction?.(action.dataset.action);
    return paint();
  }
});

$('#backBtn').addEventListener('click', () => {
  if (index > 0) { index -= 1; paint(); }
});

$('#nextBtn').addEventListener('click', () => {
  const step = STEPS[index];
  if (!step.valid()) {
    const box = $('#stepError');
    box.textContent = step.error || 'Please complete this step.';
    box.classList.remove('hidden');
    return;
  }
  if (index < STEPS.length - 1) { index += 1; return paint(); }
  finish();
});

function finish() {
  /* Save to the server too, so a teacher sees the right class and subjects.
     Fire-and-forget: onboarding must never stall behind a slow connection. */
  if (me.role === 'student') {
    api.saveOnboarding({
      level: draft.level,
      subjects: draft.subjects,
      goal: draft.goal,
      locale: draft.language === 'pidgin' ? 'pcm' : draft.language === 'english' ? 'en' : 'both',
      dailyGoal: draft.dailyGoal,
      school: draft.school || null,
    }).catch(() => { /* queued for the next sync */ });
  }

  updateUser({
    onboarded: true,
    level: draft.level,
    school: draft.school,
    subjects: draft.subjects,
    goal: draft.goal,
    language: draft.language,
    dailyGoal: draft.dailyGoal,
    subject: draft.subject,
    classes: draft.classes,
    childName: draft.childName,
    reportDay: draft.reportDay,
  });
  toast('You are all set. Welcome to ClassMate 9ja!', 'success');
  setTimeout(() => routeHome(currentUser()), 600);
}

/* "Skip for now" still marks the account usable — a half-set-up student
   should land in the app, not in a dead end. */
$('#skipBtn').addEventListener('click', () => {
  updateUser({ onboarded: true, subjects: draft.subjects.length ? draft.subjects : ['maths', 'english'] });
  routeHome(currentUser());
});

paint();
