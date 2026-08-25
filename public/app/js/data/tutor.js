/* ==========================================================================
   tutor.js — the offline AI tutor's response layer.

   This is the seam. Today it answers from an on-device rule set so the tutor
   works in flight mode; swapping in an on-device small model or a live API
   call means replacing `tutorReply` alone — every screen calls only this.
   ========================================================================== */

export const SUGGESTIONS = [
  'Explain this topic like I am 12',
  'Give me a Nigerian example',
  'What formula should I memorise?',
  'How does WAEC set this topic?',
  'I no understand — try Pidgin',
  'Give me 3 practice questions',
];

/* Topic-specific answers for the flagship lessons. */
const TOPIC_ANSWERS = {
  'electric field': {
    english: 'An electric field is the space around a charge where its force can be felt. Field strength E = F/Q, measured in N/C. For a point charge, E = kQ/r² with k = 9.0 × 10⁹. The two marks students lose most: forgetting to square r, and confusing field strength (a vector) with potential (a scalar, in volts).',
    pidgin: 'Electric field na the area around charge where the force dey work. E = F/Q, unit na N/C. For one point charge, E = kQ/r², k = 9.0 × 10⁹. Two place wey students dey fall hand: dem no go square the r, and dem go mix field strength (vector) with potential (scalar, for volts).',
  },
  'mole': {
    english: 'A mole is just a counting word, like a dozen. One mole = 6.02 × 10²³ particles. Three formulas carry the whole topic: moles = mass ÷ molar mass; particles = moles × 6.02 × 10²³; volume at s.t.p. = moles × 22.4 dm³. Always state whether you mean atoms or molecules.',
    pidgin: 'Mole na counting word, like dozen. One mole na 6.02 × 10²³ particles. Three formula carry the whole topic: moles = mass ÷ molar mass; particles = moles × 6.02 × 10²³; volume for s.t.p. = moles × 22.4 dm³. Always talk whether na atom or molecule you mean.',
  },
  'logarithm': {
    english: 'A logarithm answers "what power?" If aˣ = b then log_a b = x. The three laws: log(MN) = log M + log N, log(M/N) = log M − log N, log(Mⁿ) = n log M. Remember log 1 = 0 for every base, and you can never take the log of zero or a negative number.',
    pidgin: 'Logarithm dey ask "which power?" If aˣ = b, then log_a b = x. Three law: log(MN) = log M + log N, log(M/N) = log M − log N, log(Mⁿ) = n log M. Log 1 = 0 for any base, and you no fit take log of zero or negative number.',
  },
  'photosynthesis': {
    english: 'Photosynthesis is how green plants make food using sunlight, water and carbon dioxide, producing glucose and oxygen. Equation: 6CO₂ + 6H₂O → C₆H₁₂O₆ + 6O₂ in the presence of light and chlorophyll. It happens mainly in the palisade mesophyll, where chloroplasts are packed.',
    pidgin: 'Photosynthesis na how plant dey cook im own food with sunlight, water and carbon dioxide, and e go produce glucose and oxygen. Equation: 6CO₂ + 6H₂O → C₆H₁₂O₆ + 6O₂ with light and chlorophyll. Na for palisade mesophyll e dey happen pass.',
  },
  'concord': {
    english: 'Concord means the subject and verb must agree. Singular subject takes a singular verb. With "neither…nor" and "either…or", the verb follows the nearer subject. Watch out for phrases like "along with" and "as well as" — they do not change the number of the subject.',
    pidgin: 'Concord mean subject and verb must gree. Singular subject go take singular verb. For "neither…nor" and "either…or", the verb go follow the subject wey near am. "Along with" and "as well as" no dey change the subject number — na trap.',
  },
};

/* Intent rules, checked in order. */
const RULES = [
  {
    test: /^(hi|hello|hey|good (morning|afternoon|evening)|how far|abeg)/i,
    reply: {
      english: (ctx) => `Hello! I am your ClassMate tutor. We are on ${ctx.topic || 'your current topic'}. Ask me anything — explanations, worked examples, or practice questions.`,
      pidgin: (ctx) => `How far! Na me be your ClassMate tutor. We dey on ${ctx.topic || 'your topic'}. Ask me anything — explanation, example, or practice question.`,
    },
  },
  {
    test: /(don'?t|no|not) (understand|get)|confus|explain again|simpl|like i am|like a child/i,
    reply: {
      english: (ctx) => `No wahala — let me break ${ctx.topic || 'it'} down small small.\n\n1. Start with the idea in plain words, no formula.\n2. Then attach the formula to the idea, so the symbols mean something.\n3. Then do one example slowly, writing every line.\n\nTell me which of those three steps is giving you trouble and I will go deeper on that one.`,
      pidgin: (ctx) => `No wahala — make I break ${ctx.topic || 'am'} down small small.\n\n1. First, the idea for plain word, no formula.\n2. Then we attach the formula to the idea, so the symbol go carry meaning.\n3. Then we work one example slow slow, line by line.\n\nTell me which one dey give you wahala, make I go deep for that one.`,
    },
  },
  {
    test: /(nigerian|naija|local|everyday|real life) example|example.*(life|home|market)/i,
    reply: {
      english: (ctx) => `Here is the everyday version of ${ctx.topic || 'this topic'}:\n\nThink of something you already do without thinking — buying in the market, charging your phone at a business centre, NEPA taking light, or counting change in a danfo. Map each part of the concept onto one part of that scene. Once the scene makes sense, the formula is just the scene written in symbols.\n\nWant me to build the full example step by step?`,
      pidgin: (ctx) => `Make I give you the everyday version of ${ctx.topic || 'this topic'}:\n\nThink of something wey you dey do already — market buying, charging phone for business centre, NEPA take light, or counting change inside danfo. Match every part of the concept to one part of that scene. Once the scene make sense, the formula na just the scene wey dem write for symbol.\n\nYou want make I build the full example step by step?`,
    },
  },
  {
    test: /formula|memoris|memoriz|cram|remember/i,
    reply: {
      english: (ctx) => `For ${ctx.topic || 'this topic'}, do not cram a long list. Hold three things:\n\n• The main formula and what each letter stands for\n• The unit that comes out of it — units catch most careless mistakes\n• One rearranged version of the formula, since WAEC often gives you the answer and asks for an input\n\nWrite those three on one card. That card is your revision.`,
      pidgin: (ctx) => `For ${ctx.topic || 'this topic'}, no cram long list. Hold three thing:\n\n• The main formula and wetin every letter mean\n• The unit wey go comot — na unit dey catch careless mistake\n• One rearranged version, because WAEC go give you answer and ask you find input\n\nWrite those three for one card. That card na your revision.`,
    },
  },
  {
    test: /waec|neco|jamb|exam|utme|set (this|the) topic|past question/i,
    reply: {
      english: (ctx) => `How this topic is usually examined:\n\n• WAEC: two-part question — explain or define first, then calculate or apply. The marks are split, so never skip the explanation.\n• JAMB: one objective question, and the wrong options are usually built from the most common mistakes.\n• Timing: about 1 minute 30 seconds per objective question. If it takes longer, flag it and come back.\n\nTap Practice and choose this topic to run a timed set now.`,
      pidgin: (ctx) => `Na so dem dey set this topic:\n\n• WAEC: two part — explain first, then calculate. Dem dey share the mark, so no skip the explanation.\n• JAMB: one objective question, and the wrong option dem, na from common mistake dem build am.\n• Timing: around 1 minute 30 seconds per question. If e pass that, flag am, come back later.\n\nTap Practice, pick this topic, run timed set now now.`,
    },
  },
  {
    test: /practice question|give me.*question|test me|quiz/i,
    reply: {
      english: (ctx) => `Try these three on ${ctx.topic || 'this topic'} before I mark them:\n\n1. State the definition in one sentence, no formula.\n2. Work one calculation and show every line.\n3. Explain the one mistake most students make here, and why it is wrong.\n\nType your answers and I will mark them. Or open Practice for a full timed set from past questions.`,
      pidgin: (ctx) => `Try these three on ${ctx.topic || 'this topic'} make I mark am:\n\n1. Give the definition for one sentence, no formula.\n2. Work one calculation, show every line.\n3. Explain the mistake wey students dey make here, and why e wrong.\n\nType your answer make I mark am. Or open Practice for full timed set from past question.`,
    },
  },
  {
    test: /pidgin|speak.*(pidgin|naija)/i,
    reply: {
      english: () => 'Switched to Pidgin. Use the toggle at the top any time you want English back.',
      pidgin: () => 'We don switch to Pidgin. Use the toggle for top any time you want English back.',
    },
  },
  {
    test: /pass|fail|score|improve|prepare|study plan/i,
    reply: {
      english: (ctx) => `Here is the plan that actually moves scores:\n\n1. Finish the week's lessons — do not jump around the scheme.\n2. Do the classwork immediately, while the explanation is fresh.\n3. Run 20 timed past questions on the same topic the next day.\n4. Whatever you get wrong, bring it back here and ask me why.\n\nRepeat that loop for ${ctx.subject || 'each subject'} and your mastery score climbs on its own.`,
      pidgin: (ctx) => `This na the plan wey dey work:\n\n1. Finish the week lesson — no dey jump up and down.\n2. Do the classwork sharp sharp, while the explanation still fresh.\n3. Next day, run 20 timed past question for the same topic.\n4. Anything wey you miss, carry am come here, ask me why.\n\nDo that loop for ${ctx.subject || 'every subject'} and your mastery go climb by itself.`,
    },
  },
];

const FALLBACK = {
  english: (ctx, q) => `Good question. On ${ctx.topic || 'this topic'}, here is how I would think about "${q.slice(0, 90)}":\n\n• Start from the definition — what exactly is being described?\n• Identify what you are given and what is being asked. Write both down.\n• Pick the rule or formula that connects them, then substitute carefully with units.\n• Check that your answer is sensible in size and in unit.\n\nIf you tell me which step breaks down for you, I will work that step out in full.`,
  pidgin: (ctx, q) => `Correct question. For ${ctx.topic || 'this topic'}, na so I go take think about "${q.slice(0, 90)}":\n\n• Start from the definition — wetin exactly dem dey describe?\n• Write wetin dem give you, and wetin dem ask you find.\n• Pick the rule or formula wey join the two, then substitute with unit.\n• Check say the answer make sense — the size and the unit.\n\nIf you tell me which step dey hard you, I go work that step finish.`,
};

/**
 * @param {string} question   what the student typed
 * @param {object} ctx        { topic, subject, language: 'english'|'pidgin' }
 * @returns {string} the tutor's reply
 */
export function tutorReply(question, ctx = {}) {
  const lang = ctx.language === 'pidgin' ? 'pidgin' : 'english';
  const q = (question || '').trim();
  const lower = q.toLowerCase();

  /* A named topic beats a generic intent — the student asked about a thing. */
  for (const [key, answers] of Object.entries(TOPIC_ANSWERS)) {
    if (lower.includes(key)) return answers[lang];
  }
  for (const rule of RULES) {
    if (rule.test.test(lower)) return rule.reply[lang](ctx);
  }
  return FALLBACK[lang](ctx, q);
}

/** Opening message when a chat is started fresh. */
export function greeting(ctx = {}) {
  const lang = ctx.language === 'pidgin' ? 'pidgin' : 'english';
  const topic = ctx.topic ? ` We are on ${ctx.topic}.` : '';
  return lang === 'pidgin'
    ? `How far! Na me be your ClassMate tutor, and I dey work even when network no dey.${ctx.topic ? ` We dey on ${ctx.topic}.` : ''} Ask me anything.`
    : `Hi! I am your ClassMate tutor, and I work even with no network.${topic} Ask me anything about your subjects.`;
}
