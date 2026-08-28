<div>
  <h1 class="auth-title">Set a new password</h1>
  <p class="auth-sub">Choose something you will remember.</p>

  <form wire:submit="resetPassword" class="stack gap-5">
    <div class="field">
      <label class="label" for="email">Email address</label>
      <input class="input @error('email') is-invalid @enderror" id="email" type="email" wire:model="email">
      @error('email') <span class="error-text">{{ $message }}</span> @enderror
    </div>

    <div class="field">
      <label class="label" for="password">New password</label>
      <div class="input-group no-icon has-action">
        <input class="input @error('password') is-invalid @enderror" id="password" type="password"
        wire:model="password" autocomplete="new-password" placeholder="At least 8 characters">
        <button type="button" class="input-action" data-password-toggle="password"></button>
      </div>
      @error('password') <span class="error-text">{{ $message }}</span> @enderror
    </div>

    <div class="field">
      <label class="label" for="password_confirmation">Confirm new password</label>
      <div class="input-group no-icon has-action">
        <input class="input" id="password_confirmation" type="password"
        wire:model="password_confirmation" autocomplete="new-password">
        <button type="button" class="input-action" data-password-toggle="password_confirmation"></button>
      </div>
    </div>

    <button class="btn btn-primary btn-lg btn-block" type="submit">Save and log in</button>
  </form>
</div>
