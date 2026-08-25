<div>
  <div class="page-head">
    <div>
      <h1>Assignments</h1>
      <p class="sub">{{ $assignments->count() }} set this term</p>
    </div>
    <button class="btn btn-primary" wire:click="openDrafter">✨ Draft with AI</button>
  </div>

  <section class="card card-flat" style="background:var(--surface-brand);border-color:var(--border-brand);margin-bottom:var(--s-6)">
    <div class="row gap-4 wrap items-center">
      <span class="itile itile-lg">🤖</span>
      <div class="grow" style="min-width:220px">
        <div class="fw-bold">You do not write the questions any more</div>
        <p class="t-sm muted" style="margin-top:4px">
          Pick a class and a topic. The drafter pulls from the same scheme of work
          your students are following, and attaches a marking scheme to every
          question. You review it before a single student sees it.
        </p>
      </div>
      <button class="btn btn-primary" wire:click="openDrafter">Draft an assignment</button>
    </div>
  </section>

  <section class="card">
    <div class="card-header">
      <span class="card-title">All assignments</span>
      <span class="chip chip-outline">{{ $assignments->count() }}</span>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Title</th><th>Class</th><th>Type</th><th>Set by</th><th>Due</th><th>Progress</th><th></th></tr>
        </thead>
        <tbody>
          @forelse ($assignments as $a)
            @php $pending = $a->submissions_count - $a->approved_count; @endphp
            <tr>
              <td>
                <div class="fw-semibold">{{ $a->title }}</div>
                <div class="hint">{{ $a->question_count }} questions</div>
              </td>
              <td>{{ $a->classroom->name }}</td>
              <td><span class="chip {{ $a->kind === 'test' ? 'chip-amber' : '' }}">{{ ucfirst($a->kind) }}</span></td>
              <td>
                <span class="chip {{ $a->ai_generated ? 'chip-brand' : 'chip-outline' }}">
                  {{ $a->ai_generated ? '🤖 AI' : '👩🏾‍🏫 You' }}
                </span>
              </td>
              <td class="subtle">{{ $a->due_on?->format('j M') ?? '—' }}</td>
              <td style="min-width:140px">
                @if ($a->submissions_count)
                  <div class="row gap-2">
                    <span class="tnum t-xs">{{ $a->approved_count }}/{{ $a->submissions_count }}</span>
                    <span class="bar bar-sm grow" style="min-width:50px">
                      <i style="width:{{ round($a->approved_count / max($a->submissions_count, 1) * 100) }}%"></i>
                    </span>
                  </div>
                  @if ($pending)
                    <span class="hint danger-text">{{ $pending }} to mark</span>
                  @else
                    <span class="hint">All marked</span>
                  @endif
                @else
                  <span class="hint">No submissions yet</span>
                @endif
              </td>
              <td>
                @if ($pending > 0)
                  <a class="btn btn-primary btn-sm" href="{{ route('teacher.marking', $a) }}">Mark</a>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7"><div class="empty"><p class="t-sm">No assignment yet.</p></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  {{-- Drafting --}}
  @if ($drafting)
    <div class="overlay" wire:click.self="$set('drafting', false)">
      <div class="modal modal-lg">
        @if (! $draft)
          <h3 style="margin-bottom:var(--s-2)">Draft an assignment</h3>
          <p class="t-sm muted" style="margin-bottom:var(--s-5)">
            Built from the same scheme of work your students follow.
          </p>

          <div class="stack gap-5">
            <div class="field">
              <label class="label">Class</label>
              <select class="select" wire:model.live="classroomId">
                @foreach ($classrooms as $c)
                  <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="field">
              <label class="label">Week / topic</label>
              <select class="select" wire:model="topicId">
                @foreach ($topics as $t)
                  <option value="{{ $t->id }}">Week {{ $t->week }} — {{ $t->title }}</option>
                @endforeach
              </select>
              @error('topicId') <span class="error-text">{{ $message }}</span> @enderror
            </div>

            <div class="grid grid-2">
              <div class="field">
                <span class="label">Type</span>
                <div class="row gap-2">
                  <button type="button" class="pick {{ $kind === 'classwork' ? 'is-active' : '' }}"
                          wire:click="$set('kind', 'classwork')">Classwork</button>
                  <button type="button" class="pick {{ $kind === 'test' ? 'is-active' : '' }}"
                          wire:click="$set('kind', 'test')">Test</button>
                </div>
              </div>
              <div class="field">
                <label class="label">Due date</label>
                <input class="input" type="date" wire:model="dueOn">
              </div>
            </div>

            <div class="field">
              <span class="label">Number of questions</span>
              <div class="row gap-2 wrap">
                @foreach ([3, 5, 10, 20] as $n)
                  <button type="button" class="pick {{ $count === $n ? 'is-active' : '' }}"
                          wire:click="$set('count', {{ $n }})">{{ $n }}</button>
                @endforeach
              </div>
            </div>

            <div class="row gap-3">
              <button class="btn btn-secondary grow" wire:click="$set('drafting', false)">Cancel</button>
              <button class="btn btn-primary grow" wire:click="generate">
                <span wire:loading.remove wire:target="generate">✨ Draft it</span>
                <span wire:loading wire:target="generate">Drafting…</span>
              </button>
            </div>
          </div>
        @else
          <div class="row between gap-3" style="margin-bottom:var(--s-4)">
            <div>
              <h3>{{ $draft['title'] }}</h3>
              <p class="hint">{{ count($draft['questions']) }} questions · due {{ $dueOn }}</p>
            </div>
            <span class="chip {{ ($draft['generated'] ?? false) ? 'chip-brand' : 'chip-outline' }}">
              {{ ($draft['generated'] ?? false) ? '🤖 AI draft' : '📚 From question bank' }}
            </span>
          </div>

          <div class="alert {{ ($draft['generated'] ?? false) ? 'alert-success' : 'alert-info' }}" style="margin-bottom:var(--s-4)">
            <span>{{ $draft['note'] }}</span>
          </div>

          <div class="stack gap-3" style="max-height:340px;overflow-y:auto;margin-bottom:var(--s-5)">
            @foreach ($draft['questions'] as $i => $q)
              <div class="script-q">
                <div class="row between gap-2" style="margin-bottom:var(--s-2)">
                  <span class="t-2xs fw-bold subtle">QUESTION {{ $i + 1 }} · {{ $q['max_mark'] }} marks</span>
                  <span class="chip chip-outline t-2xs">{{ $q['kind'] }}</span>
                </div>
                <div class="t-sm fw-medium" style="margin-bottom:var(--s-2)">{{ $q['prompt'] }}</div>
                <div class="t-xs subtle">Answer: {{ $q['expected'] }}</div>
              </div>
            @endforeach
          </div>

          <div class="row gap-3">
            <button class="btn btn-secondary grow" wire:click="$set('draft', [])">Back</button>
            <button class="btn btn-primary grow" wire:click="publish">Send to class</button>
          </div>
        @endif
      </div>
    </div>
  @endif
</div>
