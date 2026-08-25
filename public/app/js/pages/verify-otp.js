/* verify-otp.js — step 2 of 3
   The OTP field is the highest-friction moment in the whole app, so it gets
   the most care: auto-advance, backspace-to-previous, full-code paste, and
   auto-submit on the sixth digit. */

import { boot, showError, clearError } from '../core/boot.js';
import { $, $$, toast } from '../core/ui.js';
import { go, goReplace } from '../core/paths.js';
import { verifyOtp, sendOtp, pendingOtpPhone, peekOtp } from '../core/auth.js';

boot();

const phone = pendingOtpPhone();
if (!phone) goReplace('auth/forgot-password.html');

const boxes = $$('#otpBoxes input');
const errorBox = $('#formError');

/* Mask as 0801 *** **78 — enough to confirm the number, not enough to leak it */
$('#maskedPhone').textContent =
  `${phone.slice(0, 4)} *** **${phone.slice(-2)}`;

const paintDemoCode = () => { $('#codeValue').textContent = peekOtp() || '------'; };
paintDemoCode();

/* --- Box behaviour -------------------------------------------------------- */

const value = () => boxes.map((b) => b.value).join('');

boxes.forEach((box, i) => {
  box.addEventListener('input', () => {
    box.value = box.value.replace(/\D/g, '').slice(0, 1);
    box.classList.toggle('is-filled', Boolean(box.value));
    clearError(errorBox);
    if (box.value && i < boxes.length - 1) boxes[i + 1].focus();
    if (value().length === 6) submit();
  });

  box.addEventListener('keydown', (e) => {
    if (e.key === 'Backspace' && !box.value && i > 0) {
      boxes[i - 1].focus();
      boxes[i - 1].value = '';
      boxes[i - 1].classList.remove('is-filled');
    }
    if (e.key === 'ArrowLeft' && i > 0) boxes[i - 1].focus();
    if (e.key === 'ArrowRight' && i < boxes.length - 1) boxes[i + 1].focus();
  });

  /* Paste the whole code into any box and it spreads across all six. */
  box.addEventListener('paste', (e) => {
    e.preventDefault();
    const digits = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
    digits.split('').forEach((d, k) => {
      boxes[k].value = d;
      boxes[k].classList.add('is-filled');
    });
    boxes[Math.min(digits.length, 5)].focus();
    if (digits.length === 6) submit();
  });
});

boxes[0].focus();

/* --- Verify --------------------------------------------------------------- */

function submit() {
  clearError(errorBox);
  const result = verifyOtp(value());
  if (!result.ok) {
    boxes.forEach((b) => { b.value = ''; b.classList.remove('is-filled'); });
    boxes[0].focus();
    return showError(errorBox, result.error);
  }
  toast('Phone verified', 'success');
  setTimeout(() => go('auth/reset-password.html'), 350);
}

$('#otpForm').addEventListener('submit', (e) => { e.preventDefault(); submit(); });

/* --- Resend countdown ------------------------------------------------------ */

const resendBtn = $('#resendBtn');
let countdown = $('#countdown');
let left = 45;
let timer;

function tick() {
  left -= 1;
  countdown.textContent = String(left);
  if (left <= 0) {
    clearInterval(timer);
    resendBtn.disabled = false;
    resendBtn.textContent = 'Resend code';
  }
}
timer = setInterval(tick, 1000);

resendBtn.addEventListener('click', () => {
  if (resendBtn.disabled) return;
  sendOtp(phone);
  paintDemoCode();
  toast('New code sent', 'success');
  left = 45;
  resendBtn.disabled = true;
  resendBtn.innerHTML = 'Resend in <span id="countdown">45</span>s';
  countdown = $('#countdown');   // the old node went with the innerHTML swap
  timer = setInterval(tick, 1000);
});
