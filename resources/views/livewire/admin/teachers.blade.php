<div>
  <div class="page-head">
    <div>
      <h1>Teachers</h1>
      <p class="sub">
        @if ($pendingCount)
          <b>{{ $pendingCount }}</b> waiting for a decision.
        @else
          Nothing waiting. Every teacher account has been decided on.
        @endif
      </p>
    </div>
  </div>

  @if (session('status'))
    <div class="alert alert-success" style="margin-bottom:var(--s-5)">{{ session('status') }}</div>
  @endif

  {{-- Filters ------------------------------------------------------- --}}
  <div class="row" style="gap:var(--s-3);margin-bottom:var(--s-5);flex-wrap:wrap">
    <input class="input grow" type="search" wire:model.live.debounce.300ms="search"
           placeholder="Search by name or email" style="min-width:220px">

    <div class="row" style="gap:var(--s-2)">
      @foreach ([['pending', 'Waiting'], ['approved', 'Approved'], ['rejected', 'Turned down'], ['', 'All']] as [$value, $label])
        <button type="button" wire:click="$set('status', '{{ $value }}')"
                class="btn btn-sm {{ $status === $value ? 'btn-primary' : 'btn-secondary' }}">
          {{ $label }}
          @if ($value === 'pending' && $pendingCount)
            <span class="badge-count" style="margin-left:var(--s-2)">{{ $pendingCount }}</span>
          @endif
        </button>
      @endforeach
    </div>
  </div>

  {{-- The list ------------------------------------------------------ --}}
  <div class="stack gap-3">
    @forelse ($teachers as $teacher)
      <div class="card">
        <div class="card-body stack gap-3">

          <div class="row between" style="gap:var(--s-3);flex-wrap:wrap">
            <div class="grow">
              <div class="row" style="gap:var(--s-2);align-items:center">
                <b>{{ $teacher->name }}</b>
                @if ($teacher->isPending())
                  <span class="chip chip-amber">Waiting</span>
                @elseif ($teacher->isRejected())
                  <span class="chip chip-danger">Turned down</span>
                @else
                  <span class="chip chip-success">Approved</span>
                @endif
              </div>
              <div class="hint">
                {{ $teacher->email }}
                @if ($teacher->school) &middot; {{ $teacher->school->name }} @endif
                @if ($teacher->phone) &middot; {{ $teacher->phone }} @endif
              </div>
            </div>

            <div class="row" style="gap:var(--s-2)">
              @if (! $teacher->isApproved())
                <button class="btn btn-primary btn-sm" wire:click="approve({{ $teacher->id }})">Approve</button>
              @endif
              @if (! $teacher->isRejected())
                <button class="btn btn-secondary btn-sm" wire:click="startReject({{ $teacher->id }})">Turn down</button>
              @endif
              <button class="btn btn-ghost btn-sm" wire:click="edit({{ $teacher->id }})">Subjects</button>
            </div>
          </div>

          {{-- What they teach, and what they carry --}}
          <div class="row" style="gap:var(--s-4);flex-wrap:wrap">
            <span class="t-sm">
              <span class="muted">Teaches:</span>
              @forelse ($teacher->teachingSummary() as $row)
                <b>{{ $row['subject']->short_name }}</b>
                ({{ \App\Support\Levels::describe($row['levels']) }}){{ ! $loop->last ? ', ' : '' }}
              @empty
                <span class="muted">nothing assigned</span>
              @endforelse
            </span>

            @if ($teacher->isApproved())
              <span class="t-sm muted tnum">
                {{ $teacher->classrooms_count }} {{ Str::plural('class', $teacher->classrooms_count) }}
                &middot; {{ $teacher->students_count }} students
              </span>
            @endif

            <span class="t-sm muted">Applied {{ $teacher->created_at->diffForHumans() }}</span>
          </div>

          @if ($teacher->isRejected() && $teacher->rejected_reason)
            <p class="hint"><b>Reason given:</b> {{ $teacher->rejected_reason }}</p>
          @endif

          {{-- Turn-down reason --}}
          @if ($rejectingId === $teacher->id)
            <div class="stack gap-2" style="border-top:1px solid var(--line);padding-top:var(--s-3)">
              <label class="label" for="reason-{{ $teacher->id }}">
                Why? {{ $teacher->firstName() }} is shown this.
              </label>
              <textarea class="input" id="reason-{{ $teacher->id }}" rows="2"
                        wire:model="rejectReason"
                        placeholder="We have no record of you at this school."></textarea>
              @error('rejectReason') <p class="error-text">{{ $message }}</p> @enderror
              <div class="row" style="gap:var(--s-2)">
                <button class="btn btn-danger btn-sm" wire:click="reject">Turn down</button>
                <button class="btn btn-ghost btn-sm" wire:click="cancelReject">Cancel</button>
              </div>
            </div>
          @endif

          {{-- Assign subjects and levels --}}
          @if ($editingId === $teacher->id)
            <div class="stack gap-3" style="border-top:1px solid var(--line);padding-top:var(--s-3)">
              <b class="t-sm">What {{ $teacher->firstName() }} teaches</b>

              <div class="stack gap-2" style="max-height:40vh;overflow-y:auto">
                @foreach ($subjects as $subject)
                  @php
                    $levels = $subject->levels
                      ->sortBy(fn ($l) => array_search($l->level, \App\Support\Levels::ALL))
                      ->pluck('level')->all();
                  @endphp
                  @if ($levels)
                    <div class="row" style="gap:var(--s-3);align-items:center;flex-wrap:wrap">
                      <span class="t-sm" style="min-width:150px">
                        {{ $subject->emoji }} {{ $subject->short_name }}
                      </span>
                      @foreach ($levels as $level)
                        <button type="button"
                                wire:click="toggle({{ $subject->id }}, '{{ $level }}')"
                                class="btn btn-sm {{ $this->isTeaching($subject->id, $level) ? 'btn-primary' : 'btn-secondary' }}">
                          {{ $level }}
                        </button>
                      @endforeach
                    </div>
                  @endif
                @endforeach
              </div>

              <div class="row" style="gap:var(--s-2)">
                <button class="btn btn-primary btn-sm" wire:click="saveTeaching">Save</button>
                <button class="btn btn-ghost btn-sm" wire:click="cancelEdit">Cancel</button>
              </div>
            </div>
          @endif

        </div>
      </div>
    @empty
      <div class="card"><div class="card-body">
        <p class="muted">No teacher matches that.</p>
      </div></div>
    @endforelse
  </div>

  <div style="margin-top:var(--s-5)">{{ $teachers->links() }}</div>
</div>
