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
        <code>OPENAI_API_KEY</code> to <code>.env</code> to switch on written marking.
      @endif
    </span>
  </div>

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
