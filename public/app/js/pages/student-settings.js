/* student-settings.js — profile, learning preferences, device data. */

import { boot } from '../core/boot.js';
import { hydrateIcons, icon } from '../core/icons.js';
import { $, $$, esc, toast, confirmDialog, initials, modal, closeOverlay } from '../core/ui.js';
import { url } from '../core/paths.js';
import { requireAuth, saveProfile, changePassword, signOut } from '../core/auth.js';
import { mountShell } from '../components/shell.js';
import * as store from '../core/store.js';
import { LEVELS, LANGUAGES, DAILY_GOALS, byId } from '../data/subjects.js';
import { getProgress } from '../data/progress.js';

boot();
let me = requireAuth('student');
function render() {
  const p = getProgress();
  const mine = (me.subjects || []).map(byId).filter(Boolean);
  /* The server computes this and sends it on the profile. It used to be
     derived here from a string id, which stopped being a string when identity
     moved server-side — [...me.id] on a number throws, and the whole page died
     before it rendered. Two copies of the same rule would drift anyway. */
  const linkCode = me.linkCode || '—';

  const page = mountShell({
    user: me,
    active: 'settings',
    title: 'Settings',
    subtitle: 'Profile, learning preferences and device data',
  });

  page.innerHTML = `
    <div class="page-head">
      <div><h1>Settings</h1><p class="sub">Changes save on this phone immediately.</p></div>
    </div>

    <div class="grid" style="grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);gap:var(--s-6)" id="stCols">

      <div class="stack gap-6">

        <!-- Profile -->
        <section class="card card-pad-lg">
          <div class="card-header"><span class="card-title">Profile</span></div>

          <div class="row gap-4" style="margin-bottom:var(--s-6)">
            <span class="avatar avatar-lg">${esc(initials(me.name))}</span>
            <div>
              <div class="fw-bold t-lg">${esc(me.name)}</div>
              <div class="t-sm muted">${esc(me.phone)}</div>
              <div class="row gap-2" style="margin-top:var(--s-2)">
                <span class="chip chip-brand">${esc(me.level || 'SS2')}</span>
                <span class="chip">${mine.length} subjects</span>
              </div>
            </div>
          </div>

          <div class="stack gap-5">
            <div class="field">
              <label class="label" for="name">Full name</label>
              <input class="input" id="name" value="${esc(me.name)}">
            </div>

            <div class="field">
              <label class="label" for="school">School</label>
              <input class="input" id="school" value="${esc(me.school || '')}"
                     placeholder="e.g. Government Secondary School, Wuse">
            </div>

            <div class="field">
              <span class="label">Class</span>
              <div class="row gap-2 wrap" id="levelPicks">
                ${LEVELS.map((l) => `
                  <button class="pick ${me.level === l ? 'is-active' : ''}" data-level="${l}">${l}</button>`).join('')}
              </div>
            </div>

            <button class="btn btn-primary" id="saveProfile">Save profile</button>
          </div>
        </section>

        <!-- Learning -->
        <section class="card card-pad-lg">
          <div class="card-header"><span class="card-title">Learning preferences</span></div>

          <div class="stack gap-6">
            <div class="field">
              <span class="label">How should the AI teacher talk?</span>
              <div class="stack gap-2" id="langPicks">
                ${LANGUAGES.map((l) => `
                  <button class="choice ${me.language === l.id ? 'is-active' : ''}" data-lang="${l.id}">
                    <span class="grow">
                      <span class="fw-semibold t-sm" style="display:block">${esc(l.label)}</span>
                      <span class="hint">${esc(l.hint)}</span>
                    </span>
                    <span class="choice-key" style="border-radius:999px">${me.language === l.id ? '✓' : ''}</span>
                  </button>`).join('')}
              </div>
            </div>

            <div class="field">
              <span class="label">Daily study goal</span>
              <div class="row gap-2 wrap" id="goalPicks">
                ${DAILY_GOALS.map((g) => `
                  <button class="pick ${(me.dailyGoal || 30) === g.id ? 'is-active' : ''}" data-goal="${g.id}">
                    ${g.label}
                  </button>`).join('')}
              </div>
              <span class="hint">Your streak counts a day once you cross this.</span>
            </div>

            <div class="field">
              <span class="label">My subjects</span>
              <div class="row gap-2 wrap">
                ${mine.map((s) => `<span class="chip chip-brand">${s.emoji} ${esc(s.short)}</span>`).join('')}
              </div>
              <a class="btn btn-secondary btn-sm" style="margin-top:var(--s-3);align-self:flex-start"
                 href="${url('student/subjects.html')}">Add or remove subjects</a>
            </div>
          </div>
        </section>
      </div>

      <div class="stack gap-6">

        <!-- Link a parent -->
        <section class="card">
          <div class="card-header"><span class="card-title">Link a parent</span></div>
          <p class="t-sm muted" style="margin-bottom:var(--s-4)">
            Give this code to your parent. They see your study time, streak and
            weekly report — never your chats with the tutor.
          </p>
          <div class="card card-flat center-text"
               style="background:var(--bg-sunken);border-style:dashed;padding:var(--s-5)">
            <div class="t-2xs subtle upper">Your link code</div>
            <div class="mono fw-black" style="font-size:var(--text-2xl);letter-spacing:.15em">${esc(linkCode)}</div>
          </div>
          <button class="btn btn-secondary btn-block" style="margin-top:var(--s-3)" id="copyCode">
            ${icon('clipboard', 16)} Copy code
          </button>
        </section>

        <!-- Appearance -->
        <section class="card">
          <div class="card-header"><span class="card-title">Appearance</span></div>
          <div class="stack gap-3">
            <label class="switch">
              <input type="checkbox" id="darkSwitch"
                     ${document.documentElement.getAttribute('data-theme') === 'dark' ? 'checked' : ''}>
              <span class="track"></span>
              <span class="t-sm">
                <b>Dark mode</b>
                <span class="hint" style="display:block">Saves battery on OLED phones at night</span>
              </span>
            </label>
          </div>
        </section>

        <!-- Notifications -->
        <section class="card">
          <div class="card-header"><span class="card-title">Reminders</span></div>
          <div class="stack gap-4">
            <label class="switch">
              <input type="checkbox" checked>
              <span class="track"></span>
              <span class="t-sm">Daily study reminder</span>
            </label>
            <label class="switch">
              <input type="checkbox" checked>
              <span class="track"></span>
              <span class="t-sm">Streak about to break</span>
            </label>
            <label class="switch">
              <input type="checkbox">
              <span class="track"></span>
              <span class="t-sm">New past questions available</span>
            </label>
          </div>
        </section>

        <!-- Data -->
        <section class="card">
          <div class="card-header"><span class="card-title">Data on this phone</span></div>
          <div class="stack gap-3 t-sm">
            <div class="row between"><span class="muted">Lessons completed</span><span class="fw-bold tnum">${p.lessonsDone}</span></div>
            <div class="row between"><span class="muted">Questions answered</span><span class="fw-bold tnum">${p.questionsAnswered}</span></div>
            <div class="row between"><span class="muted">Papers taken</span><span class="fw-bold tnum">${p.exams.length}</span></div>
          </div>
          <div class="stack gap-2" style="margin-top:var(--s-5)">
            <a class="btn btn-secondary btn-block" href="${url('student/downloads.html')}">
              ${icon('download', 16)} Manage offline packs
            </a>
            <button class="btn btn-ghost btn-block danger-text" id="resetBtn">Reset my progress</button>
          </div>
        </section>

        <!-- Account -->
        <section class="card">
          <div class="card-header"><span class="card-title">Account</span></div>
          <div class="stack gap-2">
            <button class="btn btn-secondary btn-block" id="pwBtn">Change password</button>
            <button class="btn btn-ghost btn-block danger-text" id="outBtn">Sign out</button>
          </div>
        </section>
      </div>
    </div>`;

  /* --- Wiring -------------------------------------------------------------- */

  let pendingLevel = me.level || 'SS2';
  $$('[data-level]').forEach((b) => b.addEventListener('click', () => {
    pendingLevel = b.dataset.level;
    $$('[data-level]').forEach((x) => x.classList.toggle('is-active', x === b));
  }));

  $('#saveProfile').addEventListener('click', async () => {
    const name = $('#name').value.trim();
    if (!name) return toast('Name cannot be empty', 'danger');

    const btn = $('#saveProfile');
    btn.disabled = true;

    const res = await saveProfile({
      name,
      school: $('#school').value.trim(),
      level: pendingLevel,
    });

    btn.disabled = false;

    if (res.error) return toast(res.error, 'danger');

    me = res.user;
    toast(res.offline ? 'Saved on this device — will sync later' : 'Profile saved', 'success');
  });

  $$('[data-lang]').forEach((b) => b.addEventListener('click', async () => {
    $$('[data-lang]').forEach((x) => {
      x.classList.toggle('is-active', x === b);
      x.querySelector('.choice-key').textContent = x === b ? '✓' : '';
    });

    const res = await saveProfile({ language: b.dataset.lang });

    if (res.error) return toast(res.error, 'danger');

    me = res.user;
    toast('Tutor language updated', 'success');
  }));

  $$('[data-goal]').forEach((b) => b.addEventListener('click', async () => {
    $$('[data-goal]').forEach((x) => x.classList.toggle('is-active', x === b));

    const res = await saveProfile({ dailyGoal: Number(b.dataset.goal) });

    if (res.error) return toast(res.error, 'danger');

    me = res.user;
    toast('Daily goal updated', 'success');
  }));

  $('#darkSwitch').addEventListener('change', (e) => {
    const theme = e.target.checked ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', theme);
    store.set('theme', theme);
  });

  $('#copyCode').addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(linkCode);
      toast('Code copied — read it out to your parent', 'success');
    } catch {
      toast(`Your code is ${linkCode}`);
    }
  });

  $('#pwBtn').addEventListener('click', () => {
    modal(`
      <h3 style="margin-bottom:var(--s-2)">Change password</h3>
      <p class="t-sm muted" style="margin-bottom:var(--s-5)">Choose something you will remember offline.</p>
      <div class="stack gap-4">
        <div class="field">
          <label class="label" for="pwOld">Current password</label>
          <div class="input-group no-icon has-action">
            <input class="input" id="pwOld" type="password" placeholder="Current password">
            <button type="button" class="input-action" data-password-toggle="pwOld"></button>
          </div>
        </div>
        <div class="field">
          <label class="label" for="pwNew">New password</label>
          <div class="input-group no-icon has-action">
            <input class="input" id="pwNew" type="password" placeholder="At least 8 characters">
            <button type="button" class="input-action" data-password-toggle="pwNew"></button>
          </div>
        </div>
        <p class="error-text hidden" id="pwErr"></p>
        <div class="row gap-3">
          <button class="btn btn-secondary grow" data-close>Cancel</button>
          <button class="btn btn-primary grow" id="pwSave">Save password</button>
        </div>
      </div>`);

    $('#pwSave').addEventListener('click', async () => {
      const err = $('#pwErr');
      const old = $('#pwOld').value;
      const nw = $('#pwNew').value;

      if (nw.length < 8) {
        err.textContent = 'New password must be at least 8 characters.';
        return err.classList.remove('hidden');
      }

      // The server checks the current password. This used to compare against a
      // copy held in local storage, which stopped being true -- and was never
      // a check worth having, since anyone editing storage could pass it.
      const res = await changePassword(old, nw);

      if (!res.ok) {
        err.textContent = res.error;
        return err.classList.remove('hidden');
      }

      closeOverlay();
      toast('Password changed', 'success');
    });
  });

  $('#resetBtn').addEventListener('click', async () => {
    const yes = await confirmDialog({
      title: 'Reset all progress?',
      message: 'Points, streak, badges, completed lessons and results will be cleared. Your downloaded packs and account stay.',
      confirmText: 'Reset everything',
      danger: true,
    });
    if (!yes) return;
    store.remove('progress');
    store.remove('exam-last');
    store.remove('exam-in-progress');
    store.remove('tutor-history');
    toast('Progress reset', 'success');
    setTimeout(() => window.location.reload(), 600);
  });

  $('#outBtn').addEventListener('click', async () => {
    const yes = await confirmDialog({
      title: 'Sign out?',
      message: 'Your downloaded lessons stay on this phone.',
      confirmText: 'Sign out',
      danger: true,
    });
    if (yes) signOut();
  });

  const cols = $('#stCols');
  const reflow = () => {
    cols.style.gridTemplateColumns = window.innerWidth < 1000
      ? 'minmax(0,1fr)' : 'minmax(0,1.3fr) minmax(0,1fr)';
  };
  reflow();
  window.addEventListener('resize', reflow);

  hydrateIcons();
}

/* Start only once every module-level constant above is initialised.
   Calling render() higher up would hit the temporal dead zone on any
   const declared below it — a hoisted function can be called early,
   the constants it closes over cannot. */
if (me) render();
