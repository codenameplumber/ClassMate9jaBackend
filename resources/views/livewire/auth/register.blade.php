<div>
  <h1 class="auth-title">Create your account</h1>
  <p class="auth-sub">Takes under a minute. Free for students, forever.</p>

  <form wire:submit="register" class="stack gap-5">

    <div class="field">
      <span class="label">I am a…</span>
      <div class="grid grid-3 keep-2" style="gap:var(--s-2)">
        @foreach ([['student','&#127891;','Student'],['teacher','&#128105;','Teacher'],['parent','&#128106;','Parent']] as [$value, $emoji, $label])
          <button type="button" wire:click="$set('role', '{{ $value }}')"
                  class="choice {{ $role === $value ? 'is-active' : '' }}"
                  style="flex-direction:column;text-align:center;gap:var(--s-2);padding:var(--s-4) var(--s-2)">
            <span style="font-size:22px">{!! $emoji !!}</span>
            <span class="t-sm fw-semibold">{{ $label }}</span>
          </button>
        @endforeach
      </div>
    </div>

    <div class="field">
      <label class="label" for="name">Full name</label>
      <input class="input @error('name') is-invalid @enderror" id="name" type="text"
             wire:model="name" autocomplete="name" placeholder="e.g. Chidera Okonkwo">
      @error('name') <span class="error-text">{{ $message }}</span> @enderror
    </div>

    <div class="field">
      <label class="label" for="email">Email address</label>
      <input class="input @error('email') is-invalid @enderror" id="email" type="email"
             wire:model="email" autocomplete="email" placeholder="you@school.ng">
      <span class="hint">We send your password reset link here.</span>
      @error('email') <span class="error-text">{{ $message }}</span> @enderror
    </div>

    <div class="field">
      <label class="label" for="password">Create password</label>
      <input class="input @error('password') is-invalid @enderror" id="password" type="password"
             wire:model="password" autocomplete="new-password" placeholder="At least 8 characters">
      @error('password') <span class="error-text">{{ $message }}</span> @enderror
    </div>

    <div class="field">
      <label class="label" for="password_confirmation">Confirm password</label>
      <input class="input" id="password_confirmation" type="password"
             wire:model="password_confirmation" autocomplete="new-password" placeholder="Type it again">
    </div>

    <label class="switch" style="align-items:flex-start">
      <input type="checkbox" wire:model="terms">
      <span class="track" style="margin-top:2px"></span>
      <span class="t-sm muted">I agree to the terms of use and to ClassMate 9ja storing my progress.</span>
    </label>
    @error('terms') <span class="error-text">{{ $message }}</span> @enderror

    <button class="btn btn-primary btn-lg btn-block" type="submit">
      <span wire:loading.remove wire:target="register">Create account</span>
      <span wire:loading wire:target="register">Creating…</span>
    </button>
  </form>

  <p class="t-sm muted center-text" style="margin-top:var(--s-6)">
    Already have an account? <a class="fw-semibold" href="{{ route('login') }}">Log in</a>
  </p>
</div>
