/* ==========================================================================
   password-toggle.js — show and hide what you are typing.

   A plain script rather than a module, because it is needed on the Livewire
   auth screens, which load no other JavaScript, as well as inside the offline
   app.

   It listens on the document rather than wiring each field. Two reasons, and
   both are about things that replace DOM after this file has run: Livewire
   swaps parts of the page on every keystroke it syncs, and the settings screen
   builds its password dialog with innerHTML. Anything bound directly to a
   button would stop working the moment either of those happened.

   Typing a password blind on a phone keyboard is how a Nigerian student ends
   up locked out of an account they know the password to.
   ========================================================================== */
(function () {
  'use strict';

  var OPEN = 'M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z';
  var CLOSED = 'M17.9 17.9A10.6 10.6 0 0 1 12 19C5 19 1 12 1 12a19 19 0 0 1 5.1-5.9'
    + 'M9.9 4.2A10.9 10.9 0 0 1 12 4c7 0 11 8 11 8a19 19 0 0 1-2.2 3.2M1 1l22 22'
    + 'M9.9 9.9a3 3 0 0 0 4.2 4.2';

  function svg(path, extra) {
    return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" '
      + 'stroke="currentColor" stroke-width="2" stroke-linecap="round" '
      + 'stroke-linejoin="round" aria-hidden="true">'
      + '<path d="' + path + '"></path>'
      + (extra ? '<circle cx="12" cy="12" r="3"></circle>' : '')
      + '</svg>';
  }

  /** The input this button belongs to: named explicitly, or the one beside it. */
  function fieldFor(button) {
    var id = button.getAttribute('data-password-toggle');

    if (id) return document.getElementById(id);

    var group = button.closest('.input-group') || button.parentElement;

    return group ? group.querySelector('input') : null;
  }

  function paint(button, revealed) {
    button.innerHTML = revealed ? svg(CLOSED, false) : svg(OPEN, true);
    button.setAttribute('aria-label', revealed ? 'Hide password' : 'Show password');
    button.setAttribute('aria-pressed', revealed ? 'true' : 'false');
    button.setAttribute('title', revealed ? 'Hide password' : 'Show password');
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-password-toggle]');
    if (!button) return;

    event.preventDefault();

    var field = fieldFor(button);
    if (!field) return;

    var revealed = field.type === 'text';
    field.type = revealed ? 'password' : 'text';

    paint(button, !revealed);

    /* Put the caret back where it was. Re-typing a password because the cursor
       jumped to the start is exactly the annoyance this feature exists to
       remove. */
    try {
      var at = field.value.length;
      field.focus();
      field.setSelectionRange(at, at);
    } catch (e) { /* some input types refuse setSelectionRange */ }
  });

  /* Draw any buttons already on the page, and any that arrive later. */
  function paintAll(root) {
    (root || document).querySelectorAll('[data-password-toggle]').forEach(function (button) {
      if (button.dataset.painted) return;
      button.dataset.painted = '1';
      paint(button, (fieldFor(button) || {}).type === 'text');
    });
  }

  document.addEventListener('DOMContentLoaded', function () { paintAll(); });
  document.addEventListener('livewire:navigated', function () { paintAll(); });

  /* The offline app builds dialogs with innerHTML, so watch for new ones. */
  if (window.MutationObserver) {
    new MutationObserver(function () { paintAll(); })
      .observe(document.documentElement, { childList: true, subtree: true });
  }

  paintAll();
})();
