<div>
  <h1 class="auth-title">Check your email</h1>
  <p class="auth-sub">
    We sent a verification link to <b>{{ auth()->user()->email }}</b>. You can keep
    using ClassMate 9ja while you wait — verifying just secures your account.
  </p>

  @if (session('status'))
    <div class="alert alert-success" style="margin:var(--s-4) 0">{{ session('status') }}</div>
  @endif

  <div class="stack gap-3">
    <a class="btn btn-primary btn-block" href="{{ auth()->user()->homeRoute() }}">Continue to my dashboard</a>

    <button class="btn btn-secondary btn-block" type="button" wire:click="resend"
            wire:loading.attr="disabled" wire:target="resend">
      <span wire:loading.remove wire:target="resend">Resend the link</span>
      <span wire:loading wire:target="resend">Sending…</span>
    </button>
  </div>
</div>
