/* ==========================================================================
   ui.js — DOM helpers and the overlay primitives (toast, modal, sheet).
   Deliberately tiny: this whole file is the "framework".
   ========================================================================== */

export const $  = (sel, root = document) => root.querySelector(sel);
export const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

/** Escape untrusted text before it goes near innerHTML. */
export function esc(str = '') {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

/** Build an element from an HTML string. */
export function el(html) {
  const t = document.createElement('template');
  t.innerHTML = html.trim();
  return t.content.firstElementChild;
}

/** Replace a container's contents with an HTML string. */
export function render(target, html) {
  const node = typeof target === 'string' ? $(target) : target;
  if (node) node.innerHTML = html;
  return node;
}

/* --- Toast --------------------------------------------------------------- */

function toastHost() {
  let host = $('.toast-host');
  if (!host) {
    host = el('<div class="toast-host" role="status" aria-live="polite"></div>');
    document.body.appendChild(host);
  }
  return host;
}

/**
 * @param {string} message
 * @param {'default'|'success'|'danger'|'warn'} type
 */
export function toast(message, type = 'default', ms = 2800) {
  const node = el(
    `<div class="toast ${type !== 'default' ? `toast-${type}` : ''}">
       <span>${esc(message)}</span>
     </div>`
  );
  toastHost().appendChild(node);
  setTimeout(() => {
    node.classList.add('is-out');
    setTimeout(() => node.remove(), 220);
  }, ms);
  return node;
}

/* --- Modal --------------------------------------------------------------- */

let openOverlay = null;

/**
 * Open a modal. `body` is raw HTML for the modal interior.
 * Returns a close() function. Escape and backdrop-click both close.
 */
export function modal(body, { wide = false, dismissible = true } = {}) {
  closeOverlay();
  const overlay = el(
    `<div class="overlay" role="dialog" aria-modal="true">
       <div class="modal ${wide ? 'modal-lg' : ''}">${body}</div>
     </div>`
  );
  document.body.appendChild(overlay);
  document.body.style.overflow = 'hidden';
  openOverlay = overlay;

  if (dismissible) {
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) closeOverlay();
    });
  }
  overlay.querySelectorAll('[data-close]').forEach((b) =>
    b.addEventListener('click', closeOverlay)
  );
  return closeOverlay;
}

/** Bottom sheet — the mobile-native equivalent of a modal. */
export function sheet(body) {
  closeOverlay();
  const host = el(
    `<div class="sheet-host" role="dialog" aria-modal="true">
       <div class="sheet"><div class="grabber"></div>${body}</div>
     </div>`
  );
  document.body.appendChild(host);
  document.body.style.overflow = 'hidden';
  openOverlay = host;
  host.addEventListener('click', (e) => { if (e.target === host) closeOverlay(); });
  host.querySelectorAll('[data-close]').forEach((b) =>
    b.addEventListener('click', closeOverlay)
  );
  return closeOverlay;
}

export function closeOverlay() {
  if (!openOverlay) return;
  openOverlay.remove();
  openOverlay = null;
  document.body.style.overflow = '';
}

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') closeOverlay();
});

/** Promise-based confirm. Resolves true/false. */
export function confirmDialog({
  title, message, confirmText = 'Confirm', cancelText = 'Cancel', danger = false,
}) {
  return new Promise((resolve) => {
    const close = modal(
      `<h3 style="margin-bottom:var(--s-2)">${esc(title)}</h3>
       <p class="muted t-sm" style="margin-bottom:var(--s-6)">${esc(message)}</p>
       <div class="row gap-3">
         <button class="btn btn-secondary grow" data-act="no">${esc(cancelText)}</button>
         <button class="btn ${danger ? 'btn-danger' : 'btn-primary'} grow" data-act="yes">${esc(confirmText)}</button>
       </div>`
    );
    $('[data-act="no"]').addEventListener('click', () => { close(); resolve(false); });
    $('[data-act="yes"]').addEventListener('click', () => { close(); resolve(true); });
  });
}

/* --- Progress ring (SVG, no chart library) -------------------------------- */

/**
 * @param {number} pct    0-100
 * @param {object} opts   size, stroke, label (HTML), colour (CSS colour)
 */
export function ring(pct, { size = 96, stroke = 9, label = '', colour = 'var(--primary)' } = {}) {
  const r = (size - stroke) / 2;
  const circ = 2 * Math.PI * r;
  const offset = circ * (1 - Math.max(0, Math.min(100, pct)) / 100);
  return `<div class="ring" style="width:${size}px;height:${size}px">
    <svg width="${size}" height="${size}">
      <circle class="ring-track" cx="${size / 2}" cy="${size / 2}" r="${r}"
              fill="none" stroke-width="${stroke}"/>
      <circle class="ring-fill" cx="${size / 2}" cy="${size / 2}" r="${r}"
              fill="none" stroke-width="${stroke}" stroke="${colour}"
              stroke-dasharray="${circ.toFixed(1)}" stroke-dashoffset="${offset.toFixed(1)}"/>
    </svg>
    <div class="ring-label">${label}</div>
  </div>`;
}

/* --- Formatting ----------------------------------------------------------- */

export function initials(name = '') {
  return name.trim().split(/\s+/).slice(0, 2).map((w) => w[0] || '').join('').toUpperCase();
}

/** 95 -> "1h 35m", 40 -> "40m" */
export function humanMins(mins = 0) {
  const h = Math.floor(mins / 60);
  const m = Math.round(mins % 60);
  return h ? `${h}h ${m}m` : `${m}m`;
}

/** Seconds -> "24:09" for exam countdowns. */
export function clock(seconds = 0) {
  const s = Math.max(0, Math.floor(seconds));
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const sec = s % 60;
  const pad = (n) => String(n).padStart(2, '0');
  return h ? `${h}:${pad(m)}:${pad(sec)}` : `${pad(m)}:${pad(sec)}`;
}

/** WAEC-style grade band from a percentage. */
export function grade(pct) {
  if (pct >= 75) return { band: 'A1', note: 'Excellent', tone: 'success' };
  if (pct >= 70) return { band: 'B2', note: 'Very Good', tone: 'success' };
  if (pct >= 65) return { band: 'B3', note: 'Good',      tone: 'success' };
  if (pct >= 60) return { band: 'C4', note: 'Credit',    tone: 'brand' };
  if (pct >= 55) return { band: 'C5', note: 'Credit',    tone: 'brand' };
  if (pct >= 50) return { band: 'C6', note: 'Credit',    tone: 'brand' };
  if (pct >= 45) return { band: 'D7', note: 'Pass',      tone: 'warn' };
  if (pct >= 40) return { band: 'E8', note: 'Pass',      tone: 'warn' };
  return { band: 'F9', note: 'Fail', tone: 'danger' };
}

export function pluralise(n, word, plural = `${word}s`) {
  return `${n} ${n === 1 ? word : plural}`;
}
