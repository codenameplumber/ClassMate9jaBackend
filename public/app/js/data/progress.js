/* ==========================================================================
   progress.js — the student's learning record.
   Seeded with a believable 3-week history so every screen has something to
   show on first open; from then on the app writes real values into it.
   ========================================================================== */

import * as store from '../core/store.js';

const KEY = 'progress';

/* Level titles climb in a way a Nigerian student would actually say out loud. */
export const LEVELS = [
  { level: 1, title: 'Fresher',      at: 0 },
  { level: 2, title: 'Learner',      at: 400 },
  { level: 3, title: 'Sharp One',    at: 900 },
  { level: 4, title: 'Bookworm',     at: 1500 },
  { level: 5, title: 'Scholar',      at: 2200 },
  { level: 6, title: 'Class Captain', at: 3000 },
  { level: 7, title: 'Guru',         at: 4000 },
  { level: 8, title: 'Prof',         at: 5500 },
];

export const BADGES = [
  { id: 'first-lesson', emoji: '🎯', name: 'First Step',      hint: 'Finish your first lesson' },
  { id: 'streak-7',     emoji: '🔥', name: 'Week Warrior',    hint: 'Study 7 days in a row' },
  { id: 'streak-30',    emoji: '🏔️', name: 'Consistency King', hint: 'Study 30 days in a row' },
  { id: 'exam-pass',    emoji: '✅', name: 'Credit Pass',     hint: 'Score 50% or more in an exam' },
  { id: 'exam-a1',      emoji: '🏆', name: 'A1 Material',     hint: 'Score 75% or more in an exam' },
  { id: 'night-owl',    emoji: '🌙', name: 'Night Reader',    hint: 'Study after 10pm' },
  { id: 'past-100',     emoji: '📚', name: 'Century',         hint: 'Answer 100 past questions' },
  { id: 'offline-hero', emoji: '📴', name: 'Offline Hero',    hint: 'Complete a lesson with no network' },
];

const DEFAULTS = {
  points: 2340,
  streak: 12,
  bestStreak: 14,
  minutesToday: 24,
  questionsAnswered: 386,
  lessonsDone: 27,
  /* Mon → Sun study minutes for the current week */
  week: [35, 50, 20, 65, 40, 15, 24],
  subjects: {
    maths:     { mastery: 74, done: 17, total: 24, weak: ['Logarithms', 'Bearings'] },
    english:   { mastery: 81, done: 19, total: 24, weak: ['Oral English'] },
    physics:   { mastery: 68, done: 14, total: 24, weak: ['Electric Fields', 'Waves'] },
    chemistry: { mastery: 41, done: 8,  total: 24, weak: ['Mole Concept', 'Organic Chemistry', 'Redox'] },
    biology:   { mastery: 63, done: 12, total: 24, weak: ['Genetics'] },
  },
  badges: ['first-lesson', 'streak-7', 'exam-pass', 'offline-hero'],
  /* Topic keys already completed, as "subject:topicId" */
  doneTopics: [
    'physics:w1t1', 'physics:w1t2', 'physics:w2t1', 'physics:w2t2', 'physics:w3t1', 'physics:w3t2',
    'maths:w1t1', 'maths:w1t2', 'maths:w2t1', 'maths:w2t2',
    'chemistry:w1t1', 'chemistry:w1t2',
    'english:w1t1', 'english:w1t2', 'english:w2t1',
    'biology:w1t1', 'biology:w1t2',
  ],
  exams: [
    { id: 'e3', subject: 'english',   title: 'WAEC Mock · English',   score: 32, total: 40, mins: 45, date: '2026-08-16' },
    { id: 'e2', subject: 'maths',     title: 'Scheme Exam · Maths',   score: 27, total: 40, mins: 60, date: '2026-08-12' },
    { id: 'e1', subject: 'chemistry', title: 'JAMB Mock · Chemistry', score: 16, total: 40, mins: 40, date: '2026-08-08' },
  ],
  activity: [
    { at: '2026-08-19T20:10:00', kind: 'lesson',   text: 'Completed “Electric Fields” · Physics Week 4', points: 40 },
    { at: '2026-08-19T19:30:00', kind: 'practice', text: 'Answered 20 JAMB past questions · Maths',      points: 60 },
    { at: '2026-08-18T21:05:00', kind: 'exam',     text: 'WAEC Mock · English — scored 80%',             points: 120 },
    { at: '2026-08-18T18:40:00', kind: 'tutor',    text: 'Asked the AI tutor about balancing equations', points: 10 },
    { at: '2026-08-17T17:15:00', kind: 'lesson',   text: 'Completed “Mole Concept intro” · Chemistry',   points: 40 },
  ],
};

export function getProgress() {
  const saved = store.get(KEY);
  if (!saved) { store.set(KEY, DEFAULTS); return { ...DEFAULTS }; }
  return { ...DEFAULTS, ...saved };
}

export function saveProgress(changes) {
  const next = { ...getProgress(), ...changes };
  store.set(KEY, next);
  return next;
}

/** Where the student sits on the level ladder, and how far to the next one. */
export function levelInfo(points = getProgress().points) {
  let current = LEVELS[0];
  let next = LEVELS[1];
  LEVELS.forEach((l, i) => {
    if (points >= l.at) { current = l; next = LEVELS[i + 1] || null; }
  });
  const span = next ? next.at - current.at : 1;
  const into = points - current.at;
  return {
    ...current,
    next,
    toNext: next ? next.at - points : 0,
    pct: next ? Math.min(100, Math.round((into / span) * 100)) : 100,
  };
}

export function addPoints(n, reason = '') {
  const p = getProgress();
  const activity = reason
    ? [{ at: new Date().toISOString(), kind: 'points', text: reason, points: n }, ...p.activity].slice(0, 30)
    : p.activity;
  return saveProgress({ points: p.points + n, activity });
}

export function addStudyMinutes(mins) {
  const p = getProgress();
  const week = [...p.week];
  const today = (new Date().getDay() + 6) % 7;   // Monday = 0
  week[today] = (week[today] || 0) + mins;
  return saveProgress({ minutesToday: p.minutesToday + mins, week });
}

export function recordExam(entry) {
  const p = getProgress();
  const exams = [{ id: `e${Date.now().toString(36)}`, ...entry }, ...p.exams].slice(0, 20);
  const pct = Math.round((entry.score / entry.total) * 100);
  const badges = new Set(p.badges);
  if (pct >= 50) badges.add('exam-pass');
  if (pct >= 75) badges.add('exam-a1');
  return saveProgress({
    exams,
    badges: [...badges],
    points: p.points + entry.score * 5,
    questionsAnswered: p.questionsAnswered + entry.total,
  });
}

/** Weakest subject by mastery — drives the "focus here" prompts everywhere. */
export function weakestSubject(p = getProgress()) {
  const entries = Object.entries(p.subjects);
  if (!entries.length) return null;
  const [id, data] = entries.reduce((a, b) => (a[1].mastery <= b[1].mastery ? a : b));
  return { id, ...data };
}

export const weekTotal = (p = getProgress()) => p.week.reduce((a, b) => a + b, 0);

export const hasBadge = (id, p = getProgress()) => p.badges.includes(id);

/** Mark a lesson finished. Idempotent — replaying a lesson never double-counts. */
export function markTopicDone(key, subjectId) {
  const p = getProgress();
  if (p.doneTopics.includes(key)) return p;

  const subjects = { ...p.subjects };
  if (subjects[subjectId]) {
    const s = subjects[subjectId];
    subjects[subjectId] = {
      ...s,
      done: s.done + 1,
      mastery: Math.min(100, s.mastery + 3),
    };
  }
  return saveProgress({
    doneTopics: [...p.doneTopics, key],
    subjects,
    lessonsDone: p.lessonsDone + 1,
    points: p.points + 40,
    activity: [
      { at: new Date().toISOString(), kind: 'lesson', text: `Completed a lesson in ${subjectId}`, points: 40 },
      ...p.activity,
    ].slice(0, 30),
  });
}
