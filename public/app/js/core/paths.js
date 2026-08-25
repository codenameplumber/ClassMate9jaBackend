/* ==========================================================================
   paths.js — one place that knows where the project root is.
   Pages live at three depths (/, /auth/, /student/), so hard-coded "../"
   links rot fast. We derive the root from this module's own URL, which works
   identically on file://, on a laptop demo server, and on real hosting.
   ========================================================================== */

/** Absolute href of the project root, with trailing slash. */
export const ROOT = new URL('../../', import.meta.url).href;

/** url('student/dashboard.html') -> fully-resolved href */
export const url = (p = '') => ROOT + String(p).replace(/^\/+/, '');

/** Navigate to a project-relative path. */
export const go = (p) => { window.location.href = url(p); };

/** Replace history entry instead of pushing (used by auth guards). */
export const goReplace = (p) => { window.location.replace(url(p)); };

/** Current page path relative to the project root, e.g. "student/exam.html". */
export function here() {
  const full = window.location.href.split(/[?#]/)[0];
  return full.startsWith(ROOT) ? full.slice(ROOT.length) : full.split('/').pop();
}

/** Read a query-string value. */
export const param = (key, fallback = null) =>
  new URLSearchParams(window.location.search).get(key) ?? fallback;
