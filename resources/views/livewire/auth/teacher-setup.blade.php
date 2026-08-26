<div>
  <h1 class="auth-title">What do you teach?</h1>
  <p class="auth-sub">
    An administrator at your school checks every new teacher account before it
    goes live. Tell them what to approve.
  </p>

  <form wire:submit="submit" class="stack gap-5" style="margin-top:var(--s-6)">

    {{-- School -------------------------------------------------------- --}}
    <div class="field">
      <label class="label" for="school">Your school</label>
      <input class="input" id="school" type="text" list="known-schools"
             wire:model="school" placeholder="Government Secondary School, Wuse" autocomplete="off">
      <datalist id="known-schools">
        @foreach ($schools as $name)
          <option value="{{ $name }}"></option>
        @endforeach
      </datalist>
      <p class="hint">Start typing — pick yours from the list if it is already there.</p>
      @error('school') <p class="error-text">{{ $message }}</p> @enderror
    </div>

    <div class="field">
      <label class="label" for="phone">Phone number <span class="muted">(optional)</span></label>
      <input class="input" id="phone" type="tel" wire:model="phone" placeholder="0803 000 0000">
      @error('phone') <p class="error-text">{{ $message }}</p> @enderror
    </div>

    {{-- Subjects, per level ------------------------------------------- --}}
    <div class="field">
      <label class="label">Subjects and levels</label>
      <p class="hint" style="margin-bottom:var(--s-3)">
        Tick every year you take a subject for. Only levels the platform
        actually teaches are shown.
      </p>

      <div class="stack gap-3" style="max-height:44vh;overflow-y:auto">
        @foreach ($subjects as $row)
          @php
            $subject = $row['subject'];
            $chosen = count($this->teaching[$subject->id] ?? []);
          @endphp

          <div class="card {{ $chosen ? 'is-active' : '' }}">
            <div class="card-body stack gap-3">
              <div class="row" style="gap:var(--s-3);align-items:center">
                <span class="itile itile-sm" style="font-size:18px">{{ $subject->emoji }}</span>
                <span class="grow">
                  <span class="fw-semibold t-sm" style="display:block">{{ $subject->name }}</span>
                  <span class="hint">{{ \App\Support\Levels::describe($row['levels']) }}</span>
                </span>
                @if ($chosen)
                  <span class="chip chip-brand">{{ $chosen }} selected</span>
                @endif
              </div>

              <div class="row" style="gap:var(--s-2);flex-wrap:wrap">
                @foreach ($row['levels'] as $level)
                  <button type="button"
                          wire:click="toggle({{ $subject->id }}, '{{ $level }}')"
                          class="btn btn-sm {{ $this->isTeaching($subject->id, $level) ? 'btn-primary' : 'btn-secondary' }}">
                    {{ $level }}
                  </button>
                @endforeach
              </div>
            </div>
          </div>
        @endforeach
      </div>

      @error('teaching') <p class="error-text" style="margin-top:var(--s-3)">{{ $message }}</p> @enderror
    </div>

    <button class="btn btn-primary btn-block" type="submit" wire:loading.attr="disabled">
      <span wire:loading.remove wire:target="submit">Send to my administrator</span>
      <span wire:loading wire:target="submit">Sending…</span>
    </button>
  </form>

  <form method="POST" action="{{ route('logout') }}" style="margin-top:var(--s-4)">
    @csrf
    <button class="btn btn-ghost btn-block" type="submit">Sign out</button>
  </form>
</div>
