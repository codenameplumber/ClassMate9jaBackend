<div>
  <div class="page-head">
    <div>
      <h1>Students</h1>
      <p class="sub">
        {{ number_format($students->total()) }}
        @if ($students->total() !== $total) of {{ number_format($total) }} @endif
        {{ Str::plural('student', $students->total()) }}
      </p>
    </div>
  </div>

  {{-- Filters ------------------------------------------------------- --}}
  <div class="row" style="gap:var(--s-3);margin-bottom:var(--s-5);flex-wrap:wrap">
    <input class="input field-search" type="search" wire:model.live.debounce.300ms="search"
           placeholder="Search by name or email">

    <select class="input" wire:model.live="level" style="max-width:150px">
      <option value="">All levels</option>
      @foreach ($levels as $l)
        <option value="{{ $l }}">{{ $l }}</option>
      @endforeach
    </select>

    <select class="input" wire:model.live="schoolId" style="max-width:240px">
      <option value="">All schools</option>
      @foreach ($schools as $school)
        <option value="{{ $school->id }}">{{ $school->name }}</option>
      @endforeach
    </select>

    @if ($search !== '' || $level !== '' || $schoolId)
      <button class="btn btn-ghost btn-sm" wire:click="clear">Clear</button>
    @endif
  </div>

  {{-- The roster ---------------------------------------------------- --}}
  <div class="card">
    <div class="table-wrap" style="overflow-x:auto">
      <table class="table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Level</th>
            <th>School</th>
            <th>Class</th>
            <th class="tnum">Subjects</th>
            <th class="tnum">Scripts</th>
            <th>Last seen</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($students as $student)
            <tr>
              <td>
                <b>{{ $student->name }}</b>
                <div class="hint">{{ $student->email }}</div>
              </td>
              <td>{{ $student->level ?? '—' }}</td>
              <td>{{ $student->school?->name ?? '—' }}</td>
              <td>{{ $student->enrolledClassrooms->pluck('name')->join(', ') ?: '—' }}</td>
              <td class="tnum">{{ $student->subjects_count }}</td>
              <td class="tnum">
                {{ $student->approved_count }}/{{ $student->submissions_count }}
              </td>
              <td>
                {{ $student->last_seen_at?->diffForHumans() ?? 'Never' }}
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7"><span class="muted">No student matches that.</span></td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div style="margin-top:var(--s-5)">{{ $students->links() }}</div>
</div>
