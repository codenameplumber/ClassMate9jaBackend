/* ==========================================================================
   auth.js — who is signed in, according to the server.

   This file used to be a mock: accounts, passwords and OTP codes all kept in
   localStorage, with three demo logins compiled into the bundle. It let the
   app be demonstrated before a backend existed, and it meant anyone could mint
   themselves a teacher account on their own device.

   The server owns identity now. The page is rendered by Laravel behind a
   session, and it writes the signed-in account into `cm9ja:session-user`
   before any module runs. Everything here reads that.

   The mirror is what keeps the app offline-first. Once a student has opened a
   screen, their account is on the device, so every page still renders with the
   data off — the service worker serves the shell, this returns the account,
   and the only thing that actually needs network is syncing. What is gone is
   the ability to *become* someone with no network, which never worked against
   anything real in the first place.
   ========================================================================== */

import * as store from './store.js';
import { goReplace, url } from './paths.js';

const SESSION_USER = 'session-user';

/* --- Who is signed in ----------------------------------------------------- */

/** The signed-in account, or null. Reads the server-injected mirror. */
export function currentUser() {
  const user = store.get(SESSION_USER, null);

  return user && typeof user === 'object' && user.id ? user : null;
}

export const isSignedIn = () => Boolean(currentUser());

/**
 * Persist changes to the signed-in account.
 *
 * Local only, and deliberately so: this keeps the screen honest while a
 * PATCH /api/me is in flight or waiting for signal. The server stays
 * authoritative — the next page load overwrites this with whatever it says.
 */
export function updateUser(changes) {
  const me = currentUser();
  if (!me) return null;

  const next = { ...me, ...changes };
  store.set(SESSION_USER, next);

  return next;
}

/**
 * Take a profile from the API and make it the local mirror.
 *
 * Used after onboarding and after a settings save, where the server has just
 * recomputed fields the device cannot derive on its own.
 */
export function adoptRemoteUser(profile) {
  if (!profile || typeof profile !== 'object') return currentUser();

  store.set(SESSION_USER, profile);

  return profile;
}

/* --- Leaving --------------------------------------------------------------- */

/**
 * Sign out of the Laravel session, not just this device.
 *
 * Logout is a POST so that a stray link or a prefetch cannot end someone's
 * session, which means submitting a form with the CSRF token the page carries.
 * Local state is cleared first: if the request never lands because the phone
 * is offline, the account is still gone from this device.
 */
export function signOut() {
  store.remove(SESSION_USER);
  store.remove('api-token');

  const token = document.querySelector('meta[name="csrf-token"]')?.content;

  if (!token) {
    goReplace('auth/login.html');

    return;
  }

  const form = document.createElement('form');
  form.method = 'POST';
  form.action = '/logout';
  form.style.display = 'none';

  const field = document.createElement('input');
  field.type = 'hidden';
  field.name = '_token';
  field.value = token;

  form.appendChild(field);
  document.body.appendChild(form);
  form.submit();
}

/* --- Route guards ---------------------------------------------------------- */

export const HOME_FOR = {
  student: 'student/dashboard.html',
  teacher: 'teacher/dashboard.html',
  parent: 'parent/dashboard.html',
};

/** Send a signed-in user to the right home (or to onboarding if unfinished). */
export function routeHome(user = currentUser()) {
  if (!user) return goReplace('auth/login.html');
  if (user.role === 'student' && !user.onboarded) return goReplace('auth/onboarding.html');

  return goReplace(HOME_FOR[user.role] || HOME_FOR.student);
}

/**
 * Call at the top of every protected page.
 *
 * The server has already gated the route — this cannot be the only check and
 * is not pretending to be. It exists so a screen opened from the service
 * worker cache with no session mirror sends the student to sign in rather than
 * rendering an empty shell full of undefined.
 *
 * @param {string|string[]} roles allowed role(s)
 * @returns the signed-in user, or null while redirecting
 */
export function requireAuth(roles) {
  const me = currentUser();

  if (!me) {
    goReplace('auth/login.html');

    return null;
  }

  if (me.role === 'student' && !me.onboarded) {
    goReplace('auth/onboarding.html');

    return null;
  }

  const allowed = Array.isArray(roles) ? roles : [roles];

  if (roles && !allowed.includes(me.role)) {
    routeHome(me);

    return null;
  }

  return me;
}

/** Nigerian numbers arrive as 0803…, +234803…, 234803… — store one shape. */
export function normalisePhone(raw = '') {
  const digits = String(raw).replace(/\D/g, '');
  if (digits.startsWith('234')) return `0${digits.slice(3)}`;
  if (digits.length === 10) return `0${digits}`;

  return digits;
}

export function isValidPhone(raw = '') {
  return /^0[7-9][01]\d{8}$/.test(normalisePhone(raw));
}

/** Where to send someone who is already signed in and lands on an auth page. */
export function redirectIfSignedIn() {
  const me = currentUser();
  if (me) routeHome(me);
}

export { url };
