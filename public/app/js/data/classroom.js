/* ==========================================================================
   classroom.js — classes, students, assignments and the marking queue.

   Submissions are generated from a compact seed rather than typed out, so the
   demo carries a realistic 30-script pile without a 3,000-line fixture. The
   shapes match what a real backend would return.
   ========================================================================== */

import * as store from '../core/store.js';

const MARKED_KEY = 'marked-scripts';

/* --- Roster ---------------------------------------------------------------- */

const NAMES = [
  'Chidera Okonkwo', 'Aisha Bello', 'Tunde Adeyemi', 'Ngozi Eze', 'Ibrahim Musa',
  'Folake Adebayo', 'Emeka Nwosu', 'Halima Yusuf', 'Segun Ogunleye', 'Blessing Etim',
  'Yakubu Danjuma', 'Chioma Obi', 'Musa Abdullahi', 'Temitope Balogun', 'Uche Nnamdi',
  'Zainab Sani', 'Kelechi Anyanwu', 'Adaeze Ugwu', 'Bashir Lawal', 'Funmilayo Oyelaran',
  'Chinedu Okafor', 'Maryam Garba', 'Olumide Fashola', 'Ifeoma Chukwu', 'Suleiman Bala',
  'Titilayo Akande', 'Godwin Ubah', 'Amina Shehu', 'Peter Enahoro', 'Bukola Ajayi',
];

export const CLASSES = [
  { id: 'ss2a', name: 'SS2A', level: 'SS2', subject: 'maths', size: 30, room: 'Block B, Room 4' },
  { id: 'ss2b', name: 'SS2B', level: 'SS2', subject: 'maths', size: 28, room: 'Block B, Room 5' },
  { id: 'ss1a', name: 'SS1A', level: 'SS1', subject: 'maths', size: 34, room: 'Block A, Room 2' },
];

/** Deterministic pseudo-random from a string — same roster every reload. */
function seed(str) {
  let h = 2166136261;
  for (let i = 0; i < str.length; i += 1) {
    h ^= str.charCodeAt(i);
    h = Math.imul(h, 16777619);
  }
  return Math.abs(h);
}

export function studentsOf(classId) {
  const cls = CLASSES.find((c) => c.id === classId) || CLASSES[0];
  return NAMES.slice(0, cls.size).map((name, i) => {
    const s = seed(name + classId);
    const avg = 34 + (s % 56);                    // 34–89
    return {
      id: `${classId}-${i + 1}`,
      name,
      classId,
      avg,
      streak: s % 21,
      minutesWeek: 40 + (s % 260),
      lastActive: ['Today', 'Today', 'Yesterday', '2 days ago', '5 days ago'][s % 5],
      atRisk: avg < 45,
      topics: {
        Logarithms: 20 + (s % 80),
        Indices: 30 + ((s >> 3) % 70),
        'Number Bases': 25 + ((s >> 5) % 75),
        'Quadratic Equations': 20 + ((s >> 7) % 80),
      },
    };
  });
}

/* --- Assignments ----------------------------------------------------------- */

export const ASSIGNMENTS = [
  {
    id: 'a1',
    title: 'Week 3 Classwork · Logarithms',
    subject: 'maths',
    classId: 'ss2a',
    type: 'classwork',
    setBy: 'ai',
    questions: 5,
    assigned: '2026-08-17',
    due: '2026-08-21',
    submitted: 30,
    marked: 0,
  },
  {
    id: 'a2',
    title: 'Week 2 Test · Laws of Indices',
    subject: 'maths',
    classId: 'ss2a',
    type: 'test',
    setBy: 'ai',
    questions: 10,
    assigned: '2026-08-10',
    due: '2026-08-14',
    submitted: 29,
    marked: 29,
  },
  {
    id: 'a3',
    title: 'Week 3 Classwork · Logarithms',
    subject: 'maths',
    classId: 'ss2b',
    type: 'classwork',
    setBy: 'ai',
    questions: 5,
    assigned: '2026-08-17',
    due: '2026-08-21',
    submitted: 24,
    marked: 6,
  },
  {
    id: 'a4',
    title: 'Week 1 Revision · Number Bases',
    subject: 'maths',
    classId: 'ss1a',
    type: 'classwork',
    setBy: 'teacher',
    questions: 8,
    assigned: '2026-08-05',
    due: '2026-08-09',
    submitted: 31,
    marked: 31,
  },
];

/* --- The paper being marked ------------------------------------------------ */

/* Two short-answer questions sit among the objectives on purpose: objective
   marking is trivial, and the screen has to prove the AI can handle written
   work — that is the part a teacher actually loses evenings to. */
const PAPER = [
  {
    n: 1, max: 2, type: 'objective',
    q: 'Evaluate log₁₀ 1000.',
    correct: '3',
  },
  {
    n: 2, max: 2, type: 'objective',
    q: 'Simplify log 12 − log 4.',
    correct: 'log 3',
  },
  {
    n: 3, max: 2, type: 'objective',
    q: 'If log₂ x = 5, find x.',
    correct: '32',
  },
  {
    n: 4, max: 4, type: 'short',
    q: 'State the three laws of logarithms.',
    correct: 'log(MN) = log M + log N; log(M/N) = log M − log N; log(Mⁿ) = n log M',
  },
  {
    n: 5, max: 5, type: 'short',
    q: 'Given that log 2 = 0.3010 and log 3 = 0.4771, find log 12. Show your working.',
    correct: 'log 12 = log(4 × 3) = 2log 2 + log 3 = 0.6020 + 0.4771 = 1.0791',
  },
];

/* Answer variants per question, from best to worst, each with the mark the AI
   awards and the comment it writes. */
const VARIANTS = {
  1: [
    { a: '3', mark: 2, conf: 99, note: 'Correct.' },
    { a: '3', mark: 2, conf: 99, note: 'Correct.' },
    { a: '10', mark: 0, conf: 97, note: 'Confused the base with the answer. 10³ = 1000, so the log is 3.' },
    { a: '100', mark: 0, conf: 96, note: 'This is 10², not the power that gives 1000.' },
  ],
  2: [
    { a: 'log 3', mark: 2, conf: 98, note: 'Correct — used log(M/N) = log M − log N.' },
    { a: 'log 8', mark: 0, conf: 95, note: 'Subtracted the numbers instead of dividing them. 12 ÷ 4 = 3, not 12 − 4.' },
    { a: 'log 3', mark: 2, conf: 98, note: 'Correct.' },
    { a: '3', mark: 1, conf: 74, note: 'Right value inside, but the answer should stay as log 3 — the log has not been evaluated.' },
  ],
  3: [
    { a: '32', mark: 2, conf: 99, note: 'Correct — x = 2⁵.' },
    { a: '32', mark: 2, conf: 99, note: 'Correct.' },
    { a: '10', mark: 0, conf: 96, note: 'Multiplied 2 × 5 instead of raising 2 to the power 5.' },
    { a: '25', mark: 0, conf: 95, note: 'Raised 5 to the power 2. The base is 2, so it is 2⁵ = 32.' },
  ],
  4: [
    {
      a: 'log(MN) = log M + log N, log(M/N) = log M − log N, log(M^n) = n log M',
      mark: 4, conf: 96, note: 'All three laws stated correctly and in the right form.',
    },
    {
      a: 'log MN = log M + log N and log M/N = log M − log N',
      mark: 3, conf: 88, note: 'Two laws correct. The power law, log(Mⁿ) = n log M, is missing.',
    },
    {
      a: 'Product law, quotient law and power law',
      mark: 2, conf: 71, note: 'Names the three laws but does not state them. The question says "state", so the expressions are needed.',
    },
    {
      a: 'log M + log N = log M + N',
      mark: 0, conf: 93, note: 'This is the classic error: log M + log N = log(MN), not log(M + N).',
    },
  ],
  5: [
    {
      a: 'log 12 = log(4×3) = 2log2 + log3 = 2(0.3010) + 0.4771 = 0.6020 + 0.4771 = 1.0791',
      mark: 5, conf: 94, note: 'Full working, correct factorisation and accurate arithmetic. Nothing to take off.',
    },
    {
      a: 'log 12 = log 4 + log 3 = 0.6020 + 0.4771 = 1.0791',
      mark: 4, conf: 82,
      note: 'Correct answer and method, but log 4 was written straight down without showing it comes from 2 log 2. One mark off for working.',
    },
    {
      a: 'log12 = log2 + log3 = 0.3010 + 0.4771 = 0.7781',
      mark: 2, conf: 90,
      note: 'Split 12 as 2 × 3 instead of 4 × 3. Method mark earned, but the factorisation and the final answer are wrong.',
    },
    {
      a: 'log 12 = 0.3010 × 0.4771 = 0.1436',
      mark: 0, conf: 91,
      note: 'Multiplied the logs. Multiplying inside a log becomes addition outside it, not multiplication of the values.',
    },
  ],
};

/** Build the 30 scripts waiting to be marked for an assignment. */
export function submissionsFor(assignmentId) {
  const assignment = ASSIGNMENTS.find((a) => a.id === assignmentId) || ASSIGNMENTS[0];
  const roster = studentsOf(assignment.classId);
  const approved = store.get(MARKED_KEY, {}) || {};

  return roster.slice(0, assignment.submitted).map((student, i) => {
    const s = seed(student.name + assignmentId);
    const answers = PAPER.map((q) => {
      const pool = VARIANTS[q.n];
      /* Stronger students land on earlier (better) variants. */
      const skill = Math.floor((student.avg / 100) * pool.length);
      const pick = Math.min(pool.length - 1, Math.max(0, (skill + ((s >> q.n) % 2)) % pool.length));
      const v = pool[pick];
      return {
        n: q.n,
        type: q.type,
        q: q.q,
        max: q.max,
        expected: q.correct,
        given: v.a,
        aiMark: v.mark,
        confidence: v.conf,
        comment: v.note,
      };
    });

    const aiScore = answers.reduce((a, x) => a + x.aiMark, 0);
    const maxScore = PAPER.reduce((a, q) => a + q.max, 0);
    const lowConfidence = answers.some((x) => x.confidence < 80);

    return {
      id: `${assignmentId}-${student.id}`,
      assignmentId,
      student,
      answers,
      aiScore,
      maxScore,
      pct: Math.round((aiScore / maxScore) * 100),
      lowConfidence,
      submittedAt: ['08:14', '08:31', '09:02', '12:47', '19:20', '21:08'][i % 6],
      status: approved[`${assignmentId}-${student.id}`] || 'pending',
    };
  });
}

export const paperMax = () => PAPER.reduce((a, q) => a + q.max, 0);

/* --- Teacher decisions ----------------------------------------------------- */

export function approveScript(id, finalScore) {
  const all = store.get(MARKED_KEY, {}) || {};
  all[id] = 'approved';
  store.set(MARKED_KEY, all);
  const scores = store.get('marked-scores', {}) || {};
  if (finalScore !== undefined) {
    scores[id] = finalScore;
    store.set('marked-scores', scores);
  }
  return all;
}

export function approveMany(ids) {
  const all = store.get(MARKED_KEY, {}) || {};
  ids.forEach((id) => { all[id] = 'approved'; });
  store.set(MARKED_KEY, all);
  return all;
}

export const markedScore = (id) => (store.get('marked-scores', {}) || {})[id];

export function resetMarking() {
  store.remove(MARKED_KEY);
  store.remove('marked-scores');
}

/* --- Class analytics ------------------------------------------------------- */

/** Average per topic across the class — drives "the whole class failed this". */
export function topicHealth(classId) {
  const roster = studentsOf(classId);
  const topics = Object.keys(roster[0].topics);
  return topics.map((topic) => {
    const avg = Math.round(
      roster.reduce((a, s) => a + s.topics[topic], 0) / roster.length
    );
    return { topic, avg, failing: roster.filter((s) => s.topics[topic] < 50).length };
  }).sort((a, b) => a.avg - b.avg);
}

export function classStats(classId) {
  const roster = studentsOf(classId);
  const avg = Math.round(roster.reduce((a, s) => a + s.avg, 0) / roster.length);
  return {
    size: roster.length,
    avg,
    atRisk: roster.filter((s) => s.atRisk).length,
    activeToday: roster.filter((s) => s.lastActive === 'Today').length,
    minutesWeek: roster.reduce((a, s) => a + s.minutesWeek, 0),
  };
}

export const pendingCount = () => ASSIGNMENTS.reduce((a, x) => {
  const approved = store.get(MARKED_KEY, {}) || {};
  const done = Object.keys(approved).filter((k) => k.startsWith(`${x.id}-`)).length;
  return a + Math.max(0, x.submitted - Math.max(x.marked, done));
}, 0);
