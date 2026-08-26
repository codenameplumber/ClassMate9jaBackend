/* ==========================================================================
   paths.js — one place that knows where every screen lives.

   The app used to be a folder of .html files, so a link was a file path and
   the extension ended up in the URL. The screens are now Laravel routes, and
   the pages are Blade views: /learn/dashboard, not /app/student/dashboard.html.

   Rather than rewrite the ~80 call sites that say url('student/exam.html'),
   the old names are kept as the names screens are known by and translated
   here. That keeps this module doing exactly what its header always claimed —
   being the only thing that knows where a screen is — and means a future move
   is another entry in one table rather than another sweep of the codebase.

   Static assets (css/, js/, manifest.json) are still served straight off disk
   from /app/, so those resolve against ROOT exactly as before.
   ========================================================================== */

/** Absolute href of the static asset root, with trailing slash. */
export const ROOT = new URL('../../', import.meta.url).href;

/**
 * Screen name -> route path.
 *
 * The student screens are the offline app. The teacher and parent entries
 * point at the Livewire screens, which are the real ones — a link out of a
 * design page should land on the app the school actually uses.
 */
const ROUTES = {
  'index.html': '/',

  'student/dashboard.html': '/learn/dashboard',
  'student/subjects.html': '/learn/subjects',
  'student/scheme.html': '/learn/scheme',
  'student/lesson.html': '/learn/lesson',
  'student/practice.html': '/learn/practice',
  'student/exam.html': '/learn/exam',
  'student/tutor.html': '/learn/tutor',
  'student/assignments.html': '/learn/assignments',
  'student/results.html': '/learn/results',
  'student/progress.html': '/learn/progress',
  'student/downloads.html': '/learn/downloads',
  'student/settings.html': '/learn/settings',

  'auth/login.html': '/login',
  'auth/signup.html': '/register',
  'auth/onboarding.html': '/onboarding',
  'auth/forgot-password.html': '/forgot-password',
  'auth/reset-password.html': '/reset-password',
  'auth/verify-otp.html': '/forgot-password',

  'teacher/dashboard.html': '/teacher',
  'teacher/classes.html': '/teacher/classes',
  'teacher/assignments.html': '/teacher/assignments',
  'teacher/marking.html': '/teacher/marking',
  'teacher/reports.html': '/teacher/reports',

  'parent/dashboard.html': '/parent',
  'parent/report.html': '/parent/report',
};

/**
 * Resolve a screen name or asset path to a URL.
 *
 * url('student/exam.html?count=20') -> '/learn/exam?count=20'
 * url('css/base.css')               -> 'https://host/app/css/base.css'
 */
export function url(p = '') {
  const raw = String(p).replace(/^\/+/, '');
  const [path, query = ''] = raw.split(/(?=[?#])/, 2).length > 1
    ? [raw.slice(0, raw.search(/[?#]/)), raw.slice(raw.search(/[?#]/))]
    : [raw, ''];

  const route = ROUTES[path];

  return route ? route + query : ROOT + raw;
}

/** Navigate to a screen. */
export const go = (p) => { window.location.href = url(p); };

/** Replace the history entry instead of pushing (used by auth guards). */
export const goReplace = (p) => { window.location.replace(url(p)); };

/**
 * The current screen, as the name the rest of the app knows it by.
 * Returns e.g. 'student/exam.html' so callers that compare against the old
 * names keep working.
 */
export function here() {
  const path = window.location.pathname.replace(/\/+$/, '') || '/';
  const match = Object.entries(ROUTES).find(([, route]) => route === path);

  if (match) {
    return match[0];
  }

  const full = window.location.href.split(/[?#]/)[0];

  return full.startsWith(ROOT) ? full.slice(ROOT.length) : full.split('/').pop();
}

/** Read a query-string value. */
export const param = (key, fallback = null) =>
  new URLSearchParams(window.location.search).get(key) ?? fallback;
