<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{{ $title ?? 'Dashboard' }} · ClassMate 9ja</title>
<meta name="theme-color" content="#008751">
<link rel="manifest" href="/app/manifest.json">

{{-- One copy of the design system, shared by the server-rendered app and the
     offline student PWA, so the two can never drift apart visually. --}}
<link rel="stylesheet" href="/app/css/tokens.css">
<link rel="stylesheet" href="/app/css/base.css">
<link rel="stylesheet" href="/app/css/components.css">
<link rel="stylesheet" href="/app/css/layout.css">
<link rel="stylesheet" href="/app/css/pages/app.css">

<script>
  /* Resolve the theme before first paint so dark-mode users never see a flash. */
  try {
    var t = JSON.parse(localStorage.getItem('cm9ja:theme') || 'null')
      || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', t);
  } catch (e) {}
</script>
@livewireStyles
</head>
<body>

@php
    $user = auth()->user();
    $nav = \App\Support\Navigation::forRole($user->role);
    $tabs = \App\Support\Navigation::tabsForRole($user->role);
@endphp

<div class="app">

  <aside class="sidebar" id="sidebar" aria-label="Main navigation">
    <div class="sidebar-head">
      <a class="brand-mark" href="{{ route('home') }}">
        <span class="glyph">C</span>ClassMate&nbsp;<span class="naija">9ja</span>
      </a>
      <button class="btn btn-ghost btn-icon btn-sm hide-desktop" id="closeNav" aria-label="Close menu">✕</button>
    </div>

    <div class="sidebar-body">
      @foreach ($nav as $group)
        <div class="nav-group">
          <div class="nav-group-title">{{ $group['title'] }}</div>
          @foreach ($group['items'] as $item)
            <a class="nav-link {{ request()->routeIs($item['route']) ? 'is-active' : '' }}"
               href="{{ $item['url'] ?? route($item['route'], $item['params'] ?? []) }}"
               @if(!empty($item['external'])) data-external @endif>
              <span class="ico">{{ $item['emoji'] }}</span>{{ $item['label'] }}
              @if (!empty($item['badge']))
                <span class="badge-count" style="margin-left:auto">{{ $item['badge'] }}</span>
              @endif
            </a>
          @endforeach
        </div>
      @endforeach
    </div>

    <div class="sidebar-foot">
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="nav-link w-full" type="submit">
          <span class="ico">⎋</span>Sign out
        </button>
      </form>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="btn btn-ghost btn-icon hide-desktop" id="openNav" aria-label="Open menu">☰</button>

      <div class="grow" style="min-width:0">
        <div class="page-title clamp-1">{{ $title ?? 'Dashboard' }}</div>
        @isset($subtitle)
          <div class="t-2xs subtle clamp-1 hide-mobile">{{ $subtitle }}</div>
        @endisset
      </div>

      <span class="net-pill is-online" title="Connection status">
        <span class="badge-dot"></span><span class="hide-mobile">Online · synced</span>
      </span>

      <button class="btn btn-ghost btn-icon" id="themeBtn" aria-label="Switch theme">◐</button>

      <span class="avatar" title="{{ $user->name }}">{{ $user->initials() }}</span>
    </header>

    <div class="page">
      @if (session('status'))
        <div class="alert alert-success" style="margin-bottom:var(--s-5)">
          <span>{{ session('status') }}</span>
        </div>
      @endif

      {{ $slot }}
    </div>
  </div>

  <nav class="tabbar" aria-label="Quick navigation">
    @foreach ($tabs as $tab)
      <a class="{{ request()->routeIs($tab['route']) ? 'is-active' : '' }}"
         href="{{ $tab['url'] ?? route($tab['route'], $tab['params'] ?? []) }}">
        <span class="ico">{{ $tab['emoji'] }}</span>{{ $tab['label'] }}
      </a>
    @endforeach
  </nav>
</div>

<script>
/* The shell's only behaviour: the mobile drawer and the theme toggle.
   Everything else on these pages is Livewire. */
(function () {
  var sidebar = document.getElementById('sidebar');
  function open() {
    sidebar.classList.add('is-open');
    var s = document.createElement('div');
    s.className = 'scrim';
    s.id = 'scrim';
    s.addEventListener('click', close);
    document.body.appendChild(s);
  }
  function close() {
    sidebar.classList.remove('is-open');
    var s = document.getElementById('scrim');
    if (s) s.remove();
  }
  document.getElementById('openNav').addEventListener('click', open);
  document.getElementById('closeNav').addEventListener('click', close);

  var btn = document.getElementById('themeBtn');
  function paint() {
    btn.textContent = document.documentElement.getAttribute('data-theme') === 'dark' ? '☀' : '☾';
  }
  paint();
  btn.addEventListener('click', function () {
    var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    try { localStorage.setItem('cm9ja:theme', JSON.stringify(next)); } catch (e) {}
    paint();
  });
})();
</script>
@livewireScripts
</body>
</html>
