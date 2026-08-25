<div>
  @php $me = auth()->user(); @endphp

  <div class="page-head">
    <div>
      <h1>Good day, {{ $me->name }}</h1>
      <p class="sub">
        {{ $classrooms->count() }} {{ Str::plural('class', $classrooms->count()) }} ·
        {{ $classrooms->sum('students_count') }} students
      </p>
    </div>
    <div class="row gap-3">
      <a class="btn btn-secondary" href="{{ route('teacher.assignments') }}">+ New assignment</a>
      <a class="btn btn-primary" href="{{ route('teacher.marking') }}">Start marking</a>
    </div>
  </div>

  @if ($totalPending > 0)
    <section class="continue" style="margin-bottom:var(--s-6)">
      <div>
        <div class="eye">Waiting for you</div>
        <h2>{{ $totalPending }} {{ Str::plural('script', $totalPending) }} already marked by the AI</h2>
        <div class="meta">
          Every answer has a suggested mark and a comment. Glance, adjust what was
          flagged, approve. Most teachers clear a class in under five minutes.
        </div>
        <a class="btn btn-amber" style="margin-top:var(--s-5)" href="{{ route('teacher.marking') }}">
          Review and approve &rarr;
        </a>
      </div>
      <div class="center-text">
        <div style="font-size:64px;font-weight:800;line-height:1">{{ $totalPending }}</div>
        <div class="t-sm" style="opacity:.85">scripts pending</div>
      </div>
    </section>
  @else
    <div class="alert alert-success" style="margin-bottom:var(--s-6)">
      <span>Marking queue is empty. Every script you have received is approved.</span>
    </div>
  @endif

  @if ($stats)
    <section class="stat-grid" style="margin-bottom:var(--s-6)">
      <div class="stat-card">
        <div class="k">Classes</div>
        <div class="v">{{ $classrooms->count() }}</div>
        <div class="d">{{ $classrooms->sum('students_count') }} students in total</div>
      </div>
      <div class="stat-card">
        <div class="k">Class average</div>
        <div class="v">{{ $stats['average'] }}%</div>
        <div class="bar bar-sm" style="margin-top:var(--s-2)">
          <i class="{{ $stats['average'] >= 60 ? '' : ($stats['average'] >= 45 ? 'amber' : 'danger') }}"
             style="width:{{ $stats['average'] }}%"></i>
        </div>
      </div>
      <div class="stat-card">
        <div class="k">Need attention</div>
        <div class="v danger-text">{{ $stats['at_risk'] }}</div>
        <div class="d">Scoring below 45%</div>
      </div>
      <div class="stat-card">
        <div class="k">Submitted</div>
        <div class="v">{{ $stats['submitted'] }}</div>
        <div class="d">Scripts received this term</div>
      </div>
    </section>
  @endif

  <div class="grid" style="grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);gap:var(--s-6)">

    <div class="stack gap-6">
      <section class="card">
        <div class="card-header">
          <span class="card-title">Marking queue</span>
          <a class="t-xs fw-semibold" href="{{ route('teacher.marking') }}">Open queue</a>
        </div>

        @forelse ($queues as $queue)
          <div class="list-row">
            <span class="itile itile-sm {{ $queue['assignment']->kind === 'test' ? 'itile-amber' : '' }}">
              {{ $queue['assignment']->kind === 'test' ? '📊' : '✍️' }}
            </span>
            <div class="grow" style="min-width:0">
              <div class="t-sm fw-semibold clamp-1">{{ $queue['assignment']->title }}</div>
              <div class="hint">
                {{ $queue['assignment']->classroom->name }} ·
                {{ $queue['total'] - $queue['pending'] }}/{{ $queue['total'] }} approved
              </div>
            </div>
            <span class="chip chip-danger">{{ $queue['pending'] }} left</span>
            <a class="btn btn-secondary btn-sm hide-mobile"
               href="{{ route('teacher.marking', $queue['assignment']) }}">Mark</a>
          </div>
        @empty
          <div class="empty"><div class="itile">✅</div><p class="t-sm">Nothing waiting. Enjoy your break.</p></div>
        @endforelse
      </section>

      @if ($health->isNotEmpty())
        <section class="card">
          <div class="card-header">
            <div>
              <span class="card-title">Where {{ $classrooms->first()->name }} is struggling</span>
              <div class="hint">Average per topic, weakest first</div>
            </div>
            <a class="t-xs fw-semibold" href="{{ route('teacher.reports') }}">Full report</a>
          </div>

          @if ($health->first()['average'] < 55)
            <div class="alert alert-warn" style="margin-bottom:var(--s-5)">
              <span>
                <b>{{ $health->first()['failing'] }} students</b> are below 50% on
                <b>{{ $health->first()['topic'] }}</b>. That is a re-teach, not a revision.
              </span>
            </div>
          @endif

          <div class="stack gap-4">
            @foreach ($health as $row)
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
            @endforeach
          </div>
        </section>
      @endif

      <section class="card">
        <div class="card-header">
          <span class="card-title">My classes</span>
          <a class="t-xs fw-semibold" href="{{ route('teacher.classes') }}">Manage</a>
        </div>
        <div class="grid grid-3">
          @foreach ($classrooms as $classroom)
            <a class="qa" href="{{ route('teacher.classes') }}?class={{ $classroom->id }}">
              <span class="row between">
                <span class="itile itile-sm">{{ Str::substr($classroom->name, 0, 3) }}</span>
              </span>
              <span class="t">{{ $classroom->name }}</span>
              <span class="s">{{ $classroom->students_count }} students · {{ $classroom->level }}</span>
            </a>
          @endforeach
        </div>
      </section>
    </div>

    <div class="stack gap-6">
      @if ($atRisk->isNotEmpty())
        <section class="card">
          <div class="card-header">
            <span class="card-title">Students to watch</span>
            <span class="chip chip-danger">{{ $atRisk->count() }}</span>
          </div>
          @foreach ($atRisk as $row)
            <div class="list-row">
              <span class="avatar avatar-sm">{{ $row['student']->initials() }}</span>
              <div class="grow" style="min-width:0">
                <div class="t-sm fw-semibold clamp-1">{{ $row['student']->name }}</div>
                <div class="hint">{{ $row['submissions'] }} scripts marked</div>
              </div>
              <span class="chip chip-danger">{{ $row['average'] }}%</span>
            </div>
          @endforeach
        </section>
      @endif

      <section class="card card-flat" style="background:var(--surface-brand);border-color:var(--border-brand)">
        <div class="row gap-3">
          <span class="itile">🤖</span>
          <div>
            <div class="fw-bold t-sm">Let the AI set next week's work</div>
            <p class="t-xs muted" style="margin-top:4px">
              Pick a class and a topic; it drafts the questions and the marking
              scheme. You review before it goes out.
            </p>
            <a class="btn btn-soft btn-sm" style="margin-top:var(--s-3)"
               href="{{ route('teacher.assignments') }}">Draft an assignment</a>
          </div>
        </div>
      </section>
    </div>
  </div>
</div>
