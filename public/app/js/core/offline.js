/* ==========================================================================
   offline.js — what is actually on this phone.

   The downloads screen used to be theatre: a setInterval incremented a
   percentage with Math.random(), then marked the pack "ready". Nothing was
   fetched and nothing was stored, so a student who trusted it and then lost
   signal found an empty app. For a product whose whole claim is that it works
   with the data off, that was the worst possible thing to fake.

   This is the real version. A subject is offline when two things are true:

     1. Its lessons and questions are in local storage, which is where
        /api/bootstrap puts them and where every screen reads them from.
     2. The screens and code are in the service worker's cache, which is what
        lets the app open at all with no network.

   Both are measurable, so nothing here estimates anything.
   ========================================================================== */

import * as store from './store.js';

const CONTENT = 'server-content';
const BANK = 'question-bank';

/** Bytes of a value as it sits in local storage. */
function sizeOf(value) {
  try {
    return new Blob([JSON.stringify(value)]).size;
  } catch {
    return JSON.stringify(value ?? '').length;
  }
}

export function human(bytes = 0) {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;

  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

const content = () => store.get(CONTENT, null);
const bank = () => store.get(BANK, []) || [];

/** The synced record for one subject, or null if it was never downloaded. */
export function subjectPack(slug) {
  return (content()?.subjects || []).find((s) => s.slug === slug) || null;
}

/**
 * Is this subject genuinely usable with no network?
 *
 * Topics alone are a table of contents. A pack is only ready when the lessons
 * are there too — otherwise the student opens a topic offline and finds
 * nothing behind it, which is exactly the failure this screen exists to
 * prevent.
 */
export function packStatus(slug) {
  const pack = subjectPack(slug);

  if (!pack || !pack.topics?.length) return 'missing';

  const withLessons = pack.topics.filter((t) => Object.keys(t.lessons || {}).length).length;

  if (withLessons === 0) return 'missing';

  return withLessons < pack.topics.length ? 'partial' : 'ready';
}

/** Real bytes this subject occupies, lessons, classwork and questions included. */
export function packSize(slug) {
  const pack = subjectPack(slug);
  if (!pack) return 0;

  return sizeOf(pack) + sizeOf(bank().filter((q) => q.subject === slug));
}

export function packTopics(slug) {
  return subjectPack(slug)?.topics?.length || 0;
}

export function packQuestions(slug) {
  return bank().filter((q) => q.subject === slug).length;
}

/** Everything currently held, across every subject. */
export function totalSize() {
  return sizeOf(content()) + sizeOf(bank());
}

/**
 * How much room the browser has actually given us.
 *
 * Returns null where the browser will not say — older Android WebViews among
 * them — and the screen then shows what is stored without pretending to know
 * the free space. The old code invented a 2048 MB device.
 */
export async function deviceStorage() {
  try {
    if (!navigator.storage?.estimate) return null;

    const { usage = 0, quota = 0 } = await navigator.storage.estimate();

    return quota ? { usage, quota } : null;
  } catch {
    return null;
  }
}

/** Are the screens themselves cached, or does opening the app still need network? */
export async function shellCached() {
  try {
    if (!('caches' in window)) return false;

    const hit = await caches.match('/learn/dashboard');

    return Boolean(hit);
  } catch {
    return false;
  }
}

/**
 * Download everything this student is enrolled in.
 *
 * One request brings every subject at once, because that is the shape of
 * /api/bootstrap — there is no per-subject endpoint and inventing one to make
 * a progress bar look busier would cost the student data, not save it.
 *
 * `onStage` is called with a short label so the screen can say what is
 * happening instead of animating a number.
 */
export async function downloadAll(onStage = () => {}) {
  if (!navigator.onLine) {
    return { ok: false, reason: 'offline' };
  }

  onStage('Fetching your lessons…');

  const { sync } = await import('./sync.js');
  const res = await sync({ pull: true });

  if (!res.ok) {
    return { ok: false, reason: res.reason || 'failed' };
  }

  onStage('Saving the app for offline use…');

  await cacheShell();

  onStage('Done');

  return { ok: true, bytes: totalSize() };
}

/**
 * Ask the browser to keep the screens themselves.
 *
 * The service worker precaches these on install, but a student who installed
 * before a screen existed will not have it. Re-requesting them while online is
 * enough: the worker is network-first for code and stores what it sees.
 */
export async function cacheShell() {
  const screens = [
    '/learn/dashboard', '/learn/subjects', '/learn/scheme', '/learn/lesson',
    '/learn/practice', '/learn/exam', '/learn/tutor', '/learn/assignments',
    '/learn/results', '/learn/progress', '/learn/downloads', '/learn/settings',
  ];

  try {
    await Promise.all(screens.map((url) =>
      fetch(url, { credentials: 'same-origin' }).catch(() => null)));

    return true;
  } catch {
    return false;
  }
}

/**
 * Forget one subject's content.
 *
 * Only the content: the student stays enrolled, and the next sync brings it
 * back. This is for a phone that is out of room, not for dropping a subject.
 */
export function removePack(slug) {
  const held = content();

  if (held?.subjects) {
    store.set(CONTENT, {
      ...held,
      subjects: held.subjects.filter((s) => s.slug !== slug),
    });
  }

  store.set(BANK, bank().filter((q) => q.subject !== slug));

  return packStatus(slug);
}
