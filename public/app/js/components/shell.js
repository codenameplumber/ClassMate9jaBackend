/* ==========================================================================
   shell.js — the app chrome, injected by one call per page.

   Why injected rather than copied into every HTML file: fetch() of an HTML
   partial fails under file://, and 20 hand-copied sidebars drift apart within
   a week. Template strings work everywhere and stay in one place.
   ========================================================================== */

import { icon, hydrateIcons } from '../core/icons.js';
import { $, $$, esc, initials, sheet, confirmDialog } from '../core/ui.js';
import { url } from '../core/paths.js';
import { toggleTheme } from '../core/store.js';
import { signOut } from '../core/auth.js';
import { getProgress, levelInfo } from '../data/progress.js';

/* --- Navigation per role --------------------------------------------------- */

const NAV = {
  student: [
    {
      title: 'Learn',
      items: [
        { key: 'dashboard', label: 'Dashboard',  ico: 'home',     href: 'student/dashboard.html' },
        { key: 'subjects',  label: 'My subjects', ico: 'book',     href: 'student/subjects.html' },
        { key: 'tutor',     label: 'AI tutor',    ico: 'sparkle',  href: 'student/tutor.html' },
      ],
    },
    {
      title: 'Classwork',
      items: [
        { key: 'assignments', label: 'From my teacher', ico: 'file', href: 'student/assignments.html' },
      ],
    },
    {
      title: 'Practice',
      items: [
        { key: 'practice', label: 'Past questions', ico: 'clipboard', href: 'student/practice.html' },
        { key: 'results',  label: 'My results',     ico: 'trophy',    href: 'student/results.html' },
      ],
    },
    {
      title: 'Me',
      items: [
        { key: 'progress',  label: 'Progress',      ico: 'chart',    href: 'student/progress.html' },
        { key: 'downloads', label: 'Offline packs', ico: 'download', href: 'student/downloads.html' },
        { key: 'settings',  label: 'Settings',      ico: 'sliders',  href: 'student/settings.html' },
      ],
    },
  ],
  teacher: [
    {
      title: 'Class',
      items: [
        { key: 'dashboard',   label: 'Dashboard',   ico: 'home',      href: 'teacher/dashboard.html' },
        { key: 'classes',     label: 'My classes',  ico: 'users',     href: 'teacher/classes.html' },
        { key: 'assignments', label: 'Assignments', ico: 'clipboard', href: 'teacher/assignments.html' },
      ],
    },
    {
      title: 'Marking',
      items: [
        { key: 'marking', label: 'Marking queue', ico: 'edit',  href: 'teacher/marking.html' },
        { key: 'reports', label: 'Reports',       ico: 'chart', href: 'teacher/reports.html' },
      ],
    },
  ],
  parent: [
    {
      title: 'My child',
      items: [
        { key: 'dashboard', label: 'Dashboard',     ico: 'home',  href: 'parent/dashboard.html' },
        { key: 'report',    label: 'Weekly report', ico: 'file',  href: 'parent/report.html' },
      ],
    },
  ],
};

const TABS = {
  student: [
    { key: 'dashboard', label: 'Home',     emoji: '🏠', href: 'student/dashboard.html' },
    { key: 'subjects',  label: 'Learn',    emoji: '📚', href: 'student/subjects.html' },
    { key: 'assignments', label: 'Classwork', emoji: '📋', href: 'student/assignments.html' },
    { key: 'tutor',     label: 'Tutor',    emoji: '🤖', href: 'student/tutor.html' },
    { key: 'practice',  label: 'Practice', emoji: '📝', href: 'student/practice.html' },
  ],
  teacher: [
    { key: 'dashboard',   label: 'Home',    emoji: '🏠', href: 'teacher/dashboard.html' },
    { key: 'classes',     label: 'Classes', emoji: '👥', href: 'teacher/classes.html' },
    { key: 'marking',     label: 'Marking', emoji: '✍️', href: 'teacher/marking.html' },
    { key: 'assignments', label: 'Work',    emoji: '📋', href: 'teacher/assignments.html' },
  ],
  parent: [
    { key: 'dashboard', label: 'Home',   emoji: '🏠', href: 'parent/dashboard.html' },
    { key: 'report',    label: 'Report', emoji: '📄', href: 'parent/report.html' },
  ],
};

/* --- Markup ---------------------------------------------------------------- */

function sidebarHtml(user, active) {
  const groups = NAV[user.role] || NAV.student;
  return `
  <aside class="sidebar" id="sidebar" aria-label="Main navigation">
    <div class="sidebar-head">
      <a class="brand-mark" href="${url('index.html')}">
        <span class="glyph">C</span>ClassMate&nbsp;<span class="naija">9ja</span>
      </a>
      <button class="btn btn-ghost btn-icon btn-sm hide-desktop" id="closeNav" aria-label="Close menu">
        ${icon('close', 18)}
      </button>
    </div>

    <div class="sidebar-body">
      ${groups.map((g) => `
        <div class="nav-group">
          <div class="nav-group-title">${esc(g.title)}</div>
          ${g.items.map((i) => `
            <a class="nav-link ${i.key === active ? 'is-active' : ''}" href="${url(i.href)}">
              <span class="ico">${icon(i.ico, 19)}</span>${esc(i.label)}
            </a>`).join('')}
        </div>`).join('')}
    </div>

    <div class="sidebar-foot">
      ${user.role === 'student' ? levelCardHtml() : ''}
      <button class="nav-link w-full" id="signOutBtn" style="margin-top:var(--s-2)">
        <span class="ico">${icon('logout', 19)}</span>Sign out
      </button>
    </div>
  </aside>`;
}

function levelCardHtml() {
  const p = getProgress();
  const lv = levelInfo(p.points);
  return `
  <div class="card card-flat card-pad-sm" style="background:var(--bg-sunken);border:0">
    <div class="row between" style="margin-bottom:var(--s-2)">
      <span class="t-xs fw-bold">Level ${lv.level} · ${esc(lv.title)}</span>
      <span class="t-2xs subtle tnum">${p.points.toLocaleString()} pts</span>
    </div>
    <div class="bar bar-sm"><i class="gradient" style="width:${lv.pct}%"></i></div>
    <div class="t-2xs subtle" style="margin-top:6px">
      ${lv.next ? `${lv.toNext} pts to ${esc(lv.next.title)}` : 'Top level reached 🎉'}
    </div>
  </div>`;
}

function topbarHtml(user, title, subtitle) {
  const p = user.role === 'student' ? getProgress() : null;
  return `
  <header class="topbar">
    <button class="btn btn-ghost btn-icon hide-desktop" id="openNav" aria-label="Open menu">
      ${icon('menu')}
    </button>

    <div class="grow" style="min-width:0">
      <div class="page-title clamp-1">${esc(title)}</div>
      ${subtitle ? `<div class="t-2xs subtle clamp-1 hide-mobile">${esc(subtitle)}</div>` : ''}
    </div>

    <span class="net-pill" id="netPill" title="Connection status">
      <span class="badge-dot"></span><span class="hide-mobile">Offline</span>
    </span>

    ${p ? `
      <span class="stat-chip streak hide-mobile" title="Study streak">🔥 ${p.streak}</span>
      <span class="stat-chip points hide-mobile" title="Points earned">⚡ ${p.points.toLocaleString()}</span>` : ''}

    <button class="btn btn-ghost btn-icon" id="themeBtn" aria-label="Switch theme">${icon('moon')}</button>

    <button class="avatar" id="avatarBtn" aria-label="Account menu">${esc(initials(user.name))}</button>
  </header>`;
}

function tabbarHtml(user, active) {
  const tabs = TABS[user.role] || TABS.student;
  return `
  <nav class="tabbar" aria-label="Quick navigation">
    ${tabs.map((t) => `
      <a class="${t.key === active ? 'is-active' : ''}" href="${url(t.href)}">
        <span class="ico">${t.emoji}</span>${esc(t.label)}
      </a>`).join('')}
  </nav>`;
}

/* --- Mount ----------------------------------------------------------------- */

/**
 * Inject the shell and hand back the empty page container to render into.
 * @param {object} o  { user, active, title, subtitle }
 * @returns {HTMLElement} the .page element
 */
export function mountShell({ user, active, title, subtitle = '' }) {
  document.body.insertAdjacentHTML('afterbegin', `
    <div class="app">
      ${sidebarHtml(user, active)}
      <div class="main">
        ${topbarHtml(user, title, subtitle)}
        <div class="page" id="page"></div>
      </div>
      ${tabbarHtml(user, active)}
    </div>`);

  wire(user);
  return $('#page');
}

function wire(user) {
  /* Mobile drawer */
  const sidebar = $('#sidebar');
  const open = () => {
    sidebar.classList.add('is-open');
    document.body.insertAdjacentHTML('beforeend', '<div class="scrim" id="scrim"></div>');
    $('#scrim').addEventListener('click', close);
  };
  const close = () => {
    sidebar.classList.remove('is-open');
    $('#scrim')?.remove();
  };
  $('#openNav')?.addEventListener('click', open);
  $('#closeNav')?.addEventListener('click', close);

  /* Theme */
  const paintTheme = () => {
    const dark = document.documentElement.getAttribute('data-theme') === 'dark';
    $('#themeBtn').innerHTML = icon(dark ? 'sun' : 'moon');
  };
  paintTheme();
  $('#themeBtn').addEventListener('click', () => { toggleTheme(); paintTheme(); });

  /* Connection pill — this app is judged on how it behaves offline, so the
     state is always visible rather than hidden behind a failed request. */
  const paintNet = () => {
    const pill = $('#netPill');
    const on = navigator.onLine;
    pill.classList.toggle('is-online', on);
    pill.innerHTML = `<span class="badge-dot"></span><span class="hide-mobile">${on ? 'Online · synced' : 'Offline mode'}</span>`;
  };
  paintNet();
  window.addEventListener('online', paintNet);
  window.addEventListener('offline', paintNet);

  /* Account sheet */
  $('#avatarBtn').addEventListener('click', () => {
    sheet(`
      <div class="row gap-3" style="margin-bottom:var(--s-5)">
        <span class="avatar avatar-lg">${esc(initials(user.name))}</span>
        <div class="grow">
          <div class="fw-bold">${esc(user.name)}</div>
          <div class="t-xs subtle">${esc(user.phone)} · ${esc(user.role)}</div>
          ${user.level ? `<span class="chip chip-brand" style="margin-top:6px">${esc(user.level)}</span>` : ''}
        </div>
      </div>
      <div class="stack gap-2">
        <a class="nav-link" href="${url(user.role === 'student' ? 'student/settings.html' : 'index.html')}">
          <span class="ico">${icon('sliders', 19)}</span>Settings
        </a>
        <a class="nav-link" href="${url('index.html')}">
          <span class="ico">${icon('home', 19)}</span>Back to homepage
        </a>
        <button class="nav-link w-full" data-act="signout">
          <span class="ico">${icon('logout', 19)}</span>Sign out
        </button>
      </div>`);
    $('[data-act="signout"]').addEventListener('click', askSignOut);
  });

  $('#signOutBtn').addEventListener('click', askSignOut);

  hydrateIcons();
}

async function askSignOut() {
  const yes = await confirmDialog({
    title: 'Sign out?',
    message: 'Your downloaded lessons stay on this phone. You will need your password to get back in.',
    confirmText: 'Sign out',
    danger: true,
  });
  if (yes) signOut();
}

/** Re-render the topbar chips after points or streak change mid-session. */
export function refreshTopbarStats() {
  const p = getProgress();
  const streak = $('.stat-chip.streak');
  const points = $('.stat-chip.points');
  if (streak) streak.textContent = `🔥 ${p.streak}`;
  if (points) points.textContent = `⚡ ${p.points.toLocaleString()}`;
}

export { NAV, TABS };
