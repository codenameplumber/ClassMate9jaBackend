{{--
    The offline student app, served by Laravel.

    These twelve screens used to be static .html files, which is why their file
    extension ended up in the URL. They are one Blade view now, parameterised
    by which page module to load — the pages were identical 27-line shells, so
    twelve copies of the same head was twelve places for a stylesheet to be
    forgotten.

    Two things are injected below, and only these two:

      • the API token for this session, so the device can talk to /api without
        a second login of its own;
      • the signed-in account, so the app renders instantly and still renders
        with no network.

    Both are written into local storage before any module runs, under the same
    `cm9ja:` namespace store.js uses. After the first visit the service worker
    serves this page from cache, and the app keeps working on a phone with the
    data off — which is the entire point of it being a PWA.
--}}
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{{ $title }} · ClassMate 9ja</title>
<meta name="theme-color" content="#008751">
{{-- Sign-out is a POST, so the page has to carry a token for it. --}}
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="manifest" href="/app/manifest.json">
<link rel="stylesheet" href="/app/css/tokens.css">
<link rel="stylesheet" href="/app/css/base.css">
<link rel="stylesheet" href="/app/css/components.css">
<link rel="stylesheet" href="/app/css/layout.css">
<link rel="stylesheet" href="/app/css/pages/app.css">
<script>
  /* Resolve the theme before first paint, or the screen flashes white on an
     OLED phone at night. */
  try {
    var t = JSON.parse(localStorage.getItem('cm9ja:theme') || 'null')
      || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', t);
  } catch (e) {}

  /* Hand the device its credentials and its account. store.js reads these
     keys as JSON, so they are written as JSON. */
  try {
    localStorage.setItem('cm9ja:api-token', JSON.stringify(@json($apiToken)));
    localStorage.setItem('cm9ja:session-user', JSON.stringify(@json($profile)));
  } catch (e) {}
</script>
<script src="/app/js/password-toggle.js" defer></script>
</head>
<body>
{{-- The shell (sidebar, topbar, tab bar) is injected by shell.js; this page's
     content is rendered into .page by the module below. --}}
<script type="module" src="/app/js/pages/{{ $module }}.js"></script>
</body>
</html>
