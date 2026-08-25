/* ==========================================================================
   sw.js — the offline promise, kept.

   Caching strategy, and why it is split:

     • Code (HTML, JS, CSS, JSON) is NETWORK-FIRST with a cache fallback.
       Cache-first on code means a shipped bug is served forever: the browser
       never asks the server again, so the fix never arrives. Network-first
       costs a few KB when online and still opens instantly with no network.

     • Everything else (icons, images, fonts) is CACHE-FIRST. Those are the
       heavy, unchanging bytes, and on a metered ₦-per-MB connection we must
       never pay for the same image twice.

   Bump VERSION on any release that changes cached files. Old caches are
   deleted on activate, so a bump is a clean slate.
   ========================================================================== */

const VERSION = 'cm9ja-v3';
const SHELL = `${VERSION}-shell`;

/** Code assets: always revalidate against the network when one is reachable. */
const IS_CODE = /\.(?:html|js|css|json|mjs)(?:$|\?)/i;

const PRECACHE = [
  'index.html',
  'manifest.json',
  'css/tokens.css',
  'css/base.css',
  'css/components.css',
  'css/layout.css',
  'css/pages/home.css',
  'css/pages/app.css',
  'js/core/store.js',
  'js/core/paths.js',
  'js/core/icons.js',
  'js/core/ui.js',
  'js/core/auth.js',
  'js/core/api.js',
  'js/core/sync.js',
  'js/core/boot.js',
  'js/components/shell.js',
  'js/components/charts.js',
  'js/data/subjects.js',
  'js/data/scheme.js',
  'js/data/questions.js',
  'js/data/progress.js',
  'js/data/tutor.js',
  'js/pages/home.js',
  'js/pages/login.js',
  'js/pages/signup.js',
  'js/pages/forgot-password.js',
  'js/pages/onboarding.js',
  'js/pages/student-dashboard.js',
  'js/pages/student-subjects.js',
  'js/pages/student-scheme.js',
  'js/pages/student-lesson.js',
  'js/pages/student-tutor.js',
  'js/pages/student-practice.js',
  'js/pages/student-exam.js',
  'js/pages/student-results.js',
  'js/pages/student-progress.js',
  'js/pages/student-downloads.js',
  'js/pages/student-settings.js',
  'js/pages/student-assignments.js',
  'js/pages/student-assignment-runner.js',
  'auth/login.html',
  'auth/signup.html',
  'auth/forgot-password.html',
  'auth/onboarding.html',
  'student/dashboard.html',
  'student/subjects.html',
  'student/scheme.html',
  'student/lesson.html',
  'student/tutor.html',
  'student/practice.html',
  'student/exam.html',
  'student/results.html',
  'student/progress.html',
  'student/downloads.html',
  'student/settings.html',
  'student/assignments.html',
  'assets/icons/icon.svg',
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

async function networkFirst(request) {
  try {
    const res = await fetch(request);
    if (res && res.ok) {
      const copy = res.clone();
      caches.open(SHELL).then((c) => c.put(request, copy));
    }
    return res;
  } catch {
    const hit = await caches.match(request);
    if (hit) return hit;
    if (request.mode === 'navigate') return caches.match('index.html');
    throw new Error('offline and not cached');
  }
}

async function cacheFirst(request) {
  const hit = await caches.match(request);
  if (hit) return hit;
  const res = await fetch(request);
  if (res && res.ok) {
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

  if (request.mode === 'navigate' || IS_CODE.test(url.pathname)) {
    event.respondWith(networkFirst(request));
    return;
  }
  event.respondWith(cacheFirst(request));
});
