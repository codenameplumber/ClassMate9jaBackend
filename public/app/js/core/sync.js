/* ==========================================================================
   sync.js — reconciles the device with the server, quietly.

   v1 is one student on one device, so progress syncs as a single blob with
   last-write-wins. That removes roughly ninety percent of the sync code an
   event log would need, and the event-log design is still the upgrade path if
   multi-device ever ships.
   ========================================================================== */

import * as api from './api.js';
import * as store from './store.js';
import { getProgress, saveProgress } from '../data/progress.js';

const LAST_SYNC = 'last-sync';
const QUEUE = 'sync-queue';

export const lastSyncedAt = () => store.get(LAST_SYNC, null);

/** Anything that failed to send is parked here and retried on reconnect. */
const queue = () => store.get(QUEUE, []) || [];
const enqueue = (item) => store.set(QUEUE, [...queue(), item].slice(-50));

export function queueAttempt(attempt) {
  enqueue({ kind: 'attempt', body: attempt });
}

export function queueSubmission(submission) {
  enqueue({ kind: 'submission', body: submission });
}

/**
 * Send now if we can, park it if we cannot.
 *
 * Callers never have to think about the network: they hand over a payload and
 * it either lands immediately or lands later. The only thing that never
 * happens is losing it.
 */
export async function sendAttempt(attempt) {
  if (!api.isLinked()) return { ok: false, reason: 'not-linked' };

  const res = await api.pushAttempt(attempt);
  if (res.ok) return { ok: true, queued: false };

  if (res.offline) {
    queueAttempt(attempt);
    return { ok: false, queued: true, reason: 'offline' };
  }

  return { ok: false, queued: false, reason: 'rejected' };
}

export async function sendSubmission(submission) {
  if (!api.isLinked()) return { ok: false, reason: 'not-linked' };

  const res = await api.pushSubmission(submission);
  if (res.ok) return { ok: true, queued: false };

  if (res.offline) {
    queueSubmission(submission);
    return { ok: false, queued: true, reason: 'offline' };
  }

  return { ok: false, queued: false, reason: 'rejected', data: res.data };
}

async function drainQueue() {
  const pending = queue();
  if (!pending.length) return 0;

  const left = [];
  let sent = 0;

  for (const item of pending) {
    const res = item.kind === 'attempt'
      ? await api.pushAttempt(item.body)
      : await api.pushSubmission(item.body);

    /* Anything the server rejected outright is dropped — retrying a 422
       forever would block every later item behind it. Only genuine network
       failures are kept. */
    if (res.ok) sent += 1;
    else if (res.offline) left.push(item);
  }

  store.set(QUEUE, left);

  return sent;
}

/**
 * Push local progress up, pull server content down.
 * Safe to call on every page load: it exits immediately when offline or when
 * this device has never been linked to an account.
 */
export async function sync({ pull = true } = {}) {
  if (!api.isLinked() || !navigator.onLine) {
    return { ok: false, reason: api.isLinked() ? 'offline' : 'not-linked' };
  }

  const drained = await drainQueue();

  const push = await api.pushState(getProgress());
  if (!push.ok) {
    return { ok: false, reason: push.offline ? 'offline' : 'rejected', drained };
  }

  let assignments = [];
  if (pull) {
    const boot = await api.bootstrap();
    if (boot.ok && boot.data) {
      store.set('server-content', {
        subjects: boot.data.subjects,
        contentVersion: boot.data.contentVersion,
      });
      assignments = boot.data.assignments || [];
      store.set('assignments', assignments);
    }

    /* Marked scripts coming back the other way. */
    const marked = await api.submissions();
    if (marked.ok && marked.data) {
      store.set('submissions', marked.data.submissions || []);
    }
  }

  store.set(LAST_SYNC, new Date().toISOString());

  return { ok: true, drained, assignments: assignments.length };
}

/** Fire-and-forget sync, used on page load and whenever the network returns. */
export function syncInBackground() {
  if (!api.isLinked()) return;

  const run = () => { sync().catch(() => { /* never surfaces to the student */ }); };

  if (navigator.onLine) setTimeout(run, 1200);
  window.addEventListener('online', run, { once: false });
}

/** Merge a server-side state blob into local progress after a fresh login. */
export function adoptServerState(payload) {
  if (payload && typeof payload === 'object') {
    saveProgress(payload);
  }
}
