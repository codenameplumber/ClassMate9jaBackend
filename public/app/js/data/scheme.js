/* ==========================================================================
   scheme.js — 12-week schemes of work + lesson content.

   In production this is what the AI generates once per (subject, class) and
   caches on the device. Here it ships pre-generated: same shape, same render
   path, so swapping in a live generator later changes nothing above this file.
   ========================================================================== */

/* --- Week-by-week topic lists ---------------------------------------------- */

const W = (week, title, ...topics) => ({
  week,
  title,
  topics: topics.map((t, i) => ({
    id: `w${week}t${i + 1}`,
    title: t[0],
    mins: t[1] || 25,
  })),
});

export const SCHEMES = {
  maths: [
    W(1,  'Number Bases',            ['Number bases and conversion', 30], ['Arithmetic in other bases', 25]),
    W(2,  'Fractions & Indices',     ['Fractions, decimals, percentages', 25], ['Laws of indices', 30]),
    W(3,  'Logarithms',              ['Introduction to logarithms', 30], ['Logarithm laws and calculations', 35]),
    W(4,  'Algebraic Processes',     ['Simplification and factorisation', 30], ['Change of subject of formula', 25]),
    W(5,  'Equations',               ['Linear equations', 25], ['Simultaneous equations', 35]),
    W(6,  'Quadratic Equations',     ['Solving by factorisation', 30], ['Completing the square & formula', 35]),
    W(7,  'Variation',               ['Direct and inverse variation', 25], ['Joint and partial variation', 30]),
    W(8,  'Geometry',                ['Angles and polygons', 25], ['Circle theorems', 35]),
    W(9,  'Mensuration',             ['Areas of plane shapes', 30], ['Volume and surface area of solids', 35]),
    W(10, 'Trigonometry',            ['Sine, cosine and tangent', 30], ['Bearings and distances', 35]),
    W(11, 'Statistics',              ['Mean, median and mode', 25], ['Standard deviation and charts', 30]),
    W(12, 'Probability & Revision',  ['Basic probability', 25], ['Full revision + past questions', 45]),
  ],
  english: [
    W(1,  'Comprehension Skills',    ['Reading for main idea', 25], ['Answering in your own words', 25]),
    W(2,  'Summary Writing',         ['Identifying key points', 25], ['Writing tight summaries', 30]),
    W(3,  'Lexis & Structure',       ['Synonyms and antonyms', 25], ['Word classes in context', 25]),
    W(4,  'Concord',                 ['Subject-verb agreement', 30], ['Tricky concord rules', 25]),
    W(5,  'Tenses',                  ['Simple, continuous, perfect', 30], ['Sequence of tenses', 25]),
    W(6,  'Oral English',            ['Vowel and consonant sounds', 30], ['Stress and intonation', 30]),
    W(7,  'Essay Writing I',         ['Formal and informal letters', 35], ['Article and speech writing', 30]),
    W(8,  'Essay Writing II',        ['Narrative and descriptive essays', 30], ['Argumentative essays', 35]),
    W(9,  'Figures of Speech',       ['Simile, metaphor, personification', 25], ['Irony, hyperbole, euphemism', 25]),
    W(10, 'Registers',               ['Register of law and medicine', 25], ['Register of farming and sports', 25]),
    W(11, 'Punctuation & Spelling',  ['Punctuation marks', 20], ['Commonly misspelt words', 20]),
    W(12, 'Revision',                ['WAEC essay drill', 40], ['Full past-question practice', 45]),
  ],
  physics: [
    W(1,  'Measurement',             ['Fundamental and derived units', 25], ['Measuring instruments and errors', 25]),
    W(2,  'Motion',                  ['Types of motion', 25], ['Equations of linear motion', 35]),
    W(3,  'Force & Newton Laws',     ['Newton laws of motion', 30], ['Momentum and impulse', 30]),
    W(4,  'Electric Fields',         ['Electric fields and potential', 35], ['Capacitors and capacitance', 30]),
    W(5,  'Work, Energy & Power',    ['Work and energy', 25], ['Power and efficiency', 25]),
    W(6,  'Heat Energy',             ['Temperature and thermometers', 25], ['Heat capacity and latent heat', 30]),
    W(7,  'Waves',                   ['Properties of waves', 30], ['Sound and light waves', 35]),
    W(8,  'Optics',                  ['Reflection and refraction', 30], ['Lenses and the eye', 30]),
    W(9,  'Current Electricity',     ['Ohm law and resistance', 30], ['Circuits and electrical energy', 35]),
    W(10, 'Magnetism',               ['Magnetic fields', 25], ['Electromagnetic induction', 35]),
    W(11, 'Modern Physics',          ['Atomic structure', 25], ['Radioactivity', 30]),
    W(12, 'Revision',                ['Calculation drill', 40], ['Past-question marathon', 45]),
  ],
  chemistry: [
    W(1,  'Particulate Nature',      ['Atoms, molecules and ions', 25], ['Laws of chemical combination', 30]),
    W(2,  'Atomic Structure',        ['Electron configuration', 30], ['Periodic table trends', 30]),
    W(3,  'Mole Concept',            ['Understanding the mole', 35], ['Mole calculations and stoichiometry', 40]),
    W(4,  'Chemical Equations',      ['Writing and balancing equations', 30], ['Ionic equations', 25]),
    W(5,  'Acids, Bases & Salts',    ['Properties and pH', 30], ['Preparation of salts', 30]),
    W(6,  'Redox Reactions',         ['Oxidation numbers', 30], ['Balancing redox equations', 35]),
    W(7,  'Electrolysis',            ['Electrolytes and products', 30], ['Faraday laws', 35]),
    W(8,  'Energy Changes',          ['Exothermic and endothermic', 25], ['Enthalpy calculations', 30]),
    W(9,  'Rates & Equilibrium',     ['Rate of reaction', 30], ['Le Chatelier principle', 30]),
    W(10, 'Organic Chemistry I',     ['Alkanes, alkenes, alkynes', 35], ['Isomerism', 30]),
    W(11, 'Organic Chemistry II',    ['Alcohols and alkanoic acids', 30], ['Polymers and their uses', 25]),
    W(12, 'Revision',                ['Practical chemistry drill', 40], ['Past-question marathon', 45]),
  ],
  biology: [
    W(1,  'Living Things',           ['Characteristics of living things', 25], ['Classification of organisms', 30]),
    W(2,  'Cell Biology',            ['Cell structure and functions', 30], ['Cell division: mitosis & meiosis', 35]),
    W(3,  'Nutrition',               ['Photosynthesis', 30], ['Modes of nutrition in animals', 25]),
    W(4,  'Transport Systems',       ['Transport in plants', 25], ['Blood and circulation', 35]),
    W(5,  'Respiration',             ['Aerobic and anaerobic respiration', 30], ['The respiratory system', 25]),
    W(6,  'Excretion & Homeostasis', ['Excretory organs', 25], ['Homeostasis and the kidney', 30]),
    W(7,  'Reproduction',            ['Reproduction in plants', 30], ['Human reproductive system', 30]),
    W(8,  'Genetics',                ['Mendel laws', 35], ['Sex determination and blood groups', 35]),
    W(9,  'Evolution',               ['Theories of evolution', 25], ['Evidence for evolution', 25]),
    W(10, 'Ecology I',               ['Ecosystems and habitats', 30], ['Food chains and energy flow', 25]),
    W(11, 'Ecology II',              ['Nutrient cycles', 25], ['Conservation of natural resources', 25]),
    W(12, 'Revision',                ['Diagram drill', 35], ['Past-question marathon', 45]),
  ],
};

/* Subjects without a hand-written scheme still get a full 12 weeks so no
   screen ever renders empty. */
function fallbackScheme(subjectName) {
  return Array.from({ length: 12 }, (_, i) =>
    W(i + 1, `${subjectName} · Module ${i + 1}`,
      [`Core concepts, part ${i + 1}`, 30],
      [`Worked examples and practice`, 25])
  );
}

/** The 12-week scheme for a subject, with progress applied. */
export function schemeFor(subjectId, subjectName = 'Subject', doneTopics = []) {
  const weeks = SCHEMES[subjectId] || fallbackScheme(subjectName);
  return weeks.map((w) => {
    const topics = w.topics.map((t) => ({
      ...t,
      key: `${subjectId}:${t.id}`,
      done: doneTopics.includes(`${subjectId}:${t.id}`),
    }));
    const done = topics.filter((t) => t.done).length;
    return {
      ...w,
      topics,
      doneCount: done,
      pct: Math.round((done / topics.length) * 100),
      status: done === topics.length ? 'done' : done > 0 ? 'now' : 'todo',
    };
  });
}

/** First unfinished topic — powers every "Continue where you stopped" card. */
export function nextTopic(subjectId, subjectName, doneTopics = []) {
  const weeks = schemeFor(subjectId, subjectName, doneTopics);
  for (const w of weeks) {
    const t = w.topics.find((x) => !x.done);
    if (t) return { week: w.week, weekTitle: w.title, ...t };
  }
  return null;
}

/* ==========================================================================
   Lesson content
   Flagship topics are written out in full, in English and Pidgin. Everything
   else is composed from the same section shapes so no lesson looks unfinished.
   ========================================================================== */

const LESSONS = {
  'physics:w4t1': {
    title: 'Electric Fields and Potential',
    subject: 'physics',
    week: 4,
    mins: 35,
    objectives: [
      'Define an electric field and state its unit',
      'Calculate field strength around a point charge',
      'Explain electric potential and potential difference',
    ],
    english: [
      { h: 'What is an electric field?' },
      { p: 'An electric field is the region around a charged body where its force is felt. Any other charge placed inside that region will either be pulled or pushed. The field is a vector: it has both size and direction, and by convention it points away from a positive charge and towards a negative one.' },
      { naija: 'Think of a transformer on your street. You no need to touch am before you know say danger dey there — the closer you go, the more the effect. That "area of influence" na exactly what electric field mean.' },
      { h: 'Electric field strength (E)' },
      { p: 'Field strength is the force experienced per unit positive charge placed at that point.' },
      { f: 'E = F / Q        (unit: N/C or V/m)' },
      { p: 'For a point charge Q, the field at a distance r away is:' },
      { f: 'E = kQ / r²      where k = 9.0 × 10⁹ N m² C⁻²' },
      { h: 'Worked example' },
      { p: 'A charge of 2 × 10⁻⁶ C sits in a field and experiences a force of 0.5 N. Find the field strength.' },
      { f: 'E = F / Q = 0.5 / (2 × 10⁻⁶) = 2.5 × 10⁵ N/C' },
      { h: 'Electric potential (V)' },
      { p: 'Electric potential at a point is the work done in bringing one coulomb of positive charge from infinity to that point.' },
      { f: 'V = W / Q = kQ / r      (unit: volt, V)' },
      { p: 'Potential is a scalar — no direction. Potential difference between two points is simply the work done moving one coulomb from one point to the other, and that is exactly what your voltmeter reads.' },
      { h: 'Common mistakes to avoid' },
      { list: [
        'Mixing up field strength (vector, N/C) with potential (scalar, V)',
        'Forgetting to square r in the E = kQ/r² formula',
        'Leaving the charge in microcoulombs instead of converting to coulombs',
      ] },
    ],
    pidgin: [
      { h: 'Wetin be electric field?' },
      { p: 'Electric field na the area around any charged body wey the force dey work. If you put another charge for inside that area, e go either pull am or push am comot. The field get direction: e dey comot from positive charge, e dey enter negative charge.' },
      { naija: 'Take transformer wey dey your street. You no need touch am before you know say wahala dey there. As you dey near am, na so the effect dey increase. That area of influence na the electric field.' },
      { h: 'Field strength (E)' },
      { p: 'Field strength na the force wey one unit of positive charge go feel for that point.' },
      { f: 'E = F / Q        (unit: N/C or V/m)' },
      { p: 'If na one point charge Q, the field for distance r away na:' },
      { f: 'E = kQ / r²      where k = 9.0 × 10⁹ N m² C⁻²' },
      { h: 'Example wey we work' },
      { p: 'Charge of 2 × 10⁻⁶ C dey inside field and e feel force of 0.5 N. Find the field strength.' },
      { f: 'E = F / Q = 0.5 / (2 × 10⁻⁶) = 2.5 × 10⁵ N/C' },
      { h: 'Electric potential (V)' },
      { p: 'Potential na the work wey you do to carry one coulomb of positive charge from far far place come reach that point.' },
      { f: 'V = W / Q = kQ / r      (unit: volt, V)' },
      { p: 'Potential no get direction — na scalar. The difference between two points na wetin your voltmeter dey show you.' },
      { h: 'Mistake wey students dey make' },
      { list: [
        'Dem go mix field strength (vector) with potential (scalar)',
        'Dem go forget to square the r for E = kQ/r²',
        'Dem go leave the charge for microcoulomb instead of change am to coulomb',
      ] },
    ],
  },

  'chemistry:w3t1': {
    title: 'Understanding the Mole',
    subject: 'chemistry',
    week: 3,
    mins: 35,
    objectives: [
      'Define the mole and Avogadro number',
      'Convert between mass, moles and number of particles',
      'Apply the mole to gas volumes at s.t.p.',
    ],
    english: [
      { h: 'The mole is just a counting word' },
      { p: 'A dozen means 12. A carton of Peak milk means 24 tins. In the same way, a mole means 6.02 × 10²³ particles. That number is called Avogadro constant, and the particles can be atoms, molecules, ions or electrons — you must always say which.' },
      { naija: 'For market, you no dey buy egg one by one — you dey buy crate. Crate na 30 eggs. Chemist too no fit count atom one by one, so dem take mole as their own crate. Just that this crate na 602 followed by 21 zeros.' },
      { h: 'The three formulas that carry everything' },
      { f: 'number of moles = mass (g) ÷ molar mass (g/mol)' },
      { f: 'number of particles = moles × 6.02 × 10²³' },
      { f: 'volume of gas at s.t.p. = moles × 22.4 dm³' },
      { h: 'Worked example' },
      { p: 'How many moles are in 44 g of carbon dioxide? (C = 12, O = 16)' },
      { list: [
        'Molar mass of CO₂ = 12 + (16 × 2) = 44 g/mol',
        'Moles = 44 ÷ 44 = 1 mole',
        'So it also contains 6.02 × 10²³ molecules and occupies 22.4 dm³ at s.t.p.',
      ] },
      { h: 'Common mistakes to avoid' },
      { list: [
        'Using atomic mass where molar mass of the whole compound is needed',
        'Forgetting that 22.4 dm³ only applies at s.t.p., not at room temperature',
        'Saying "one mole of oxygen" without stating atoms or molecules',
      ] },
    ],
    pidgin: [
      { h: 'Mole na just counting word' },
      { p: 'Dozen mean 12. Carton of Peak milk mean 24 tins. Na so mole mean 6.02 × 10²³ particles. Dem call that number Avogadro constant. The particles fit be atom, molecule, ion or electron — you must talk which one.' },
      { naija: 'For market, you no dey buy egg one one — you dey buy crate, and crate na 30 eggs. Chemist no fit count atom one one, so dem carry mole as their own crate. Na only say this crate na 602 with 21 zeros follow am.' },
      { h: 'The three formula wey carry everything' },
      { f: 'moles = mass (g) ÷ molar mass (g/mol)' },
      { f: 'number of particles = moles × 6.02 × 10²³' },
      { f: 'volume of gas for s.t.p. = moles × 22.4 dm³' },
      { h: 'Example wey we work' },
      { p: 'How many mole dey inside 44 g of carbon dioxide? (C = 12, O = 16)' },
      { list: [
        'Molar mass of CO₂ = 12 + (16 × 2) = 44 g/mol',
        'Moles = 44 ÷ 44 = 1 mole',
        'So e get 6.02 × 10²³ molecules, and e go occupy 22.4 dm³ for s.t.p.',
      ] },
      { h: 'Mistake wey students dey make' },
      { list: [
        'Dem go use atomic mass where na molar mass of the whole compound dem suppose use',
        'Dem go forget say 22.4 dm³ na for s.t.p. only',
        'Dem go talk "one mole of oxygen" without talk whether na atom or molecule',
      ] },
    ],
  },

  'maths:w3t1': {
    title: 'Introduction to Logarithms',
    subject: 'maths',
    week: 3,
    mins: 30,
    objectives: [
      'Convert between index form and logarithm form',
      'Evaluate simple logarithms without a calculator',
      'State and use the three laws of logarithms',
    ],
    english: [
      { h: 'A logarithm is a question about a power' },
      { p: 'When we write log₁₀ 1000 = 3, we are answering: "10 raised to what power gives 1000?" The answer is 3. So a logarithm is simply an index written the other way round.' },
      { f: 'If aˣ = b, then log_a b = x' },
      { naija: 'Think of okada fare wey dey double every year: ₦100, ₦200, ₦400, ₦800. If somebody ask "how many years before fare reach ₦800?", you dey ask log₂(800/100) = log₂ 8 = 3 years. Na the same question.' },
      { h: 'The three laws you must know cold' },
      { f: 'log(MN) = log M + log N' },
      { f: 'log(M/N) = log M − log N' },
      { f: 'log(Mⁿ) = n log M' },
      { h: 'Worked example' },
      { p: 'Evaluate log₁₀ 20 + log₁₀ 5.' },
      { list: [
        'Use the first law: log₁₀(20 × 5) = log₁₀ 100',
        '10 raised to what power gives 100? The answer is 2',
        'So the value is 2',
      ] },
      { h: 'Common mistakes to avoid' },
      { list: [
        'Writing log M + log N as log(M + N) — it is log(MN)',
        'Forgetting that log 1 = 0 for any base',
        'Trying to take the log of a negative number or zero — it does not exist',
      ] },
    ],
    pidgin: [
      { h: 'Logarithm na question about power' },
      { p: 'When we write log₁₀ 1000 = 3, we dey ask: "10 raise to which power go give 1000?" Answer na 3. So logarithm na just index wey dem turn round.' },
      { f: 'If aˣ = b, then log_a b = x' },
      { naija: 'Imagine okada fare wey dey double every year: ₦100, ₦200, ₦400, ₦800. If person ask "how many year before e reach ₦800?", na log₂ 8 = 3 years you dey solve. Same question.' },
      { h: 'The three law wey you must sabi' },
      { f: 'log(MN) = log M + log N' },
      { f: 'log(M/N) = log M − log N' },
      { f: 'log(Mⁿ) = n log M' },
      { h: 'Example wey we work' },
      { p: 'Find log₁₀ 20 + log₁₀ 5.' },
      { list: [
        'Use the first law: log₁₀(20 × 5) = log₁₀ 100',
        '10 raise to which power go give 100? Na 2',
        'So the answer na 2',
      ] },
      { h: 'Mistake wey students dey make' },
      { list: [
        'Dem go write log M + log N as log(M + N) — no be so, na log(MN)',
        'Dem go forget say log 1 = 0 for any base',
        'Dem go try take log of negative number — e no exist',
      ] },
    ],
  },
};

/* Section shapes reused by the generator so every lesson has real structure. */
function generatedLesson(subjectId, topicTitle, weekTitle, mins) {
  const eng = [
    { h: `What "${topicTitle}" is about` },
    { p: `${topicTitle} sits inside the ${weekTitle} module. Before you memorise anything, understand the idea: examiners rarely ask you to recall a definition alone — they ask you to apply it to a situation you have not seen before.` },
    { naija: `Whenever this topic shows up in an exam, look for the everyday version of it first. If you can explain it to your younger brother using something for the house, market or road, you truly understand am.` },
    { h: 'Key points to hold' },
    { list: [
      `The definition of ${topicTitle.toLowerCase()}, stated in one clean sentence`,
      'The formula or rule, plus the unit that goes with it',
      'One worked example you can reproduce from memory',
      'The one place students usually lose marks',
    ] },
    { h: 'How this is examined' },
    { p: 'WAEC tends to ask this as a two-part question: define or explain first, then calculate or apply. JAMB compresses it into a single objective question, usually with one distractor built from the most common mistake.' },
    { h: 'Your next step' },
    { p: 'Finish this lesson, take the 5-question classwork below, then run a timed practice set on this topic. If you score under 60%, ask the AI tutor to explain it again in the other language — a second angle often does it.' },
  ];
  const pid = [
    { h: `Wetin "${topicTitle}" mean` },
    { p: `${topicTitle} dey inside the ${weekTitle} module. Before you cram anything, make you understand the idea. Examiner no dey just ask you definition — dem go give you new situation make you apply am.` },
    { naija: `Any time this topic show for exam, first find the everyday version. If you fit explain am give your junior brother with something wey dey house, market or road, then you don sabi am well well.` },
    { h: 'The main point dem' },
    { list: [
      `The definition of ${topicTitle.toLowerCase()}, for one clean sentence`,
      'The formula or rule, with the unit wey follow am',
      'One example wey you fit reproduce from your head',
      'The place wey students dey lose mark',
    ] },
    { h: 'How dem dey set am for exam' },
    { p: 'WAEC go ask am for two part: explain first, then calculate. JAMB go squeeze am into one objective question, and the wrong option dem go build am from the common mistake.' },
    { h: 'Wetin you go do next' },
    { p: 'Finish this lesson, do the 5-question classwork, then run timed practice for this topic. If you score under 60%, tell the AI tutor make e explain am for the other language.' },
  ];
  return {
    title: topicTitle,
    subject: subjectId,
    mins: mins || 25,
    objectives: [
      `Explain ${topicTitle.toLowerCase()} in your own words`,
      'Apply it to a worked example',
      'Answer exam-style questions on it',
    ],
    english: eng,
    pidgin: pid,
  };
}

/** Fetch a lesson by "subject:topicId". Always returns something renderable. */
export function lessonFor(key, topicTitle = 'This topic', weekTitle = 'this module', mins = 25) {
  if (LESSONS[key]) return LESSONS[key];
  const [subjectId] = key.split(':');
  return generatedLesson(subjectId, topicTitle, weekTitle, mins);
}

export const hasRichLesson = (key) => Boolean(LESSONS[key]);

/* --- Classwork attached to the end of a lesson ----------------------------- */

export const CLASSWORK = {
  'physics:w4t1': [
    {
      q: 'The unit of electric field strength is:',
      options: ['N/C', 'J/C', 'C/N', 'V·m'],
      answer: 0,
      why: 'Field strength is force per unit charge, so newtons per coulomb (N/C), which is the same as V/m.',
    },
    {
      q: 'A charge of 4 × 10⁻⁶ C experiences a force of 0.8 N. The field strength is:',
      options: ['3.2 × 10⁻⁶ N/C', '2.0 × 10⁵ N/C', '5.0 × 10⁶ N/C', '0.32 N/C'],
      answer: 1,
      why: 'E = F/Q = 0.8 ÷ (4 × 10⁻⁶) = 2.0 × 10⁵ N/C.',
    },
    {
      q: 'Electric potential is best described as a:',
      options: ['Vector quantity', 'Scalar quantity', 'Unit of force', 'Type of current'],
      answer: 1,
      why: 'Potential has magnitude only — no direction — so it is a scalar.',
    },
  ],
  'chemistry:w3t1': [
    {
      q: 'How many molecules are in 2 moles of water?',
      options: ['6.02 × 10²³', '1.204 × 10²⁴', '3.01 × 10²³', '2.24 × 10²³'],
      answer: 1,
      why: '2 × 6.02 × 10²³ = 1.204 × 10²⁴ molecules.',
    },
    {
      q: 'The volume occupied by 0.5 mole of any gas at s.t.p. is:',
      options: ['11.2 dm³', '22.4 dm³', '44.8 dm³', '5.6 dm³'],
      answer: 0,
      why: '0.5 × 22.4 = 11.2 dm³.',
    },
    {
      q: 'The mass of 0.25 mole of NaCl (Na = 23, Cl = 35.5) is:',
      options: ['58.5 g', '29.25 g', '14.625 g', '234 g'],
      answer: 2,
      why: 'Molar mass = 58.5 g/mol, so 0.25 × 58.5 = 14.625 g.',
    },
  ],
  'maths:w3t1': [
    {
      q: 'Evaluate log₁₀ 1000.',
      options: ['10', '100', '3', '30'],
      answer: 2,
      why: '10³ = 1000, so the logarithm is 3.',
    },
    {
      q: 'log 12 − log 4 equals:',
      options: ['log 8', 'log 3', 'log 48', '3'],
      answer: 1,
      why: 'log(M/N) = log M − log N, so it is log(12 ÷ 4) = log 3.',
    },
    {
      q: 'If log₂ x = 5, then x is:',
      options: ['10', '25', '32', '7'],
      answer: 2,
      why: 'x = 2⁵ = 32.',
    },
  ],
};

/** Generic classwork so every lesson ends with something to attempt. */
export function classworkFor(key, topicTitle = 'this topic') {
  if (CLASSWORK[key]) return CLASSWORK[key];
  return [
    {
      q: `Which of these best describes ${topicTitle.toLowerCase()}?`,
      options: [
        'A definition you memorise word for word',
        'A concept you apply to new situations',
        'A topic that never appears in WAEC',
        'A formula with no unit',
      ],
      answer: 1,
      why: 'Examiners test application far more often than recall. Understand the idea, then the formula follows.',
    },
    {
      q: 'When you get a question on this topic wrong, the best next step is to:',
      options: [
        'Skip it and move to another subject',
        'Memorise the correct option',
        'Ask the tutor to explain it a second way, then retry',
        'Wait for the teacher next term',
      ],
      answer: 2,
      why: 'A second explanation plus an immediate retry is what actually moves your mastery score.',
    },
  ];
}
