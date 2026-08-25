/* ==========================================================================
   auth.js — offline-first mock authentication.

   Everything here runs against localStorage: accounts, sessions, OTP codes.
   That is deliberate. A student in Zamfara must be able to log in with the
   phone in flight mode. When a backend exists, only the four functions
   marked [SERVER] need to change — the screens never touch storage directly.
   ========================================================================== */

import * as store from './store.js';
import { goReplace } from './paths.js';

const USERS   = 'users';
const SESSION = 'session';
const OTP     = 'otp';

/* --- Demo accounts -------------------------------------------------------
   Seeded once so the app works before it has ever reached the server.
   Emails match the Laravel seeder, so the same credentials work online
   and offline. */
const SEED_USERS = [
  {
    id: 'u-student-1',
    role: 'student',
    name: 'Chidera Okonkwo',
    phone: '08012345678',
    email: 'student@classmate9ja.ng',
    password: 'student',
    level: 'SS2',
    school: 'Government Secondary School, Wuse',
    goal: 'both',
    language: 'both',
    subjects: ['maths', 'english', 'physics', 'chemistry', 'biology'],
    onboarded: true,
    avatarTone: 'green',
  },
  {
    id: 'u-teacher-1',
    role: 'teacher',
    name: 'Mrs. Folake Adebayo',
    phone: '08023456789',
    email: 'teacher@classmate9ja.ng',
    password: 'teacher',
    school: 'Government Secondary School, Wuse',
    subject: 'maths',
    onboarded: true,
    avatarTone: 'sky',
  },
  {
    id: 'u-parent-1',
    role: 'parent',
    name: 'Mr. Emeka Okonkwo',
    phone: '08034567890',
    email: 'parent@classmate9ja.ng',
    password: 'parent',
    children: ['u-student-1'],
    onboarded: true,
    avatarTone: 'amber',
  },
];

function seed() {
  if (!store.get(USERS)) store.set(USERS, SEED_USERS);
}
seed();

/* --- Helpers -------------------------------------------------------------- */

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

const allUsers = () => store.get(USERS, []) || [];

export function findUser(identifier = '') {
  const id = String(identifier).trim().toLowerCase();
  const phone = normalisePhone(identifier);
  return allUsers().find(
    (u) => u.email?.toLowerCase() === id || u.phone === phone
  ) || null;
}

/* --- Sign up / sign in ---------------------------------------------------- */

/** [SERVER] Create an account. Returns {ok, user} or {ok:false, error}. */
export function signUp({ name, phone, email, password, role = 'student' }) {
  const cleanPhone = normalisePhone(phone);
  if (!name?.trim()) return { ok: false, error: 'Enter your full name.' };
  if (!isValidPhone(cleanPhone)) return { ok: false, error: 'Enter a valid Nigerian phone number.' };
  if (!password || password.length < 6) return { ok: false, error: 'Password must be at least 6 characters.' };
  if (findUser(cleanPhone)) return { ok: false, error: 'This number already has an account. Log in instead.' };

  const user = {
    id: `u-${role}-${Date.now().toString(36)}`,
    role,
    name: name.trim(),
    phone: cleanPhone,
    email: email?.trim() || '',
    password,
    onboarded: false,
    subjects: [],
    avatarTone: 'green',
  };
  store.set(USERS, [...allUsers(), user]);
  startSession(user.id);
  return { ok: true, user };
}

/** [SERVER] Verify credentials and open a session. */
export function signIn(identifier, password) {
  const user = findUser(identifier);
  if (!user) return { ok: false, error: 'No account found for that number or email.' };
  if (user.password !== password) return { ok: false, error: 'Wrong password. Try again or reset it.' };
  startSession(user.id);
  return { ok: true, user };
}

function startSession(userId) {
  store.set(SESSION, { userId, since: new Date().toISOString() });
}

/**
 * [SERVER] Take a profile returned by the API and make it the local account.
 *
 * The device keeps a local mirror of the signed-in user so every screen still
 * renders when the network is gone. The server stays authoritative: whatever
 * it sends overwrites the mirror.
 */
export function adoptRemoteUser(profile) {
  const users = allUsers();
  const existing = users.find((u) => u.email?.toLowerCase() === profile.email?.toLowerCase());

  const merged = {
    ...(existing || {}),
    id: existing?.id || `u-${profile.role}-${profile.id}`,
    serverId: profile.id,
    role: profile.role,
    name: profile.name,
    email: profile.email,
    level: profile.level || existing?.level || 'SS2',
    language: profile.locale === 'pcm' ? 'pidgin' : profile.locale === 'en' ? 'english' : 'both',
    goal: profile.goal || 'both',
    dailyGoal: profile.dailyGoal || 30,
    school: profile.school || existing?.school || '',
    subjects: profile.subjects?.length ? profile.subjects : (existing?.subjects || []),
    onboarded: Boolean(profile.onboarded),
  };

  store.set(USERS, existing
    ? users.map((u) => (u.id === merged.id ? merged : u))
    : [...users, merged]);

  startSession(merged.id);

  return merged;
}

export function signOut() {
  store.remove(SESSION);
  goReplace('auth/login.html');
}

export function currentUser() {
  const session = store.get(SESSION);
  if (!session?.userId) return null;
  return allUsers().find((u) => u.id === session.userId) || null;
}

export const isSignedIn = () => Boolean(currentUser());

/** Persist changes to the signed-in user (onboarding answers, settings). */
export function updateUser(changes) {
  const me = currentUser();
  if (!me) return null;
  const next = { ...me, ...changes };
  store.set(USERS, allUsers().map((u) => (u.id === me.id ? next : u)));
  return next;
}

/* --- OTP (password reset by SMS) -----------------------------------------
   Offline demo: the code is generated on-device and surfaced in the UI.
   [SERVER] swaps this for an SMS gateway call; verify() stays identical. */

export function sendOtp(phone) {
  const cleanPhone = normalisePhone(phone);
  if (!isValidPhone(cleanPhone)) return { ok: false, error: 'Enter a valid Nigerian phone number.' };
  if (!findUser(cleanPhone)) return { ok: false, error: 'No account found for that number.' };

  const code = String(Math.floor(100000 + Math.random() * 900000));
  store.set(OTP, { phone: cleanPhone, code, expires: Date.now() + 10 * 60 * 1000, attempts: 0 });
  return { ok: true, phone: cleanPhone, code };  // code returned for the demo banner only
}

export function verifyOtp(code) {
  const record = store.get(OTP);
  if (!record) return { ok: false, error: 'Request a new code.' };
  if (Date.now() > record.expires) return { ok: false, error: 'That code has expired. Request a new one.' };
  if (record.attempts >= 5) return { ok: false, error: 'Too many tries. Request a new code.' };
  if (String(code) !== record.code) {
    store.set(OTP, { ...record, attempts: record.attempts + 1 });
    return { ok: false, error: 'Incorrect code. Check your SMS and try again.' };
  }
  store.set(OTP, { ...record, verified: true });
  return { ok: true, phone: record.phone };
}

export function resetPassword(newPassword) {
  const record = store.get(OTP);
  if (!record?.verified) return { ok: false, error: 'Verify your phone number first.' };
  if (!newPassword || newPassword.length < 6) return { ok: false, error: 'Password must be at least 6 characters.' };

  store.set(USERS, allUsers().map((u) =>
    u.phone === record.phone ? { ...u, password: newPassword } : u
  ));
  store.remove(OTP);
  return { ok: true };
}

export const pendingOtpPhone = () => store.get(OTP)?.phone || null;

/** DEMO ONLY — lets the verify screen display the code there is no SMS for.
    Delete this together with the demo banner once an SMS gateway exists. */
export const peekOtp = () => store.get(OTP)?.code || null;

export const otpVerified = () => Boolean(store.get(OTP)?.verified);

/* --- Route guards --------------------------------------------------------- */

export const HOME_FOR = {
  student: 'student/dashboard.html',
  teacher: 'teacher/dashboard.html',
  parent:  'parent/dashboard.html',
};

/** Send a signed-in user to the right dashboard (or onboarding if unfinished). */
export function routeHome(user = currentUser()) {
  if (!user) return goReplace('auth/login.html');
  if (!user.onboarded) return goReplace('auth/onboarding.html');
  return goReplace(HOME_FOR[user.role] || HOME_FOR.student);
}

/**
 * Call at the top of every protected page.
 * @param {string|string[]} roles allowed role(s)
 * @returns the signed-in user, or null while redirecting
 */
export function requireAuth(roles) {
  const me = currentUser();
  if (!me) { goReplace('auth/login.html'); return null; }
  if (!me.onboarded) { goReplace('auth/onboarding.html'); return null; }
  const allowed = Array.isArray(roles) ? roles : [roles];
  if (roles && !allowed.includes(me.role)) { routeHome(me); return null; }
  return me;
}

/** Call on auth pages: bounce users who are already signed in. */
export function redirectIfSignedIn() {
  const me = currentUser();
  if (me) routeHome(me);
}
