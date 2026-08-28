/* ==========================================================================
   api.js — the only file that talks to the Laravel backend.

   Every call is written so that failing is normal. A student on a Lagos bus
   loses signal constantly; that must never surface as an error, only as "not
   synced yet". Nothing here throws at the caller — every function resolves to
   a result object, and `offline: true` means the request never left the phone.
   ========================================================================== */

import * as store from './store.js';

const TOKEN_KEY = 'api-token';

/** The PWA is served from /app/, Laravel owns /api/. */
export const BASE = `${window.location.origin}/api/`;

export const token = () => store.get(TOKEN_KEY, null);
export const setToken = (value) => (value ? store.set(TOKEN_KEY, value) : store.remove(TOKEN_KEY));
export const isLinked = () => Boolean(token());

/**
 * @returns {Promise<{ok:boolean, status:number, data:any, offline:boolean}>}
 */
async function call(path, { method = 'GET', body = null, auth = true } = {}) {
  if (!navigator.onLine) {
    return { ok: false, status: 0, data: null, offline: true };
  }

  const headers = { Accept: 'application/json' };
  if (body) headers['Content-Type'] = 'application/json';
  if (auth && token()) headers.Authorization = `Bearer ${token()}`;

  try {
    const res = await fetch(`${BASE}${path}`, {
      method,
      headers,
      body: body ? JSON.stringify(body) : undefined,
    });

    let data = null;
    try { data = await res.json(); } catch { /* empty or non-JSON body */ }

    /* A rejected token means this device was signed out elsewhere. Drop it so
       the app falls back to local mode instead of retrying forever. */
    if (res.status === 401) setToken(null);

    return { ok: res.ok, status: res.status, data, offline: false };
  } catch {
    return { ok: false, status: 0, data: null, offline: true };
  }
}

/* --- Account -------------------------------------------------------------- */

export const login = (email, password) =>
  call('login', { method: 'POST', body: { email, password }, auth: false });

export const register = (name, email, password) =>
  call('register', { method: 'POST', body: { name, email, password }, auth: false });

export const forgotPassword = (email) =>
  call('forgot-password', { method: 'POST', body: { email }, auth: false });

export const me = () => call('me');

export const logout = () => call('logout', { method: 'POST' });

export const saveOnboarding = (answers) =>
  call('onboarding', { method: 'POST', body: answers });

export const updateProfile = (changes) =>
  call('me', { method: 'PATCH', body: changes });

export const changePassword = (body) =>
  call('password', { method: 'POST', body });

/* --- Content and progress -------------------------------------------------- */

export const bootstrap = () => call('bootstrap');

export const pushState = (payload) =>
  call('state', {
    method: 'POST',
    body: { payload, deviceUpdatedAt: new Date().toISOString() },
  });

export const pushAttempt = (attempt) =>
  call('attempts', { method: 'POST', body: attempt });

/* --- Classwork ------------------------------------------------------------- */

export const assignments = () => call('assignments');

export const assignment = (id) => call(`assignments/${id}`);

export const pushSubmission = (submission) =>
  call('submissions', { method: 'POST', body: submission });

export const submissions = () => call('submissions');

/**
 * Ask the server to stock this subject (or one topic) with practice questions.
 *
 * Used when a student opens a topic the bank is thin on. The work happens on
 * the server and the questions arrive with the next sync — nobody waits on a
 * model to finish while looking at an empty screen.
 */
export const requestPractice = (subject, topicRef = null) =>
  call('practice', { method: 'POST', body: { subject, topicRef } });

/* --- Tutor ----------------------------------------------------------------- */

/**
 * Ask the online tutor. Resolves with `{answered:false}` whenever the server
 * cannot help — no key, rate limited, or no network — and the caller then falls
 * back to the on-device tutor rather than showing an error.
 */
export const askTutor = ({ question, subject = null, topicRef = null, locale = 'both' }) =>
  call('tutor', { method: 'POST', body: { question, subject, topicRef, locale } });
