/* reset-password.js — step 3 of 3 */

import { boot, showError, clearError, wirePasswordToggle } from '../core/boot.js';
import { icon } from '../core/icons.js';
import { $, toast } from '../core/ui.js';
import { goReplace } from '../core/paths.js';
import { resetPassword, otpVerified, pendingOtpPhone, signIn, routeHome } from '../core/auth.js';

boot();

/* Nobody reaches this screen without passing the OTP step. */
if (!otpVerified()) goReplace('auth/forgot-password.html');

wirePasswordToggle('#togglePw', '#password', icon);

const errorBox = $('#formError');

$('#resetForm').addEventListener('submit', (e) => {
  e.preventDefault();
  clearError(errorBox);

  const password = $('#password').value;
  const confirm = $('#confirm').value;
  const phone = pendingOtpPhone();

  if (password.length < 6) return showError(errorBox, 'Password must be at least 6 characters.');
  if (password !== confirm) return showError(errorBox, 'The two passwords do not match.');

  const result = resetPassword(password);
  if (!result.ok) return showError(errorBox, result.error);

  toast('Password changed. Logging you in…', 'success');
  const session = signIn(phone, password);
  setTimeout(() => (session.ok ? routeHome(session.user) : goReplace('auth/login.html')), 500);
});

$('#confirm').addEventListener('input', () => clearError(errorBox));
