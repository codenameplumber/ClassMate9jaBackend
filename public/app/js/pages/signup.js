/* signup.js */

import { boot, showError, clearError, wirePasswordToggle } from '../core/boot.js';
import { icon } from '../core/icons.js';
import { $, $$, toast } from '../core/ui.js';
import { param, goReplace } from '../core/paths.js';
import { signUp, adoptRemoteUser, redirectIfSignedIn } from '../core/auth.js';
import * as api from '../core/api.js';
import { syncInBackground } from '../core/sync.js';

boot();
redirectIfSignedIn();

wirePasswordToggle('#togglePw', '#password', icon);

const errorBox = $('#formError');
let role = param('role', 'student');

/* --- Role picker (deep-linkable from the homepage cards) ------------------ */
function paintRole() {
  $$('#roleGrid .choice').forEach((b) =>
    b.classList.toggle('is-active', b.dataset.role === role)
  );
}
if (!['student', 'teacher', 'parent'].includes(role)) role = 'student';
paintRole();

$$('#roleGrid .choice').forEach((btn) => {
  btn.addEventListener('click', () => { role = btn.dataset.role; paintRole(); });
});

/* --- Email field ---------------------------------------------------------- */
const email = $('#email');
email.addEventListener('input', () => clearError(errorBox));

const looksLikeEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value);

/* --- Password strength ---------------------------------------------------- */
const pwBar = $('#pwBar').firstElementChild;
const pwHint = $('#pwHint');

$('#password').addEventListener('input', (e) => {
  const v = e.target.value;
  let score = 0;
  if (v.length >= 6) score += 1;
  if (v.length >= 10) score += 1;
  if (/\d/.test(v) && /[a-z]/i.test(v)) score += 1;
  if (/[^A-Za-z0-9]/.test(v)) score += 1;

  const map = [
    ['0%',   '',       'Use a mix of letters and numbers.'],
    ['25%',  'danger', 'Too short — at least 6 characters.'],
    ['55%',  'amber',  'Okay. Add a number to make it stronger.'],
    ['80%',  '',       'Strong password.'],
    ['100%', '',       'Very strong password.'],
  ][v ? score + 1 : 0] || ['0%', '', ''];

  pwBar.style.width = map[0];
  pwBar.className = map[1];
  pwHint.textContent = map[2];
});

/* --- Submit --------------------------------------------------------------- */
$('#signupForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  clearError(errorBox);

  const name = $('#name').value.trim();
  const address = email.value.trim();
  const password = $('#password').value;
  const submit = $('#signupForm').querySelector('button[type="submit"]');

  if (!name) return showError(errorBox, 'Enter your full name.');
  if (!looksLikeEmail(address)) return showError(errorBox, 'Enter a valid email address.');
  if (password.length < 8) return showError(errorBox, 'Password must be at least 8 characters.');
  if (!$('#terms').checked) return showError(errorBox, 'Please accept the terms to continue.');

  submit.disabled = true;
  submit.textContent = 'Creating your account…';

  /* Create the account on the server so a teacher can add this student to a
     class and a parent can link to them. An account that exists only on one
     phone is invisible to everybody who matters. */
  const remote = await api.register(name, address, password);

  submit.disabled = false;
  submit.textContent = 'Create account';

  if (remote.ok && remote.data?.token) {
    api.setToken(remote.data.token);
    adoptRemoteUser(remote.data.user);
    syncInBackground();
    toast('Account created. Let us set you up.', 'success');
    return setTimeout(() => goReplace('auth/onboarding.html'), 400);
  }

  if (!remote.offline) {
    const message = remote.data?.errors?.email?.[0]
      || remote.data?.message
      || 'That did not go through. Check your details and try again.';
    return showError(errorBox, message);
  }

  /* No network: make the account locally so the student can still study, and
     the next sync will not lose the work they do in the meantime. */
  const result = signUp({ name, email: address, phone: '', password, role });
  if (!result.ok) return showError(errorBox, result.error);

  toast('Created offline — it will link to your school when you get network.', 'warn', 4200);
  setTimeout(() => goReplace('auth/onboarding.html'), 500);
});
