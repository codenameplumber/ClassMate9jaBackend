/* ==========================================================================
   icons.js — inline SVG icon set.
   No icon font, no sprite fetch (fetch() dies on file:// anyway). Every icon
   is a 24x24 stroke path that inherits currentColor, so theming is free.
   ========================================================================== */

const P = {
  menu:        'M3 6h18M3 12h18M3 18h18',
  close:       'M18 6L6 18M6 6l12 12',
  chevronR:    'M9 5l7 7-7 7',
  chevronL:    'M15 5l-7 7 7 7',
  chevronD:    'M6 9l6 6 6-6',
  chevronU:    'M18 15l-6-6-6 6',
  arrowR:      'M5 12h14M13 6l6 6-6 6',
  arrowL:      'M19 12H5M11 18l-6-6 6-6',
  home:        'M3 10.5L12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z',
  book:        'M4 19.5A2.5 2.5 0 0 1 6.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z',
  sparkle:     'M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8zM18 15l.9 2.1L21 18l-2.1.9L18 21l-.9-2.1L15 18l2.1-.9z',
  chart:       'M3 20h18M7 20v-8M12 20V5M17 20v-6',
  download:    'M12 3v12M7 10l5 5 5-5M4 21h16',
  sliders:     'M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6',
  logout:      'M15 12H3M7 8l-4 4 4 4M14 4h5a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-5',
  user:        'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2',
  users:       'M17 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M22 21v-2a4 4 0 0 0-3-3.9',
  bell:        'M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0',
  search:      'M21 21l-4.3-4.3',
  eye:         'M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z',
  eyeOff:      'M17.9 17.9A10.6 10.6 0 0 1 12 19C5 19 1 12 1 12a19 19 0 0 1 5.1-5.9M9.9 4.2A10.9 10.9 0 0 1 12 4c7 0 11 8 11 8a19 19 0 0 1-2.2 3.2M1 1l22 22M9.9 9.9a3 3 0 0 0 4.2 4.2',
  check:       'M20 6L9 17l-5-5',
  clock:       'M12 7v5l3 2',
  flag:        'M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1zM4 22v-7',
  send:        'M22 2L11 13M22 2l-7 20-4-9-9-4z',
  trophy:      'M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0zM7 6H4a2 2 0 0 0 0 4h3M17 6h3a2 2 0 0 1 0 4h-3',
  calendar:    'M3 5h18v16H3zM3 10h18M8 3v4M16 3v4',
  play:        'M6 4l14 8-14 8z',
  lock:        'M7 11V7a5 5 0 0 1 10 0v4',
  mail:        'M3 5h18v14H3zM3 6l9 6 9-6',
  phone:       'M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z',
  shield:      'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z',
  plus:        'M12 5v14M5 12h14',
  minus:       'M5 12h14',
  edit:        'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z',
  refresh:     'M21 4v6h-6M3 20v-6h6M3.5 9a9 9 0 0 1 14.9-3.4L21 8M20.5 15a9 9 0 0 1-14.9 3.4L3 16',
  star:        'M12 2l3 6.3 6.9 1-5 4.9 1.2 6.9L12 17.8 5.9 21l1.2-6.9-5-4.9 6.9-1z',
  message:     'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z',
  alert:       'M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4M12 17h.01',
  info:        'M12 16v-4M12 8h.01',
  moon:        'M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z',
  sun:         'M12 1v2M12 21v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M1 12h2M21 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4',
  wifiOff:     'M1 1l22 22M16.7 12.7A6 6 0 0 1 19 14M5 12.5a10 10 0 0 1 5.2-2.7M10.7 5.1A16 16 0 0 1 22.6 9M1.4 9a16 16 0 0 1 4-2.9M8.5 16.4a5 5 0 0 1 7 0M12 20h.01',
  wifi:        'M5 12.5a10 10 0 0 1 14 0M1.4 9a16 16 0 0 1 21.2 0M8.5 16a5 5 0 0 1 7 0M12 20h.01',
  target:      'M12 12h.01',
  layers:      'M12 2L2 7l10 5 10-5zM2 17l10 5 10-5M2 12l10 5 10-5',
  clipboard:   'M9 3h6v3H9zM8 4.5H6a1 1 0 0 0-1 1V20a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V5.5a1 1 0 0 0-1-1h-2',
  file:        'M14 2H6a1 1 0 0 0-1 1v18a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7zM14 2v5h5',
  pause:       'M7 4h3v16H7zM14 4h3v16h-3z',
  volume:      'M11 5L6 9H2v6h4l5 4zM16.5 8.5a5 5 0 0 1 0 7',
  grid:        'M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z',
  bookmark:    'M19 21l-7-5-7 5V4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1z',
  zap:         'M13 2L3 14h8l-1 8 10-12h-8z',
  print:       'M6 9V2h12v7M6 18H4a1 1 0 0 1-1-1v-5a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1h-2M6 14h12v8H6z',
  external:    'M18 13v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h6M15 3h6v6M10 14L21 3',
};

/* Icons needing a circle in addition to (or instead of) a path. */
const CIRCLES = {
  search:  [11, 11, 8],
  user:    [12, 7, 4],
  users:   [9, 7, 4],
  clock:   [12, 12, 10],
  eye:     [12, 12, 3],
  info:    [12, 12, 10],
  target:  [12, 12, 10],
  sun:     [12, 12, 4],
};

/* Icons needing a rect (lock body). */
const RECTS = {
  lock: [3, 11, 18, 11, 2],
};

/**
 * Return an inline SVG string for `name`.
 * @param {string} name  key from the set above
 * @param {number} size  px, defaults to 20 (the app's nav/button size)
 */
export function icon(name, size = 20) {
  const d = P[name];
  if (!d && !CIRCLES[name]) return '';
  const c = CIRCLES[name]
    ? `<circle cx="${CIRCLES[name][0]}" cy="${CIRCLES[name][1]}" r="${CIRCLES[name][2]}"/>`
    : '';
  const r = RECTS[name]
    ? `<rect x="${RECTS[name][0]}" y="${RECTS[name][1]}" width="${RECTS[name][2]}" height="${RECTS[name][3]}" rx="${RECTS[name][4]}"/>`
    : '';
  return `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 24 24"
    fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"
    stroke-linejoin="round" aria-hidden="true">${r}${c}${d ? `<path d="${d}"/>` : ''}</svg>`;
}

/** Swap every <i data-icon="name" data-size="20"> in the document for real SVG. */
export function hydrateIcons(root = document) {
  root.querySelectorAll('[data-icon]').forEach((node) => {
    const svg = icon(node.dataset.icon, Number(node.dataset.size) || 20);
    if (svg) node.innerHTML = svg;
  });
}
