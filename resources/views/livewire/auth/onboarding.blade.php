<div>
  <div class="steps" style="margin-bottom:var(--s-6)">
    @for ($i = 1; $i <= $this->steps(); $i++)
      <span class="dot {{ $i < $step ? 'is-done' : '' }} {{ $i === $step ? 'is-current' : '' }}"></span>
    @endfor
  </div>

  @if ($step === 1)
    <h1 class="auth-title">Which class are you in?</h1>
    <p class="auth-sub">This sets your scheme of work and the standard of your questions.</p>

    <div class="grid grid-3" style="gap:var(--s-3)">
      @foreach ($levels as $l)
        <button type="button" wire:click="$set('level', '{{ $l }}')"
                class="choice {{ $level === $l ? 'is-active' : '' }}"
                style="justify-content:center;padding:var(--s-5) var(--s-2)">
          <span class="fw-bold">{{ $l }}</span>
        </button>
      @endforeach
    </div>

  @elseif ($step === 2)
    <h1 class="auth-title">Which subjects are you offering?</h1>
    <p class="auth-sub">Pick as many as you carry. You can change these any time.</p>

    <div class="row between" style="margin-bottom:var(--s-4)">
      <span class="t-sm muted"><b>{{ count($subjects) }}</b> selected</span>
      <button type="button" class="btn btn-ghost btn-sm" wire:click="selectCore">Select the core subjects</button>
    </div>

    <div class="stack gap-2" style="max-height:46vh;overflow-y:auto">
      @foreach ($allSubjects as $subject)
        @php $on = in_array($subject->id, $subjects, true) @endphp
        <button type="button" wire:click="toggleSubject({{ $subject->id }})"
                class="choice {{ $on ? 'is-active' : '' }}">
          <span class="itile itile-sm" style="font-size:18px">{{ $subject->emoji }}</span>
          <span class="grow">
            <span class="fw-semibold t-sm" style="display:block">{{ $subject->short_name }}</span>
            <span class="hint">{{ $subject->topic_count }} topics · {{ $subject->pack_mb }} MB offline</span>
          </span>
          <span class="choice-key">{{ $on ? '✓' : '+' }}</span>
        </button>
      @endforeach
    </div>
    @error('subjects') <p class="error-text" style="margin-top:var(--s-3)">{{ $message }}</p> @enderror

  @elseif ($step === 3)
    <h1 class="auth-title">What are you preparing for?</h1>
    <p class="auth-sub">This weights your practice questions and mock timing.</p>

    <div class="stack gap-3">
      @foreach ([['waec','WAEC / NECO','Sitting for SSCE this session'],['jamb','JAMB UTME','Chasing admission this year'],['both','Both WAEC & JAMB','The full battle — most SS3 students'],['school','Just school work','Keeping up with class for now']] as [$value, $label, $hint])
        <button type="button" wire:click="$set('goal', '{{ $value }}')"
                class="choice {{ $goal === $value ? 'is-active' : '' }}">
          <span class="grow">
            <span class="fw-semibold t-sm" style="display:block">{{ $label }}</span>
            <span class="hint">{{ $hint }}</span>
          </span>
          <span class="choice-key" style="border-radius:999px">{{ $goal === $value ? '✓' : '' }}</span>
        </button>
      @endforeach
    </div>

  @else
    <h1 class="auth-title">How should your AI teacher talk?</h1>
    <p class="auth-sub">You can switch language on any lesson with one tap.</p>

    <div class="stack gap-3" style="margin-bottom:var(--s-8)">
      @foreach ([['en','English only','Formal, exam-style explanations'],['pcm','Pidgin only','Explain am for the way I sabi'],['both','Mix both','English first, Pidgin when it gets hard']] as [$value, $label, $hint])
        <button type="button" wire:click="$set('locale', '{{ $value }}')"
                class="choice {{ $locale === $value ? 'is-active' : '' }}">
          <span class="grow">
            <span class="fw-semibold t-sm" style="display:block">{{ $label }}</span>
            <span class="hint">{{ $hint }}</span>
          </span>
          <span class="choice-key" style="border-radius:999px">{{ $locale === $value ? '✓' : '' }}</span>
        </button>
      @endforeach
    </div>

    <h4 style="margin-bottom:var(--s-2)">Daily study goal</h4>
    <p class="hint" style="margin-bottom:var(--s-4)">Your streak counts a day once you hit this.</p>
    <div class="row gap-2 wrap">
      @foreach ([20 => '20 min', 30 => '30 min', 60 => '1 hour'] as $mins => $label)
        <button type="button" wire:click="$set('daily_goal', {{ $mins }})"
                class="pick {{ $daily_goal === $mins ? 'is-active' : '' }}">{{ $label }}</button>
      @endforeach
    </div>
  @endif

  <div class="row between gap-3" style="margin-top:var(--s-8)">
    <button class="btn btn-secondary" wire:click="back" @disabled($step === 1)>Back</button>
    <button class="btn btn-ghost btn-sm" wire:click="skip">Skip for now</button>
    <button class="btn btn-primary" wire:click="next">
      {{ $step === $this->steps() ? 'Finish setup ✓' : 'Continue' }}
    </button>
  </div>
</div>
