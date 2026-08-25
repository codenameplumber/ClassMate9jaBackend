/* ==========================================================================
   subjects.js — the subject catalogue.
   Ships with the app so a student can browse and pick subjects with the
   network off. `packMb` is the download size of that subject's offline pack.
   ========================================================================== */

export const LEVELS = ['JSS1', 'JSS2', 'JSS3', 'SS1', 'SS2', 'SS3'];

export const GOALS = [
  { id: 'waec',   label: 'WAEC / NECO',    hint: 'Sitting for SSCE this session' },
  { id: 'jamb',   label: 'JAMB UTME',      hint: 'Chasing an admission this year' },
  { id: 'both',   label: 'Both WAEC & JAMB', hint: 'The full battle — most SS3 students' },
  { id: 'school', label: 'Just school work', hint: 'Keeping up with class for now' },
];

export const LANGUAGES = [
  { id: 'english', label: 'English only',      hint: 'Formal, exam-style explanations' },
  { id: 'pidgin',  label: 'Pidgin only',       hint: 'Explain am for the way I sabi' },
  { id: 'both',    label: 'Mix both',          hint: 'English first, Pidgin when it gets hard' },
];

export const DAILY_GOALS = [
  { id: 20, label: '20 min', hint: 'Light — before school' },
  { id: 30, label: '30 min', hint: 'Steady — recommended' },
  { id: 60, label: '1 hour', hint: 'Serious — exam season' },
];

export const SUBJECTS = [
  {
    id: 'maths', name: 'Mathematics', short: 'Maths', emoji: '📐', tone: 'brand',
    core: true, packMb: 38, topics: 64,
    blurb: 'Numbers, algebra, geometry, statistics — the subject that decides admission.',
  },
  {
    id: 'english', name: 'English Language', short: 'English', emoji: '📖', tone: 'sky',
    core: true, packMb: 31, topics: 58,
    blurb: 'Comprehension, lexis and structure, oral English, summary and essay.',
  },
  {
    id: 'physics', name: 'Physics', short: 'Physics', emoji: '⚡', tone: 'amber',
    core: true, packMb: 34, topics: 52,
    blurb: 'Mechanics, waves, electricity, modern physics — with worked calculations.',
  },
  {
    id: 'chemistry', name: 'Chemistry', short: 'Chemistry', emoji: '🧪', tone: 'violet',
    core: true, packMb: 33, topics: 49,
    blurb: 'Mole concept, organic chemistry, reactions and practical chemistry.',
  },
  {
    id: 'biology', name: 'Biology', short: 'Biology', emoji: '🧬', tone: 'brand',
    core: true, packMb: 36, topics: 55,
    blurb: 'Cells, ecology, genetics, human physiology and plant biology.',
  },
  {
    id: 'economics', name: 'Economics', short: 'Economics', emoji: '💹', tone: 'clay',
    core: true, packMb: 24, topics: 42,
    blurb: 'Demand and supply, national income, money and the Nigerian economy.',
  },
  {
    id: 'government', name: 'Government', short: 'Government', emoji: '🏛️', tone: 'sky',
    core: true, packMb: 22, topics: 40,
    blurb: 'Constitutions, arms of government, political parties and Nigerian history.',
  },
  {
    id: 'literature', name: 'Literature-in-English', short: 'Literature', emoji: '🎭', tone: 'violet',
    core: true, packMb: 27, topics: 36,
    blurb: 'Prose, drama, poetry, literary devices and the current WAEC texts.',
  },
  {
    id: 'agric', name: 'Agricultural Science', short: 'Agric', emoji: '🌾', tone: 'brand',
    core: true, packMb: 21, topics: 38,
    blurb: 'Crop and animal production, soil science, farm management, agric economics.',
  },
  {
    id: 'civic', name: 'Civic Education', short: 'Civic', emoji: '⚖️', tone: 'amber',
    core: false, packMb: 14, topics: 28,
    blurb: 'Citizenship, human rights, values and national consciousness.',
  },
  {
    id: 'furthermaths', name: 'Further Mathematics', short: 'F. Maths', emoji: '📊', tone: 'clay',
    core: false, packMb: 29, topics: 44,
    blurb: 'Matrices, calculus, vectors and statistics for science students.',
  },
  {
    id: 'geography', name: 'Geography', short: 'Geography', emoji: '🗺️', tone: 'sky',
    core: false, packMb: 26, topics: 41,
    blurb: 'Map reading, landforms, climate and the geography of Nigeria.',
  },
];

export const byId = (id) => SUBJECTS.find((s) => s.id === id) || null;

export const subjectsFor = (ids = []) => ids.map(byId).filter(Boolean);

/** Tailwind-less tone → the itile modifier class used across the app. */
export const toneClass = (tone) => (tone && tone !== 'brand' ? `itile-${tone}` : '');
