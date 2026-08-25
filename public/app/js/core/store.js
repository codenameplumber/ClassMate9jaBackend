/* ==========================================================================
   store.js — the only thing in the app that touches localStorage.
   Everything is namespaced so a shared browser (cyber cafe, family phone)
   never collides with another site. Every read is defensive: a corrupt or
   quota-blocked storage must degrade to defaults, never throw a white screen.
   ========================================================================== */

const NS = 'cm9ja:';

/** Read a namespaced key. Returns `fallback` if missing or unparseable. */
export function get(key, fallback = null) {
  try {
    const raw = localStorage.getItem(NS + key);
    return raw === null ? fallback : JSON.parse(raw);
  } catch {
    return fallback;
  }
}

/** Write a namespaced key. Returns false if storage is full or blocked. */
export function set(key, value) {
  try {
    localStorage.setItem(NS + key, JSON.stringify(value));
    return true;
  } catch {
    return false;
  }
}

export function remove(key) {
  try { localStorage.removeItem(NS + key); } catch { /* noop */ }
}

/** Wipe only our keys — never other sites' data on a shared device. */
export function clearAll() {
  try {
    Object.keys(localStorage)
      .filter((k) => k.startsWith(NS))
      .forEach((k) => localStorage.removeItem(k));
  } catch { /* noop */ }
}

/** Shallow-merge a patch into an object key. */
export function patch(key, changes) {
  const current = get(key, {}) || {};
  const next = { ...current, ...changes };
  set(key, next);
  return next;
}

/* --- Theme ---------------------------------------------------------------
   Dark mode is a battery feature on OLED phones, not decoration. We resolve
   it before first paint (see the inline snippet in each page head) and store
   the explicit choice so it survives the next launch. */

export function getTheme() {
  return get('theme', null);
}

export function applyTheme(theme) {
  const resolved = theme
    || getTheme()
    || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  document.documentElement.setAttribute('data-theme', resolved);
  if (theme) set('theme', theme);
  return resolved;
}

export function toggleTheme() {
  const now = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  return applyTheme(now);
}
