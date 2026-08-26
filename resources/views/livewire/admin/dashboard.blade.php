<div>
  <div class="page-head">
    <div>
      <h1>Platform overview</h1>
      <p class="sub">Everything currently in the database</p>
    </div>
  </div>

  <div class="alert {{ $aiReady ? 'alert-success' : 'alert-info' }}" style="margin-bottom:var(--s-6)">
    <span>
      @if ($aiReady)
        <b>AI marking is on.</b> Written answers are marked automatically and
        anything below the confidence floor is flagged for a teacher.
      @else
        <b>Running without an AI key.</b> Objective marking still runs in PHP and
        every screen works; written answers wait for a teacher instead. Add
        <code>OPENAI_API_KEY</code> to <code>.env</code> to switch on written marking,
        the tutor, and course writing. Check it with
        <code>php artisan ai:check</code>.
      @endif
    </span>
  </div>

  {{-- What actually needs a person ------------------------------------ --}}
  @if ($pendingTeachers || $emptyLevels || $writingNow || $unreviewed)
    <section class="card" style="margin-bottom:var(--s-6)">
      <div class="card-body stack gap-3">
        <b>Waiting on you</b>

        @if ($pendingTeachers)
          <div class="row between">
            <span>
              <b class="tnum">{{ $pendingTeachers }}</b>
              {{ Str::plural('teacher', $pendingTeachers) }} cannot mark until approved
            </span>
            <a class="btn btn-primary btn-sm" href="{{ route('admin.teachers') }}">Review</a>
          </div>
        @endif

        @if ($writingNow)
          <div class="row between">
            <span>
              <b class="tnum">{{ $writingNow }}</b>
              {{ Str::plural('course', $writingNow) }} being written right now
            </span>
            <a class="btn btn-ghost btn-sm" href="{{ route('admin.courses') }}">Watch</a>
          </div>
        @endif

        @if ($emptyLevels)
          <div class="row between">
            <span>
              <b class="tnum">{{ $emptyLevels }}</b>
              {{ Str::plural('level', $emptyLevels) }} declared but empty &mdash;
              students can pick these and find nothing
            </span>
            <a class="btn btn-secondary btn-sm" href="{{ route('admin.courses') }}">Fill them</a>
          </div>
        @endif

        @if ($unreviewed)
          <div class="row between">
            <span>
              <b class="tnum">{{ number_format($unreviewed) }}</b>
              AI-written questions nobody has checked
              <span class="hint" style="display:block">
                An answer key that is wrong marks a correct student wrong, and
                nothing on the screen looks broken.
              </span>
            </span>
          </div>
        @endif
      </div>
    </section>
  @endif

  {{-- Recent generation runs -------------------------------------------- --}}
  @if ($recentRuns->isNotEmpty())
    <section class="card" style="margin-bottom:var(--s-6)">
      <div class="card-body stack gap-2">
        <b>Course writing</b>
        @foreach ($recentRuns as $run)
          <div class="row between t-sm">
            <span>
              <b>{{ $run->subject?->short_name ?? 'Removed' }} {{ $run->level }}</b>
              <span class="muted">&middot; {{ $run->created_at->diffForHumans() }}</span>
            </span>
            @if ($run->isRunning())
              <span class="chip chip-amber">{{ ucfirst($run->status) }}</span>
            @elseif ($run->hasFailed())
              <span class="chip chip-danger">Failed</span>
            @else
              <span class="chip chip-success">{{ $run->topics_written }} topics</span>
            @endif
          </div>
        @endforeach
      </div>
    </section>
  @endif

  <section class="stat-grid" style="margin-bottom:var(--s-6)">
    <div class="stat-card"><div class="k">Subjects</div><div class="v">{{ $counts['subjects'] }}</div></div>
    <div class="stat-card"><div class="k">Topics</div><div class="v">{{ $counts['topics'] }}</div></div>
    <div class="stat-card">
      <div class="k">Lessons</div><div class="v">{{ $counts['lessons'] }}</div>
      <div class="d">English and Pidgin</div>
    </div>
    <div class="stat-card"><div class="k">Past questions</div><div class="v">{{ $counts['questions'] }}</div></div>
  </section>

  <section class="stat-grid" style="margin-bottom:var(--s-6)">
    <div class="stat-card">
      <div class="k">Accounts</div><div class="v">{{ $counts['users'] }}</div>
      <div class="d">{{ $counts['students'] }} students</div>
    </div>
    <div class="stat-card"><div class="k">Assignments</div><div class="v">{{ $counts['assignments'] }}</div></div>
    <div class="stat-card"><div class="k">Scripts</div><div class="v">{{ $counts['submissions'] }}</div></div>
    <div class="stat-card">
      <div class="k">Awaiting approval</div>
      <div class="v {{ $counts['pending'] ? 'danger-text' : '' }}">{{ $counts['pending'] }}</div>
    </div>
  </section>

  <section class="card">
    <div class="card-header">
      <span class="card-title">Newest accounts</span>
      <a class="t-xs fw-semibold" href="{{ route('admin.people') }}">All people</a>
    </div>
    @foreach ($recent as $person)
      <div class="list-row">
        <span class="avatar avatar-sm">{{ $person->initials() }}</span>
        <div class="grow" style="min-width:0">
          <div class="t-sm fw-semibold clamp-1">{{ $person->name }}</div>
          <div class="hint">{{ $person->email }}</div>
        </div>
        <span class="chip chip-outline">{{ ucfirst($person->role) }}</span>
      </div>
    @endforeach
  </section>
</div>
