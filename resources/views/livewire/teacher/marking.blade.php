<div x-data="{
        elapsed: 0,
        start: Date.now(),
        tick() { this.elapsed = Math.floor((Date.now() - this.start) / 1000) },
        clock() {
          const m = String(Math.floor(this.elapsed / 60)).padStart(2, '0');
          const s = String(this.elapsed % 60).padStart(2, '0');
          return m + ':' + s;
        }
     }"
     x-init="setInterval(() => tick(), 1000)"
     @keydown.window.prevent.a="$wire.approve()"
     @keydown.window.down="$wire.selectScript({{ $current ? $scripts->search(fn ($s) => $s->id === $current->id) + 1 : 0 }})"
     @keydown.window.up="$wire.previous()">

  @if (! $assignment)
    <div class="empty card">
      <div class="itile">✅</div>
      <h3>Nothing to mark</h3>
      <p class="t-sm">No script has been submitted to you yet.</p>
      <a class="btn btn-primary" href="{{ route('teacher.assignments') }}">Set an assignment</a>
    </div>
  @else

  <div class="page-head">
    <div>
      <h1>Marking queue</h1>
      <p class="sub">
        {{ $assignment->title }} · {{ $assignment->classroom->name }} ·
        {{ $assignment->maxScore() }} marks
      </p>
    </div>
    <div class="row gap-3 wrap">
      <select class="select" style="min-height:44px;width:auto" wire:model.live="assignmentId">
        @foreach ($assignments as $option)
          <option value="{{ $option->id }}">
            {{ $option->title }} — {{ $option->classroom->name }}
          </option>
        @endforeach
      </select>
      <button class="btn btn-secondary" wire:click="approveConfident"
              wire:confirm="Approve every script the marker was confident about? Flagged ones stay in the queue.">
        ⚡ Approve confident
      </button>
    </div>
  </div>

  {{-- The session meter: the five-minute claim, measured live --}}
  <section class="card card-pad-sm" style="margin-bottom:var(--s-5)">
    <div class="row between wrap gap-4">
      <div class="row gap-5 wrap">
        <div>
          <div class="t-2xs subtle upper">Approved this session</div>
          <div class="fw-black t-xl tnum">{{ $approvedThisSession }}</div>
        </div>
        <div>
          <div class="t-2xs subtle upper">Time spent</div>
          <div class="fw-black t-xl tnum mono" x-text="clock()">00:00</div>
        </div>
        <div>
          <div class="t-2xs subtle upper">Pace</div>
          <div class="fw-black t-xl tnum"
               x-text="{{ $approvedThisSession }} ? Math.round(elapsed / {{ max($approvedThisSession, 1) }}) + 's each' : '—'">—</div>
        </div>
      </div>
      <div class="grow" style="min-width:220px;max-width:420px">
        <div class="row between t-xs" style="margin-bottom:6px">
          <span class="muted">Queue progress</span>
          <span class="fw-bold">{{ $scripts->count() - $pending }}/{{ $scripts->count() }} approved</span>
        </div>
        <div class="bar">
          <i style="width:{{ $scripts->count() ? round(($scripts->count() - $pending) / $scripts->count() * 100) : 0 }}%"></i>
        </div>
      </div>
    </div>
  </section>

  <div class="mark-grid">

    {{-- Queue --}}
    <aside class="card side-card">
      <div class="card-header">
        <span class="card-title">Scripts</span>
        <span class="chip chip-outline">{{ $pending }} pending</span>
      </div>

      <div class="queue stack gap-1">
        @foreach ($scripts as $i => $script)
          <button wire:click="selectScript({{ $i }})"
                  class="queue-row {{ $current && $current->id === $script->id ? 'is-current' : '' }} {{ $script->isApproved() ? 'is-approved' : '' }}">
            <span class="avatar avatar-sm">{{ $script->student->initials() }}</span>
            <span class="grow" style="min-width:0">
              <span class="t-sm fw-semibold clamp-1" style="display:block">{{ $script->student->name }}</span>
              <span class="hint">
                @if ($script->isApproved()) ✓ Approved
                @elseif ($script->needs_review) ⚠ Needs your eye
                @else AI confident
                @endif
              </span>
            </span>
            <span class="score {{ $this->scoreFor($script) / max($script->max_score, 1) >= 0.5 ? 'success-text' : 'danger-text' }}">
              {{ $this->scoreFor($script) }}/{{ $script->max_score }}
            </span>
          </button>
        @endforeach
      </div>
    </aside>

    {{-- Open script --}}
    <section class="card card-pad-lg">
      @if (! $current)
        <div class="empty">
          <div class="itile">🎉</div>
          <h3>Queue clear</h3>
          <p class="t-sm">Every script on this assignment is approved.</p>
        </div>
      @else
        @php
          $flagged = $current->answers->filter(fn ($a) => ($a->ai_confidence ?? 100) < $floor)->count();
          $score = $this->scoreFor($current);
          $pct = $current->max_score ? round($score / $current->max_score * 100) : 0;
        @endphp

        <div class="row between wrap gap-4" style="margin-bottom:var(--s-5)">
          <div class="row gap-3">
            <span class="avatar avatar-lg">{{ $current->student->initials() }}</span>
            <div>
              <div class="fw-bold t-lg">{{ $current->student->name }}</div>
              <div class="hint">
                Submitted {{ $current->submitted_at->diffForHumans() }} ·
                {{ $assignment->classroom->name }}
              </div>
              <div class="row gap-2" style="margin-top:var(--s-2)">
                @if ($current->isApproved())
                  <span class="chip chip-brand">✓ Approved</span>
                @elseif ($flagged)
                  <span class="chip chip-amber">⚠ {{ $flagged }} {{ Str::plural('answer', $flagged) }} to check</span>
                @else
                  <span class="chip chip-brand">AI confident on all answers</span>
                @endif
              </div>
            </div>
          </div>

          <div class="center-text">
            <div class="t-2xs subtle upper">Total</div>
            <div style="font-size:var(--text-3xl);font-weight:800;line-height:1"
                 class="{{ $pct >= 50 ? 'success-text' : 'danger-text' }}">
              {{ $score }}<span class="subtle" style="font-size:var(--text-lg)">/{{ $current->max_score }}</span>
            </div>
            <div class="t-xs subtle tnum">{{ $pct }}%</div>
          </div>
        </div>

        <div class="alert alert-info" style="margin-bottom:var(--s-5)">
          <span>
            Every answer below carries a suggested mark and the comment the student
            will see. Change any mark with the +/− buttons; whatever you leave is
            what gets recorded.
          </span>
        </div>

        @foreach ($current->answers as $answer)
          @php
            $question = $answer->question;
            $mark = $this->markFor($current, $answer);
            $confidence = $answer->ai_confidence ?? 100;
            $tone = $confidence >= $floor ? '' : ($confidence >= 60 ? 'mid' : 'low');
            $changed = isset($overrides[$current->id][$answer->id]) || $answer->teacher_mark !== null;
          @endphp

          <article class="script-q {{ $confidence < $floor ? 'needs-eye' : '' }}">
            <div class="row between gap-3 wrap" style="margin-bottom:var(--s-3)">
              <span class="t-xs fw-bold subtle">
                QUESTION {{ $question->position }} · {{ $question->max_mark }} {{ Str::plural('mark', $question->max_mark) }} ·
                {{ $question->isWritten() ? 'written answer' : 'objective' }}
              </span>
              <span class="conf {{ $tone }}">
                {{ $confidence >= $floor ? '✓' : '⚠' }} AI {{ $confidence }}% sure
              </span>
            </div>

            <div class="fw-medium t-sm" style="margin-bottom:var(--s-3)">{{ $question->prompt }}</div>

            <div class="t-2xs subtle upper" style="margin-bottom:6px">Student wrote</div>
            <div class="given" style="margin-bottom:var(--s-3)">{{ $answer->answer_text }}</div>

            @if ($answer->ai_comment)
              <div class="alert {{ $mark === $question->max_mark ? 'alert-success' : ($mark === 0 ? 'alert-danger' : 'alert-warn') }}"
                   style="margin-bottom:var(--s-3)">
                <span><b>AI comment:</b> {{ $answer->ai_comment }}</span>
              </div>
            @endif

            <div class="row between wrap gap-3">
              <span class="t-2xs subtle">Expected: {{ $question->expected }}</span>
              <div class="mark-stepper">
                @if ($changed) <span class="chip chip-amber">Adjusted</span> @endif
                <button class="btn btn-secondary btn-icon btn-sm"
                        wire:click="step({{ $answer->id }}, -1)" aria-label="Reduce mark">−</button>
                <span class="val {{ $mark === $question->max_mark ? 'success-text' : ($mark === 0 ? 'danger-text' : '') }}">
                  {{ $mark }}/{{ $question->max_mark }}
                </span>
                <button class="btn btn-secondary btn-icon btn-sm"
                        wire:click="step({{ $answer->id }}, 1)" aria-label="Increase mark">+</button>
              </div>
            </div>
          </article>
        @endforeach

        <div class="mark-actions">
          <button class="btn btn-secondary" wire:click="previous">← Previous</button>
          <button class="btn btn-primary grow" wire:click="approve">
            @if ($current->isApproved())
              Approved — next script →
            @else
              Approve {{ $score }}/{{ $current->max_score }} and go next →
            @endif
          </button>
          <span class="t-2xs subtle hide-mobile">
            Press <span class="kbd">A</span> to approve · <span class="kbd">↑↓</span> to move
          </span>
        </div>
      @endif
    </section>
  </div>
  @endif
</div>
