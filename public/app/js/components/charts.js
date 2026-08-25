/* ==========================================================================
   charts.js — hand-rolled SVG charts.
   A charting library would cost 80–200 KB over a metered connection to draw
   two shapes. These are ~60 lines, render offline, and inherit theme tokens.
   ========================================================================== */

import { esc } from '../core/ui.js';

/**
 * Vertical bar chart.
 * @param {{label:string, value:number}[]} data
 */
export function barChart(data, { height = 160, colour = 'var(--primary)', unit = 'm' } = {}) {
  const max = Math.max(...data.map((d) => d.value), 1);
  const w = 100 / data.length;

  return `
  <div class="stack gap-2">
    <div style="display:flex;align-items:flex-end;gap:2%;height:${height}px">
      ${data.map((d) => {
        const h = Math.max(2, Math.round((d.value / max) * 100));
        const strong = d.value === max && max > 0;
        return `
        <div style="flex:1;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;height:100%;gap:6px"
             title="${esc(d.label)}: ${d.value}${unit}">
          <span class="t-2xs tnum ${strong ? 'fw-bold brand' : 'subtle'}">${d.value || ''}</span>
          <div style="width:100%;height:${h}%;border-radius:var(--r-xs) var(--r-xs) 2px 2px;
                      background:${strong ? colour : 'var(--green-200)'};
                      transition:height var(--dur-slow) var(--ease)"></div>
        </div>`;
      }).join('')}
    </div>
    <div style="display:flex;gap:2%">
      ${data.map((d) => `
        <div style="flex:1;text-align:center" class="t-2xs subtle">${esc(d.label)}</div>`).join('')}
    </div>
  </div>`;
}

/**
 * Line chart with dots — used for the exam-score trend.
 * @param {{label:string, value:number}[]} data  values are percentages
 */
export function lineChart(data, { height = 170, colour = 'var(--primary)' } = {}) {
  if (data.length < 2) {
    return `<div class="empty t-sm">Take two or more papers to see your trend.</div>`;
  }

  const W = 300;
  const H = 100;
  const pad = 6;
  const step = (W - pad * 2) / (data.length - 1);
  const y = (v) => H - pad - (Math.max(0, Math.min(100, v)) / 100) * (H - pad * 2);
  const pts = data.map((d, i) => [pad + i * step, y(d.value)]);
  const path = pts.map((p, i) => `${i ? 'L' : 'M'}${p[0].toFixed(1)},${p[1].toFixed(1)}`).join(' ');
  const area = `${path} L${pts[pts.length - 1][0].toFixed(1)},${H - pad} L${pts[0][0].toFixed(1)},${H - pad} Z`;

  return `
  <div class="stack gap-2">
    <svg viewBox="0 0 ${W} ${H}" style="width:100%;height:${height}px" preserveAspectRatio="none"
         role="img" aria-label="Score trend">
      ${[25, 50, 75].map((g) => `
        <line x1="${pad}" x2="${W - pad}" y1="${y(g)}" y2="${y(g)}"
              stroke="var(--border)" stroke-width="1" stroke-dasharray="3 4"/>`).join('')}
      <line x1="${pad}" x2="${W - pad}" y1="${y(50)}" y2="${y(50)}"
            stroke="var(--amber-400)" stroke-width="1.2"/>
      <path d="${area}" fill="${colour}" opacity=".10"/>
      <path d="${path}" fill="none" stroke="${colour}" stroke-width="2.2"
            stroke-linejoin="round" stroke-linecap="round"/>
      ${pts.map((p, i) => `
        <circle cx="${p[0].toFixed(1)}" cy="${p[1].toFixed(1)}" r="3.4"
                fill="var(--bg-elevated)" stroke="${colour}" stroke-width="2">
          <title>${esc(data[i].label)}: ${data[i].value}%</title>
        </circle>`).join('')}
    </svg>
    <div style="display:flex">
      ${data.map((d) => `<div style="flex:1;text-align:center" class="t-2xs subtle">${esc(d.label)}</div>`).join('')}
    </div>
    <div class="t-2xs subtle row gap-2">
      <span style="width:14px;height:2px;background:var(--amber-400);display:inline-block"></span>
      50% credit line
    </div>
  </div>`;
}

/** Horizontal comparison bars — subject mastery at a glance. */
export function masteryBars(rows) {
  return `<div class="stack gap-4">
    ${rows.map((r) => {
      const cls = r.value >= 70 ? '' : r.value >= 50 ? 'amber' : 'danger';
      return `
      <div>
        <div class="row between t-sm" style="margin-bottom:6px">
          <span class="row gap-2 fw-medium">${r.emoji || ''} ${esc(r.label)}</span>
          <span class="subtle tnum">${r.value}%</span>
        </div>
        <div class="bar"><i class="${cls}" style="width:${r.value}%"></i></div>
      </div>`;
    }).join('')}
  </div>`;
}
