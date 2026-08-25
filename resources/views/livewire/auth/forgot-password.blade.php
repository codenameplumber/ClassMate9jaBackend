<div>
  <a class="row gap-2 t-sm fw-semibold muted" href="{{ route('login') }}" style="margin-bottom:var(--s-5)">
    &larr; Back to log in
  </a>

  <h1 class="auth-title">Forgot your password?</h1>
  <p class="auth-sub">Enter the email on your account and we will send a reset link.</p>

  <form wire:submit="sendLink" class="stack gap-5">
    <div class="field">
      <label class="label" for="email">Email address</label>
      <input class="input @error('email') is-invalid @enderror" id="email" type="email"
             wire:model="email" autocomplete="email" placeholder="you@school.ng">
      @error('email') <span class="error-text">{{ $message }}</span> @enderror
    </div>

    <button class="btn btn-primary btn-lg btn-block" type="submit">
      <span wire:loading.remove wire:target="sendLink">Send reset link</span>
      <span wire:loading wire:target="sendLink">Sending…</span>
    </button>
  </form>

  <div class="alert alert-info" style="margin-top:var(--s-6)">
    <span>We never ask for your password by email. Anybody who does is not from ClassMate 9ja.</span>
  </div>
</div>
