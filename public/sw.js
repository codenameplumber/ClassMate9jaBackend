/* ==========================================================================
   sw.js — the offline promise, kept.

   Served from the site root, not from /app, and that is load-bearing: a
   worker's scope is the directory it is served from, and the student screens
   are Laravel routes at /learn/* now. Registered from /app it would control
   the stylesheets and none of the pages, which is the half that matters.

   Caching strategy, and why it is split:

     • Code (HTML, JS, CSS, JSON) and navigations are NETWORK-FIRST with a
       cache fallback. Cache-first on code means a shipped bug is served
       forever: the browser never asks the server again, so the fix never
       arrives. Network-first costs a few KB when online and still opens
       instantly with no network.

     • Everything else (icons, images, fonts) is CACHE-FIRST. Those are the
       heavy, unchanging bytes, and on a metered naira-per-MB connection we
       must never pay for the same image twice.

   Bump VERSION on any release that changes cached files. Old caches are
   deleted on activate, so a bump is a clean slate.
   ========================================================================== */

const VERSION = 'cm9ja-v4';
const SHELL = `${VERSION}-shell`;

/** Code assets: always revalidate against the network when one is reachable. */
const IS_CODE = /\.(?:html|js|css|json|mjs)(?:$|\?)/i;

/** Where an offline navigation to an uncached screen lands. */
const FALLBACK = '/learn/dashboard';

const PRECACHE = [
  '/app/manifest.json',
  '/app/css/tokens.css',
  '/app/css/base.css',
  '/app/css/components.css',
  '/app/css/layout.css',
  '/app/css/pages/home.css',
  '/app/css/pages/app.css',
  '/app/js/core/store.js',
  '/app/js/core/paths.js',
  '/app/js/core/icons.js',
  '/app/js/core/ui.js',
  '/app/js/core/auth.js',
  '/app/js/core/api.js',
  '/app/js/core/sync.js',
  '/app/js/core/boot.js',
  '/app/js/components/shell.js',
  '/app/js/components/charts.js',
  '/app/js/data/subjects.js',
  '/app/js/data/scheme.js',
  '/app/js/data/questions.js',
  '/app/js/data/progress.js',
  '/app/js/data/tutor.js',
  '/app/js/pages/student-dashboard.js',
  '/app/js/pages/student-subjects.js',
  '/app/js/pages/student-scheme.js',
  '/app/js/pages/student-lesson.js',
  '/app/js/pages/student-tutor.js',
  '/app/js/pages/student-practice.js',
  '/app/js/pages/student-exam.js',
  '/app/js/pages/student-results.js',
  '/app/js/pages/student-progress.js',
  '/app/js/pages/student-downloads.js',
  '/app/js/pages/student-settings.js',
  '/app/js/pages/student-assignments.js',
  '/app/js/pages/student-assignment-runner.js',
  '/app/assets/icons/icon.svg',
  '/learn/dashboard',
  '/learn/subjects',
  '/learn/scheme',
  '/learn/lesson',
  '/learn/tutor',
  '/learn/practice',
  '/learn/exam',
  '/learn/results',
  '/learn/progress',
  '/learn/downloads',
  '/learn/settings',
  '/learn/assignments',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(SHELL)
      .then((cache) =>
        // addAll() is all-or-nothing; add individually so one missing file
        // never leaves the student with no offline app at all.
        Promise.all(PRECACHE.map((url) =>
          cache.add(new Request(url, { cache: 'reload' })).catch(() => null)
        ))
      )
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys.filter((k) => !k.startsWith(VERSION)).map((k) => caches.delete(k))
      ))
      .then(() => self.clients.claim())
  );
});

/** Lets the page ask a waiting worker to take over immediately. */
self.addEventListener('message', (event) => {
  if (event.data === 'SKIP_WAITING') self.skipWaiting();
});

/**
 * Is this response safe to keep?
 *
 * The screens sit behind a session now, so a signed-out request for
 * /learn/dashboard comes back as the login page. Caching that under the
 * dashboard's URL would pin a student to a login screen every time they opened
 * the app offline — and clearing it would mean clearing site data. A redirect
 * is therefore never stored.
 */
function isCacheable(request, response) {
  return response
    && response.ok
    && response.type === 'basic'
    && !response.redirected
    && new URL(response.url).pathname === new URL(request.url).pathname;
}

async function networkFirst(request) {
  try {
    const res = await fetch(request);

    if (isCacheable(request, res)) {
      const copy = res.clone();
      caches.open(SHELL).then((c) => c.put(request, copy));
    }

    return res;
  } catch {
    const hit = await caches.match(request);
    if (hit) return hit;

    if (request.mode === 'navigate') {
      const fallback = await caches.match(FALLBACK);
      if (fallback) return fallback;
    }

    throw new Error('offline and not cached');
  }
}

async function cacheFirst(request) {
  const hit = await caches.match(request);
  if (hit) return hit;

  const res = await fetch(request);

  if (isCacheable(request, res)) {
    const copy = res.clone();
    caches.open(SHELL).then((c) => c.put(request, copy));
  }

  return res;
}

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;   // never touch third parties

  /* The API is never cached. A stale assignment list or a stale mark is worse
     than no answer at all — api.js already treats a failed call as "not synced
     yet" and carries on. */
  if (url.pathname.startsWith('/api/')) return;

  /* Neither is anything that signs a person in or out. */
  if (url.pathname === '/login' || url.pathname === '/logout') return;

  if (request.mode === 'navigate' || IS_CODE.test(url.pathname)) {
    event.respondWith(networkFirst(request));

    return;
  }

  event.respondWith(cacheFirst(request));
});
