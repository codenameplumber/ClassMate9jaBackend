<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{{ $title ?? 'Sign in' }} · ClassMate 9ja</title>
<meta name="theme-color" content="#008751">
<link rel="stylesheet" href="/app/css/tokens.css">
<link rel="stylesheet" href="/app/css/base.css">
<link rel="stylesheet" href="/app/css/components.css">
<link rel="stylesheet" href="/app/css/layout.css">
<script>
  try {
    var t = JSON.parse(localStorage.getItem('cm9ja:theme') || 'null')
      || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', t);
  } catch (e) {}
</script>
@livewireStyles
<script src="/app/js/password-toggle.js" defer></script>
</head>
<body>

<div class="auth-wrap">
  <aside class="auth-aside">
    <a class="brand-mark" href="{{ route('home') }}">
      <span class="glyph">C</span>ClassMate&nbsp;<span class="naija">9ja</span>
    </a>

    <div class="stack gap-6">
      <h2 style="font-size:var(--text-3xl);color:#fff;max-width:17ch">
        {{ $asideHeading ?? 'Your AI teacher, inside your phone.' }}
      </h2>
      <div class="stack gap-4" style="margin-top:var(--s-2)">
        <div class="row gap-3 t-sm" style="color:rgba(255,255,255,.9)">
          <span class="itile itile-sm" style="background:rgba(255,255,255,.18);color:#fff">&#128218;</span>
          A 12-week scheme of work for every subject
        </div>
        <div class="row gap-3 t-sm" style="color:rgba(255,255,255,.9)">
          <span class="itile itile-sm" style="background:rgba(255,255,255,.18);color:#fff">&#9997;</span>
          Classwork marked the second it is submitted
        </div>
        <div class="row gap-3 t-sm" style="color:rgba(255,255,255,.9)">
          <span class="itile itile-sm" style="background:rgba(255,255,255,.18);color:#fff">&#128246;</span>
          Students keep learning with the data off
        </div>
      </div>
    </div>

    <p class="t-xs" style="color:rgba(255,255,255,.6)">
      &copy; {{ date('Y') }} ClassMate 9ja
    </p>
  </aside>

  <main class="auth-main">
    <div class="auth-card">
      <a class="brand-mark hide-desktop" href="{{ route('home') }}" style="margin-bottom:var(--s-8)">
        <span class="glyph">C</span>ClassMate&nbsp;<span class="naija">9ja</span>
      </a>

      @if (session('status'))
        <div class="alert alert-success" style="margin-bottom:var(--s-5)">
          <span>{{ session('status') }}</span>
        </div>
      @endif

      {{ $slot }}
    </div>
  </main>
</div>

@livewireScripts
</body>
</html>
