/* forgot-password.js — asks the server to email a reset link. */

import { boot, showError, clearError } from '../core/boot.js';
import { $, toast } from '../core/ui.js';
import * as api from '../core/api.js';

boot();

const email = $('#email');
const errorBox = $('#formError');
const form = $('#forgotForm');

email.addEventListener('input', () => clearError(errorBox));

const looksLikeEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value);

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  clearError(errorBox);

  const address = email.value.trim();
  const submit = form.querySelector('button[type="submit"]');

  if (!looksLikeEmail(address)) return showError(errorBox, 'Enter a valid email address.');

  submit.disabled = true;
  submit.textContent = 'Sending…';

  const res = await api.forgotPassword(address);

  submit.disabled = false;
  submit.textContent = 'Send reset link';

  if (res.offline) {
    return showError(
      errorBox,
      'You need network to reset a password. Your downloaded lessons still work offline.'
    );
  }

  /* The server deliberately answers the same way whether or not the address
     has an account — confirming it would turn this form into a user list. */
  form.innerHTML = `
    <div class="alert alert-success">
      <span>
        If <b>${address.replace(/[<>&"]/g, '')}</b> has an account, a reset link is on
        its way. Open it on this phone and you will be signed straight back in.
      </span>
    </div>
    <a class="btn btn-primary btn-lg btn-block" href="login.html" style="margin-top:var(--s-5)">
      Back to log in
    </a>`;

  toast('Check your email', 'success');
});
