<div>
  @if (! $classroom)
    <div class="empty card">
      <div class="itile">👥</div>
      <h3>No class yet</h3>
      <p class="t-sm">Once a class is created for you, its roster appears here.</p>
    </div>
  @else

  <div class="page-head">
    <div>
      <h1>{{ $classroom->name }}</h1>
      <p class="sub">{{ $classroom->level }} · {{ $classroom->room }} · {{ $stats['size'] }} students</p>
    </div>
    <div class="row gap-3">
      <span class="chip chip-brand">Join code: {{ $classroom->join_code }}</span>
      <a class="btn btn-primary" href="{{ route('teacher.assignments') }}">Set work for {{ $classroom->name }}</a>
    </div>
  </div>

  <div class="row gap-2 wrap" style="margin-bottom:var(--s-6)">
    @foreach ($classrooms as $c)
      <button wire:click="selectClass({{ $c->id }})"
              class="pick {{ $classroom->id === $c->id ? 'is-active' : '' }}">
        {{ $c->name }} · {{ $c->students_count }}
      </button>
    @endforeach
  </div>

  <section class="stat-grid" style="margin-bottom:var(--s-6)">
    <div class="stat-card">
      <div class="k">Class average</div>
      <div class="v">{{ $stats['average'] }}%</div>
      <div class="bar bar-sm" style="margin-top:var(--s-2)">
        <i class="{{ $stats['average'] >= 60 ? '' : ($stats['average'] >= 45 ? 'amber' : 'danger') }}"
           style="width:{{ $stats['average'] }}%"></i>
      </div>
    </div>
    <div class="stat-card">
      <div class="k">Students</div>
      <div class="v">{{ $stats['size'] }}</div>
      <div class="d">Enrolled in this class</div>
    </div>
    <div class="stat-card">
      <div class="k">Need attention</div>
      <div class="v danger-text">{{ $stats['at_risk'] }}</div>
      <div class="d">Below 45% average</div>
    </div>
    <div class="stat-card">
      <div class="k">Scripts marked</div>
      <div class="v">{{ $stats['approved'] }}<span class="subtle t-lg">/{{ $stats['submitted'] }}</span></div>
      <div class="d">Approved by you</div>
    </div>
  </section>

  <div class="split" style="--split:minmax(0,1.6fr) minmax(0,1fr)">
    <section class="card">
      <div class="card-header">
        <span class="card-title">Roster</span>
        <div class="row gap-2">
          <input class="input field-search" style="min-height:40px" placeholder="Find a student"
                 wire:model.live.debounce.300ms="search">
          <select class="select" style="min-height:40px;width:auto" wire:model.live="sort">
            <option value="name">A–Z</option>
            <option value="average">Weakest first</option>
          </select>
        </div>
      </div>

      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th>Student</th><th>Average</th><th>Scripts</th><th>Email</th></tr>
          </thead>
          <tbody>
            @forelse ($roster as $row)
              <tr>
                <td>
                  <span class="row gap-3">
                    <span class="avatar avatar-sm">{{ $row['student']->initials() }}</span>
                    <span class="fw-semibold">{{ $row['student']->name }}</span>
                    @if ($row['at_risk']) <span class="chip chip-danger">At risk</span> @endif
                  </span>
                </td>
                <td style="min-width:120px">
                  <div class="row gap-2">
                    <span class="tnum fw-bold">{{ $row['average'] }}%</span>
                    <span class="bar bar-sm grow" style="min-width:48px">
                      <i class="{{ $row['average'] >= 60 ? '' : ($row['average'] >= 45 ? 'amber' : 'danger') }}"
                         style="width:{{ $row['average'] }}%"></i>
                    </span>
                  </div>
                </td>
                <td class="tnum">{{ $row['submissions'] }}</td>
                <td class="subtle t-xs">{{ $row['student']->email }}</td>
              </tr>
            @empty
              <tr><td colspan="4"><div class="empty"><p class="t-sm">No student matches that name.</p></div></td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>

    <section class="card">
      <div class="card-header">
        <div>
          <span class="card-title">Topic health</span>
          <div class="hint">Class average, weakest first</div>
        </div>
      </div>
      <div class="stack gap-4">
        @forelse ($health as $row)
          <div>
            <div class="row between t-sm" style="margin-bottom:6px">
              <span class="fw-medium">{{ $row['topic'] }}</span>
              <span class="subtle tnum">{{ $row['average'] }}% · {{ $row['failing'] }} failing</span>
            </div>
            <div class="bar">
              <i class="{{ $row['average'] >= 70 ? '' : ($row['average'] >= 50 ? 'amber' : 'danger') }}"
                 style="width:{{ $row['average'] }}%"></i>
            </div>
          </div>
        @empty
          <p class="t-sm muted">No marked work yet, so there is nothing to analyse.</p>
        @endforelse
      </div>
    </section>
  </div>
  @endif
</div>
