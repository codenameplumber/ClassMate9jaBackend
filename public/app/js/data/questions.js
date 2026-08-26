/* ==========================================================================
   questions.js — the offline past-question bank.

   Each item carries the exam body, the year and the topic, because the
   diagnosis screen is only useful if a wrong answer can be traced back to a
   topic.

   Two tiers, and the order matters:

     1. The bank synced from /api/bootstrap, held in local storage. This is
        the real one — it grows as the server generates questions, and it is
        what a student actually practises against.

     2. SEED_BANK below, compiled into the bundle. It is the cold-start set:
        what a brand-new install shows before its first sync, and what a
        device falls back to if storage was cleared. It is a sample, not the
        product.

   The bank is resolved once at import, from synchronous local storage, so a
   fresh sync shows up on the next navigation. That is how the rest of the app
   already behaves — no screen re-renders itself mid-session on new content.
   ========================================================================== */

import * as store from '../core/store.js';

export const EXAM_BODIES = ['WAEC', 'JAMB', 'NECO'];

const SEED_BANK = [
  /* ------------------------------- MATHS ------------------------------- */
  { id: 'm1', subject: 'maths', body: 'WAEC', year: 2019, topic: 'Logarithms',
    q: 'Evaluate log₁₀ 25 + log₁₀ 4.',
    options: ['1', '2', '10', '100'], answer: 1,
    why: 'log 25 + log 4 = log(25 × 4) = log 100 = 2.' },
  { id: 'm2', subject: 'maths', body: 'JAMB', year: 2021, topic: 'Number Bases',
    q: 'Convert 45 in base 10 to base 2.',
    options: ['101101₂', '110101₂', '101011₂', '111001₂'], answer: 0,
    why: '45 = 32 + 8 + 4 + 1 = 101101 in base 2.' },
  { id: 'm3', subject: 'maths', body: 'WAEC', year: 2020, topic: 'Quadratic Equations',
    q: 'Solve x² − 5x + 6 = 0.',
    options: ['x = 1 or 6', 'x = 2 or 3', 'x = −2 or −3', 'x = 5 or 6'], answer: 1,
    why: 'Factorising gives (x − 2)(x − 3) = 0, so x = 2 or x = 3.' },
  { id: 'm4', subject: 'maths', body: 'JAMB', year: 2022, topic: 'Indices',
    q: 'Simplify 2³ × 2⁵ ÷ 2⁴.',
    options: ['2²', '2⁴', '2⁶', '2⁸'], answer: 1,
    why: 'Add the indices being multiplied and subtract the divisor: 3 + 5 − 4 = 4, so 2⁴.' },
  { id: 'm5', subject: 'maths', body: 'WAEC', year: 2018, topic: 'Mensuration',
    q: 'The area of a circle is 154 cm². Find its radius. (Take π = 22/7)',
    options: ['7 cm', '14 cm', '3.5 cm', '11 cm'], answer: 0,
    why: 'πr² = 154 → r² = 154 × 7/22 = 49 → r = 7 cm.' },
  { id: 'm6', subject: 'maths', body: 'JAMB', year: 2023, topic: 'Statistics',
    q: 'Find the median of 4, 8, 2, 10, 6.',
    options: ['4', '6', '8', '10'], answer: 1,
    why: 'Arranged: 2, 4, 6, 8, 10. The middle value is 6.' },
  { id: 'm7', subject: 'maths', body: 'WAEC', year: 2021, topic: 'Variation',
    q: 'If y varies inversely as x and y = 4 when x = 3, find y when x = 6.',
    options: ['1', '2', '8', '12'], answer: 1,
    why: 'y = k/x → k = 12. When x = 6, y = 12/6 = 2.' },
  { id: 'm8', subject: 'maths', body: 'JAMB', year: 2020, topic: 'Trigonometry',
    q: 'A ladder leans against a wall at 60° to the ground. If the ladder is 10 m long, how high up the wall does it reach?',
    options: ['5 m', '5√3 m', '10√3 m', '20 m'], answer: 1,
    why: 'Height = 10 sin 60° = 10 × (√3/2) = 5√3 m ≈ 8.66 m.' },
  { id: 'm9', subject: 'maths', body: 'WAEC', year: 2024, topic: 'Probability',
    q: 'A bag holds 3 red and 5 blue balls. What is the probability of picking a red ball?',
    options: ['3/8', '5/8', '3/5', '1/3'], answer: 0,
    why: 'P(red) = number of red ÷ total = 3/8.' },
  { id: 'm10', subject: 'maths', body: 'JAMB', year: 2025, topic: 'Bearings',
    q: 'A man walks 4 km north then 3 km east. How far is he from his starting point?',
    options: ['5 km', '7 km', '1 km', '12 km'], answer: 0,
    why: 'Pythagoras: √(4² + 3²) = √25 = 5 km.' },

  /* ------------------------------ ENGLISH ------------------------------ */
  { id: 'e1', subject: 'english', body: 'WAEC', year: 2022, topic: 'Concord',
    q: 'Choose the correct option: Neither the students nor the teacher ___ present.',
    options: ['were', 'was', 'are', 'have been'], answer: 1,
    why: 'With "neither…nor", the verb agrees with the nearer subject — "teacher" is singular, so "was".' },
  { id: 'e2', subject: 'english', body: 'JAMB', year: 2021, topic: 'Lexis',
    q: 'Choose the word nearest in meaning to "meticulous".',
    options: ['Careless', 'Thorough', 'Rapid', 'Doubtful'], answer: 1,
    why: 'Meticulous means showing great attention to detail — thorough.' },
  { id: 'e3', subject: 'english', body: 'WAEC', year: 2020, topic: 'Figures of Speech',
    q: '"The classroom was a market square." This is an example of:',
    options: ['Simile', 'Metaphor', 'Hyperbole', 'Personification'], answer: 1,
    why: 'It compares directly without "like" or "as", which makes it a metaphor.' },
  { id: 'e4', subject: 'english', body: 'JAMB', year: 2023, topic: 'Oral English',
    q: 'Which word has a different vowel sound from the others?',
    options: ['beat', 'seat', 'great', 'meat'], answer: 2,
    why: '"Great" has the /eɪ/ sound, while the others carry /iː/.' },
  { id: 'e5', subject: 'english', body: 'WAEC', year: 2019, topic: 'Tenses',
    q: 'By the time we arrived, the film ___.',
    options: ['has started', 'had started', 'was starting', 'starts'], answer: 1,
    why: 'The past perfect "had started" shows an action completed before another past action.' },
  { id: 'e6', subject: 'english', body: 'JAMB', year: 2024, topic: 'Antonyms',
    q: 'Choose the option opposite in meaning to "frugal".',
    options: ['Thrifty', 'Extravagant', 'Modest', 'Prudent'], answer: 1,
    why: 'Frugal means sparing with money; its opposite is extravagant.' },
  { id: 'e7', subject: 'english', body: 'WAEC', year: 2023, topic: 'Registers',
    q: '"Adjournment", "plaintiff" and "bail" belong to the register of:',
    options: ['Medicine', 'Law', 'Farming', 'Banking'], answer: 1,
    why: 'All three are courtroom terms — the register of law.' },
  { id: 'e8', subject: 'english', body: 'JAMB', year: 2022, topic: 'Word Classes',
    q: 'In "She sings beautifully", the word "beautifully" is:',
    options: ['An adjective', 'An adverb', 'A noun', 'A preposition'], answer: 1,
    why: 'It modifies the verb "sings", so it is an adverb of manner.' },

  /* ------------------------------ PHYSICS ------------------------------ */
  { id: 'p1', subject: 'physics', body: 'WAEC', year: 2021, topic: 'Electric Fields',
    q: 'The unit of electric field intensity is:',
    options: ['N/C', 'J/C', 'C/s', 'W/A'], answer: 0,
    why: 'Field intensity is force per unit charge, giving newtons per coulomb.' },
  { id: 'p2', subject: 'physics', body: 'JAMB', year: 2020, topic: 'Motion',
    q: 'A car accelerates uniformly from rest to 20 m/s in 5 s. Its acceleration is:',
    options: ['2 m/s²', '4 m/s²', '5 m/s²', '100 m/s²'], answer: 1,
    why: 'a = (v − u)/t = (20 − 0)/5 = 4 m/s².' },
  { id: 'p3', subject: 'physics', body: 'WAEC', year: 2019, topic: 'Waves',
    q: 'A wave has frequency 50 Hz and wavelength 4 m. Its speed is:',
    options: ['12.5 m/s', '54 m/s', '200 m/s', '0.08 m/s'], answer: 2,
    why: 'v = fλ = 50 × 4 = 200 m/s.' },
  { id: 'p4', subject: 'physics', body: 'JAMB', year: 2023, topic: 'Current Electricity',
    q: 'Two 6 Ω resistors are connected in parallel. The effective resistance is:',
    options: ['12 Ω', '6 Ω', '3 Ω', '0.33 Ω'], answer: 2,
    why: '1/R = 1/6 + 1/6 = 2/6, so R = 3 Ω.' },
  { id: 'p5', subject: 'physics', body: 'WAEC', year: 2022, topic: 'Work & Energy',
    q: 'A force of 20 N moves a body 5 m in its direction. The work done is:',
    options: ['4 J', '25 J', '100 J', '0.25 J'], answer: 2,
    why: 'W = F × d = 20 × 5 = 100 J.' },
  { id: 'p6', subject: 'physics', body: 'JAMB', year: 2024, topic: 'Heat Energy',
    q: 'The quantity of heat needed to change 1 kg of ice to water at 0°C is called:',
    options: ['Heat capacity', 'Specific latent heat of fusion', 'Specific heat capacity', 'Thermal conductivity'], answer: 1,
    why: 'A change of state at constant temperature is governed by latent heat — fusion for solid to liquid.' },
  { id: 'p7', subject: 'physics', body: 'WAEC', year: 2025, topic: 'Modern Physics',
    q: 'Which particle has no charge?',
    options: ['Proton', 'Electron', 'Neutron', 'Alpha particle'], answer: 2,
    why: 'The neutron is electrically neutral; that is where its name comes from.' },
  { id: 'p8', subject: 'physics', body: 'JAMB', year: 2021, topic: 'Newton Laws',
    q: 'A body of mass 5 kg experiences a force of 30 N. Its acceleration is:',
    options: ['6 m/s²', '150 m/s²', '0.17 m/s²', '35 m/s²'], answer: 0,
    why: 'F = ma → a = F/m = 30/5 = 6 m/s².' },

  /* ----------------------------- CHEMISTRY ----------------------------- */
  { id: 'c1', subject: 'chemistry', body: 'WAEC', year: 2020, topic: 'Mole Concept',
    q: 'How many moles are in 36 g of water? (H = 1, O = 16)',
    options: ['1 mole', '2 moles', '18 moles', '0.5 mole'], answer: 1,
    why: 'Molar mass of H₂O = 18 g/mol, so 36 ÷ 18 = 2 moles.' },
  { id: 'c2', subject: 'chemistry', body: 'JAMB', year: 2022, topic: 'Redox',
    q: 'The oxidation number of sulphur in H₂SO₄ is:',
    options: ['+2', '+4', '+6', '−2'], answer: 2,
    why: '2(+1) + S + 4(−2) = 0 → S = +6.' },
  { id: 'c3', subject: 'chemistry', body: 'WAEC', year: 2023, topic: 'Acids & Bases',
    q: 'A solution with pH 3 is:',
    options: ['Strongly basic', 'Weakly basic', 'Acidic', 'Neutral'], answer: 2,
    why: 'Any pH below 7 is acidic; pH 3 is strongly so.' },
  { id: 'c4', subject: 'chemistry', body: 'JAMB', year: 2021, topic: 'Atomic Structure',
    q: 'The electron configuration of an element with atomic number 11 is:',
    options: ['2, 8, 1', '2, 8, 3', '2, 9', '1, 8, 2'], answer: 0,
    why: '11 electrons fill as 2, 8, 1 — that element is sodium.' },
  { id: 'c5', subject: 'chemistry', body: 'WAEC', year: 2019, topic: 'Electrolysis',
    q: 'During the electrolysis of acidified water, the gas liberated at the anode is:',
    options: ['Hydrogen', 'Oxygen', 'Chlorine', 'Nitrogen'], answer: 1,
    why: 'Oxidation happens at the anode, releasing oxygen; hydrogen appears at the cathode.' },
  { id: 'c6', subject: 'chemistry', body: 'JAMB', year: 2024, topic: 'Organic Chemistry',
    q: 'The general formula of alkanes is:',
    options: ['CₙH₂ₙ', 'CₙH₂ₙ₊₂', 'CₙH₂ₙ₋₂', 'CₙH₂ₙ₊₁'], answer: 1,
    why: 'Alkanes are saturated hydrocarbons with the formula CₙH₂ₙ₊₂.' },
  { id: 'c7', subject: 'chemistry', body: 'WAEC', year: 2025, topic: 'Gas Laws',
    q: 'The volume occupied by 1 mole of any gas at s.t.p. is:',
    options: ['11.2 dm³', '22.4 dm³', '24 dm³', '44.8 dm³'], answer: 1,
    why: 'One mole of any gas occupies 22.4 dm³ at standard temperature and pressure.' },
  { id: 'c8', subject: 'chemistry', body: 'JAMB', year: 2020, topic: 'Chemical Equations',
    q: 'Balance: __ H₂ + O₂ → __ H₂O. The coefficients are:',
    options: ['1, 1', '2, 2', '2, 1 → 2', '3, 1 → 3'], answer: 2,
    why: '2H₂ + O₂ → 2H₂O balances both hydrogen and oxygen atoms.' },

  /* ------------------------------ BIOLOGY ------------------------------ */
  { id: 'b1', subject: 'biology', body: 'WAEC', year: 2021, topic: 'Cell Biology',
    q: 'The powerhouse of the cell is the:',
    options: ['Ribosome', 'Mitochondrion', 'Nucleus', 'Golgi body'], answer: 1,
    why: 'Mitochondria carry out aerobic respiration, releasing the cell energy as ATP.' },
  { id: 'b2', subject: 'biology', body: 'JAMB', year: 2022, topic: 'Genetics',
    q: 'A cross between two heterozygous tall plants (Tt × Tt) gives a phenotypic ratio of:',
    options: ['1 : 1', '3 : 1', '9 : 3 : 3 : 1', '2 : 1'], answer: 1,
    why: 'A monohybrid cross of two heterozygotes gives 3 tall to 1 short.' },
  { id: 'b3', subject: 'biology', body: 'WAEC', year: 2020, topic: 'Nutrition',
    q: 'Photosynthesis takes place mainly in the:',
    options: ['Root hair', 'Palisade mesophyll', 'Xylem vessel', 'Stomata'], answer: 1,
    why: 'Palisade mesophyll cells are packed with chloroplasts near the leaf surface.' },
  { id: 'b4', subject: 'biology', body: 'JAMB', year: 2023, topic: 'Transport',
    q: 'The blood vessel that carries oxygenated blood from the lungs to the heart is the:',
    options: ['Pulmonary artery', 'Pulmonary vein', 'Aorta', 'Vena cava'], answer: 1,
    why: 'The pulmonary vein is the exception — a vein carrying oxygenated blood.' },
  { id: 'b5', subject: 'biology', body: 'WAEC', year: 2024, topic: 'Excretion',
    q: 'The functional unit of the kidney is the:',
    options: ['Neuron', 'Nephron', 'Alveolus', 'Villus'], answer: 1,
    why: 'Each kidney holds about a million nephrons, where filtration happens.' },
  { id: 'b6', subject: 'biology', body: 'JAMB', year: 2019, topic: 'Ecology',
    q: 'In a food chain, green plants are described as:',
    options: ['Primary consumers', 'Producers', 'Decomposers', 'Secondary consumers'], answer: 1,
    why: 'They manufacture their own food through photosynthesis, so they are producers.' },
  { id: 'b7', subject: 'biology', body: 'WAEC', year: 2022, topic: 'Respiration',
    q: 'The end product of anaerobic respiration in muscles is:',
    options: ['Ethanol', 'Lactic acid', 'Carbon dioxide only', 'Glucose'], answer: 1,
    why: 'Muscle cells short of oxygen convert pyruvate to lactic acid — the reason for cramp.' },
  { id: 'b8', subject: 'biology', body: 'JAMB', year: 2025, topic: 'Classification',
    q: 'Organisms that make their own food are described as:',
    options: ['Heterotrophic', 'Autotrophic', 'Saprophytic', 'Parasitic'], answer: 1,
    why: 'Autotrophs synthesise their own organic food from simple inorganic substances.' },
];

/**
 * The synced bank if there is one, otherwise the bundled sample.
 *
 * Anything malformed falls back rather than throwing: a half-written storage
 * key must not be able to leave a student staring at a broken practice screen
 * when a perfectly good sample is sitting in the bundle.
 */
function loadBank() {
  const synced = store.get('question-bank', null);

  return Array.isArray(synced) && synced.length ? synced : SEED_BANK;
}

export const BANK = loadBank();

/** True when practising against real synced content rather than the sample. */
export const isSynced = () => BANK !== SEED_BANK;

export const YEARS = [...new Set(BANK.map((q) => q.year))].sort((a, b) => b - a);

export const topicsFor = (subjectId) =>
  [...new Set(BANK.filter((q) => q.subject === subjectId).map((q) => q.topic))];

/* Deterministic shuffle-ish rotation: keeps sets varied between attempts
   without pulling in a seeded-random dependency. */
function rotate(list, by) {
  const n = list.length;
  if (!n) return [];
  const k = ((by % n) + n) % n;
  return [...list.slice(k), ...list.slice(0, k)];
}

/**
 * Build a question set for practice or a mock exam.
 * Pads by cycling the pool so a 40-question mock always returns 40 items,
 * which is what a student expects even on a thin offline pack.
 */
export function buildSet({ subject = null, body = null, year = null, topic = null, count = 20, offset = 0 } = {}) {
  let pool = BANK.filter((q) =>
    (!subject || q.subject === subject)
    && (!body || q.body === body)
    && (!year || q.year === Number(year))
    && (!topic || q.topic === topic)
  );
  if (!pool.length) pool = BANK.filter((q) => !subject || q.subject === subject);
  if (!pool.length) pool = BANK;

  pool = rotate(pool, offset);

  const out = [];
  for (let i = 0; i < count; i += 1) {
    const base = pool[i % pool.length];
    out.push({ ...base, uid: `${base.id}-${i}` });
  }
  return out;
}

export const countFor = (subject) => BANK.filter((q) => q.subject === subject).length;
