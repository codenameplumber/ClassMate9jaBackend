<div>
  <h1 class="auth-title">Log in</h1>
  <p class="auth-sub">Welcome back. Your class dey wait.</p>

  <form wire:submit="login" class="stack gap-5">

    <div class="field">
      <label class="label" for="email">Email address</label>
      <input class="input @error('email') is-invalid @enderror" id="email" type="email"
             wire:model="email" autocomplete="username" placeholder="you@school.ng">
      @error('email') <span class="error-text">{{ $message }}</span> @enderror
    </div>

    <div class="field">
      <div class="row between">
        <label class="label" for="password">Password</label>
        <a class="t-xs fw-semibold" href="{{ route('password.request') }}">Forgot password?</a>
      </div>
      <div class="input-group no-icon has-action">
        <input class="input @error('password') is-invalid @enderror" id="password" type="password"
               wire:model="password" autocomplete="current-password" placeholder="Your password">
        <button type="button" class="input-action" data-password-toggle="password"></button>
      </div>
      @error('password') <span class="error-text">{{ $message }}</span> @enderror
    </div>

    <label class="switch">
      <input type="checkbox" wire:model="remember">
      <span class="track"></span>
      <span class="t-sm">Keep me logged in on this device</span>
    </label>

    <button class="btn btn-primary btn-lg btn-block" type="submit">
      <span wire:loading.remove wire:target="login">Log in</span>
      <span wire:loading wire:target="login">Signing you in…</span>
    </button>
  </form>

  {{-- Demo shortcut: a judge should reach a dashboard in two clicks. --}}
  <div class="card card-flat" style="margin-top:var(--s-6);background:var(--bg-sunken);border-style:dashed">
    <div class="row between" style="margin-bottom:var(--s-3)">
      <span class="t-xs fw-bold upper subtle">Demo accounts</span>
      <span class="chip chip-brand">Tap to fill</span>
    </div>
    <div class="row gap-2 wrap">
      <button type="button" class="pick" wire:click="useDemo('student')">&#127891; Student</button>
      <button type="button" class="pick" wire:click="useDemo('teacher')">&#128105; Teacher</button>
      <button type="button" class="pick" wire:click="useDemo('parent')">&#128106; Parent</button>
      <button type="button" class="pick" wire:click="useDemo('admin')">&#9881; Admin</button>
    </div>
  </div>

  <p class="t-sm muted center-text" style="margin-top:var(--s-6)">
    New here? <a class="fw-semibold" href="{{ route('register') }}">Create a free account</a>
  </p>
</div>
