/* login.js */

import { boot, showError, clearError, wirePasswordToggle } from '../core/boot.js';
import { icon } from '../core/icons.js';
import { $, toast } from '../core/ui.js';
import { signIn, adoptRemoteUser, routeHome, redirectIfSignedIn } from '../core/auth.js';
import * as api from '../core/api.js';
import { adoptServerState, syncInBackground } from '../core/sync.js';

boot();
redirectIfSignedIn();

wirePasswordToggle('#togglePw', '#password', icon);

const form = $('#loginForm');
const errorBox = $('#formError');

/* One-tap demo credentials — a judge should reach a dashboard in two clicks.
   These are the same accounts the Laravel seeder creates, so they work whether
   or not the device can reach the server. */
const DEMO = {
  student: ['student@classmate9ja.ng', 'password'],
  teacher: ['teacher@classmate9ja.ng', 'password'],
  parent:  ['parent@classmate9ja.ng', 'password'],
};

document.querySelectorAll('[data-demo]').forEach((btn) => {
  btn.addEventListener('click', () => {
    const [email, password] = DEMO[btn.dataset.demo];
    $('#identifier').value = email;
    $('#password').value = password;
    clearError(errorBox);
    document.querySelectorAll('[data-demo]').forEach((b) => b.classList.remove('is-active'));
    btn.classList.add('is-active');
    toast(`${btn.textContent.trim()} details filled — tap Log in`, 'success');
  });
});

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  clearError(errorBox);

  const identifier = $('#identifier').value.trim();
  const password = $('#password').value;
  const submit = form.querySelector('button[type="submit"]');

  if (!identifier) return showError(errorBox, 'Enter your email address.');
  if (!password)   return showError(errorBox, 'Enter your password.');

  submit.disabled = true;
  submit.textContent = 'Signing you in…';

  /* Try the server first so the account is real and progress syncs. If the
     device has no network we fall back to the local account, because being
     offline must never lock a student out of their own lessons. */
  const remote = await api.login(identifier, password);

  submit.disabled = false;
  submit.textContent = 'Log in';

  if (remote.ok && remote.data?.token) {
    api.setToken(remote.data.token);
    const user = adoptRemoteUser(remote.data.user);
    adoptServerState(remote.data.user.state);
    syncInBackground();
    toast(`Welcome back, ${user.name.split(' ')[0]}!`, 'success');
    return setTimeout(() => routeHome(user), 350);
  }

  if (!remote.offline && remote.status === 422) {
    $('#identifier').classList.add('is-invalid');
    return showError(errorBox, remote.data?.message || 'Those details do not match any account.');
  }

  const result = signIn(identifier, password);
  if (!result.ok) {
    $('#identifier').classList.add('is-invalid');
    return showError(errorBox, result.error);
  }

  toast(`Welcome back, ${result.user.name.split(' ')[0]} — working offline.`, 'warn');
  setTimeout(() => routeHome(result.user), 350);
});

$('#identifier').addEventListener('input', () => {
  $('#identifier').classList.remove('is-invalid');
  clearError(errorBox);
});
