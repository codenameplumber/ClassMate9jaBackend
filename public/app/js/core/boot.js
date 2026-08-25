/* ==========================================================================
   boot.js — the three lines every page needs, in one call.
   ========================================================================== */

import { applyTheme } from './store.js';
import { hydrateIcons } from './icons.js';
import { $$ } from './ui.js';

/** Apply theme, swap icon placeholders, stamp the year, register the SW. */
export function boot() {
  applyTheme();
  hydrateIcons();
  $$('.js-year').forEach((n) => { n.textContent = String(new Date().getFullYear()); });

  registerServiceWorker();

  /* Reconcile with the server whenever this device happens to have network.
     Silent by design: a failed sync is a normal Tuesday, not an error. */
  import('./sync.js')
    .then((sync) => sync.syncInBackground())
    .catch(() => { /* running fully standalone */ });
}

/* --------------------------------------------------------------------------
   Service worker registration, with an update path.

   A cached app that cannot be updated is worse than no cache at all: a bug
   shipped once would be served forever. So when a new worker takes control we
   reload exactly once, guarded by a session flag so it can never loop.
   -------------------------------------------------------------------------- */
export function registerServiceWorker() {
  if (!('serviceWorker' in navigator) || !location.protocol.startsWith('http')) return;

  const swUrl = new URL('../../sw.js', import.meta.url).href;

  navigator.serviceWorker.register(swUrl).then((reg) => {
    reg.update().catch(() => {});
    reg.addEventListener('updatefound', () => {
      const fresh = reg.installing;
      if (!fresh) return;
      fresh.addEventListener('statechange', () => {
        if (fresh.state === 'installed' && navigator.serviceWorker.controller) {
          fresh.postMessage('SKIP_WAITING');
        }
      });
    });
  }).catch(() => { /* offline, or SW unsupported — the app still runs */ });

  navigator.serviceWorker.addEventListener('controllerchange', () => {
    try {
      if (sessionStorage.getItem('cm9ja:reloaded')) return;
      sessionStorage.setItem('cm9ja:reloaded', '1');
    } catch { /* private mode — accept the small risk of no reload */ }
    location.reload();
  });
}

/** Show a field-level error under a form. */
export function showError(node, message) {
  if (!node) return;
  node.textContent = message;
  node.classList.remove('hidden');
}

export function clearError(node) {
  if (!node) return;
  node.textContent = '';
  node.classList.add('hidden');
}

/** Wire a password show/hide button to its input. */
export function wirePasswordToggle(buttonSel, inputSel, iconFn) {
  const btn = document.querySelector(buttonSel);
  const input = document.querySelector(inputSel);
  if (!btn || !input) return;
  btn.addEventListener('click', () => {
    const hidden = input.type === 'password';
    input.type = hidden ? 'text' : 'password';
    btn.setAttribute('aria-label', hidden ? 'Hide password' : 'Show password');
    btn.innerHTML = iconFn(hidden ? 'eyeOff' : 'eye', 18);
    input.focus();
  });
}
