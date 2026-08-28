/* ==========================================================================
   progress.js — the student's learning record.

   Starts empty, on purpose.

   It used to ship a believable three-week history so that every screen had
   something to show on first open — 2,340 points, a twelve-day streak, five
   invented activity entries. That was fine while this was a prototype with no
   server behind it. It is not fine now: a student who registered this morning
   opened Progress and was shown someone else's fortnight as their own, and
   the screen looked frozen because nothing they did could move numbers that
   had been made up.

   Real history arrives from /api/bootstrap, where the server derives exams and
   activity from records it actually holds. Everything else here is written by
   the device as the student works.
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
  points: 0,
  streak: 0,
  bestStreak: 0,
  minutesToday: 0,
  questionsAnswered: 0,
  lessonsDone: 0,
  /* Mon → Sun study minutes for the current week */
  week: [0, 0, 0, 0, 0, 0, 0],
  subjects: {},
  badges: [],
  doneTopics: [],
  /* Both of these are derived on the server and arrive with the first sync. */
  exams: [],
  activity: [],
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
