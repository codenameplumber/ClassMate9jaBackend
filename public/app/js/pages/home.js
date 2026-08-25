/* home.js — landing page behaviour */

import { hydrateIcons, icon } from '../core/icons.js';
import { registerServiceWorker } from '../core/boot.js';
import { toggleTheme, applyTheme } from '../core/store.js';
import { $, el, sheet, toast } from '../core/ui.js';

applyTheme();
hydrateIcons();

/* Year in the footer */
const yr = $('#year');
if (yr) yr.textContent = String(new Date().getFullYear());

/* Theme toggle — swap the glyph so the button shows what you'd switch TO */
function paintThemeBtn() {
  const dark = document.documentElement.getAttribute('data-theme') === 'dark';
  $('#themeBtn').innerHTML = icon(dark ? 'sun' : 'moon');
}
paintThemeBtn();
$('#themeBtn').addEventListener('click', () => { toggleTheme(); paintThemeBtn(); });

/* Mobile menu — a bottom sheet rather than a full-screen overlay, so the
   thumb never has to travel to the top of the phone. */
$('#menuBtn')?.addEventListener('click', () => {
  const close = sheet(`
    <nav class="mob-nav" aria-label="Mobile">
      <a href="#how" data-close>How it works</a>
      <a href="#features" data-close>Features</a>
      <a href="#offline" data-close>Offline mode</a>
      <a href="#who" data-close>For schools</a>
    </nav>
    <div class="stack gap-3" style="margin-top:var(--s-5)">
      <a class="btn btn-primary btn-block" href="auth/signup.html">Start free</a>
      <a class="btn btn-secondary btn-block" href="auth/login.html">Log in</a>
    </div>
  `);
  // Anchor links inside a sheet must close it before the scroll happens.
  document.querySelectorAll('.mob-nav a').forEach((a) =>
    a.addEventListener('click', () => close())
  );
});

/* Reveal sections on scroll. Cheap: one observer, unobserves as it goes,
   and it is skipped entirely when the user prefers reduced motion. */
if (!matchMedia('(prefers-reduced-motion: reduce)').matches && 'IntersectionObserver' in window) {
  const targets = document.querySelectorAll('.feature, .step, .audience, .problem, .offline-band');
  targets.forEach((n) => {
    n.style.opacity = '0';
    n.style.transform = 'translateY(16px)';
    n.style.transition = 'opacity .5s var(--ease), transform .5s var(--ease)';
  });
  const io = new IntersectionObserver((entries) => {
    entries.forEach((e, i) => {
      if (!e.isIntersecting) return;
      setTimeout(() => {
        e.target.style.opacity = '1';
        e.target.style.transform = 'none';
      }, i * 60);
      io.unobserve(e.target);
    });
  }, { threshold: 0.12 });
  targets.forEach((n) => io.observe(n));
}

/* Same registration and update path as every other page — one implementation,
   so the landing page can never end up on a stale worker the app screens shed. */
registerServiceWorker();

/* Install to home screen. Chrome fires this only when the PWA criteria are met;
   until then we show nothing rather than a button that would do nothing. */
let installEvent = null;
window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  installEvent = e;

  const btn = el(`<button class="btn btn-secondary hide-mobile" id="installBtn">
      ${icon('download', 16)} Install app
    </button>`);
  $('#themeBtn').insertAdjacentElement('beforebegin', btn);

  btn.addEventListener('click', async () => {
    installEvent.prompt();
    const { outcome } = await installEvent.userChoice;
    if (outcome === 'accepted') btn.remove();
    installEvent = null;
  });
});

/* Say it out loud when the network drops — the whole pitch is that nothing
   breaks, and a silent page proves nothing. */
window.addEventListener('offline', () =>
  toast('Network gone. This page still works — that is the point.', 'warn', 4000));
window.addEventListener('online', () => toast('Back online', 'success'));
