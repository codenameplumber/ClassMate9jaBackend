<x-layouts.guest title="Verify your email">
  <h1 class="auth-title">Check your email</h1>
  <p class="auth-sub">
    We sent a verification link to <b>{{ auth()->user()->email }}</b>. You can keep
    using ClassMate 9ja while you wait — verifying just secures your account.
  </p>

  <div class="stack gap-3">
    <a class="btn btn-primary btn-block" href="{{ auth()->user()->homeRoute() }}">Continue to my dashboard</a>

    <form method="POST" action="{{ route('verification.send') }}">
      @csrf
      <button class="btn btn-secondary btn-block" type="submit">Resend the link</button>
    </form>
  </div>
</x-layouts.guest>
