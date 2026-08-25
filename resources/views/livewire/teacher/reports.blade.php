<div>
  @if (! $classroom)
    <div class="empty card"><div class="itile">📊</div><p class="t-sm">No class to report on yet.</p></div>
  @else
  <div class="page-head no-print">
    <div>
      <h1>Class report</h1>
      <p class="sub">{{ $classroom->name }} · {{ $classroom->subject?->name }} · this term</p>
    </div>
    <div class="row gap-3">
      <button class="btn btn-secondary" onclick="window.print()">🖨 Print / PDF</button>
    </div>
  </div>

  <div class="row gap-2 wrap no-print" style="margin-bottom:var(--s-6)">
    @foreach ($classrooms as $c)
      <button class="pick {{ $classroom->id === $c->id ? 'is-active' : '' }}"
              wire:click="$set('classroomId', {{ $c->id }})">{{ $c->name }}</button>
    @endforeach
  </div>

  <div class="sheet-doc">
    <div class="doc-head">
      <div>
        <div class="brand-mark" style="margin-bottom:var(--s-3)">
          <span class="glyph">C</span>ClassMate&nbsp;<span class="naija">9ja</span>
        </div>
        <h2>{{ $classroom->name }} — {{ $classroom->subject?->name }}</h2>
        <p class="t-sm muted">
          {{ $classroom->school?->name }} · prepared by {{ auth()->user()->name }}
        </p>
      </div>
      <div class="center-text">
        <div class="t-2xs subtle upper">Class average</div>
        <div style="font-size:var(--text-3xl);font-weight:800;line-height:1">{{ $stats['average'] }}%</div>
      </div>
    </div>

    <section style="margin-bottom:var(--s-8)">
      <h4 style="margin-bottom:var(--s-4)">Summary</h4>
      <div class="grid grid-4">
        <div class="stat-card card-flat"><div class="k">Students</div><div class="v">{{ $stats['size'] }}</div></div>
        <div class="stat-card card-flat">
          <div class="k">Credit or better</div>
          <div class="v">{{ $bands[0]['count'] + $bands[1]['count'] }}</div>
        </div>
        <div class="stat-card card-flat">
          <div class="k">Below pass</div><div class="v danger-text">{{ $bands[3]['count'] }}</div>
        </div>
        <div class="stat-card card-flat">
          <div class="k">Scripts marked</div><div class="v">{{ $stats['approved'] }}</div>
        </div>
      </div>
    </section>

    <section style="margin-bottom:var(--s-8)">
      <h4 style="margin-bottom:var(--s-4)">Grade spread</h4>
      <div class="stack gap-2">
        @foreach ($bands as $band)
          <div class="doc-row">
            <span>{{ $band['key'] }}</span>
            <span class="row gap-3 items-center">
              <span class="bar bar-sm" style="width:140px">
                <i style="width:{{ $band['percent'] }}%"></i>
              </span>
              <span class="fw-semibold tnum" style="min-width:110px;text-align:right">
                {{ $band['count'] }} students · {{ $band['percent'] }}%
              </span>
            </span>
          </div>
        @endforeach
      </div>
    </section>

    @if ($health->isNotEmpty())
      <section style="margin-bottom:var(--s-8)">
        <h4 style="margin-bottom:var(--s-2)">Question analysis</h4>
        <p class="t-sm muted" style="margin-bottom:var(--s-4)">Where the marks were lost, weakest first.</p>
        <div class="stack gap-4">
          @foreach ($health as $row)
            <div>
              <div class="row between t-sm" style="margin-bottom:6px">
                <span class="fw-medium">{{ $row['topic'] }}</span>
                <span class="subtle tnum">{{ $row['average'] }}% · {{ $row['failing'] }} below half marks</span>
              </div>
              <div class="bar">
                <i class="{{ $row['average'] >= 70 ? '' : ($row['average'] >= 50 ? 'amber' : 'danger') }}"
                   style="width:{{ $row['average'] }}%"></i>
              </div>
            </div>
          @endforeach
        </div>

        <div class="alert alert-warn" style="margin-top:var(--s-5)">
          <span>
            <b>Recommended action:</b> re-teach <b>{{ $health->first()['topic'] }}</b> before
            moving on. {{ $health->first()['failing'] }} students scored below half marks
            on it, which points at the teaching sequence rather than individual students.
          </span>
        </div>
      </section>
    @endif

    <section>
      <div class="grid grid-2">
        <div>
          <h4 style="margin-bottom:var(--s-4)">Top of the class</h4>
          @foreach ($top as $i => $row)
            <div class="list-row">
              <span class="chip chip-brand">{{ $i + 1 }}</span>
              <span class="avatar avatar-sm">{{ $row['student']->initials() }}</span>
              <span class="grow t-sm fw-semibold clamp-1">{{ $row['student']->name }}</span>
              <span class="tnum fw-bold">{{ $row['average'] }}%</span>
            </div>
          @endforeach
        </div>
        <div>
          <h4 style="margin-bottom:var(--s-4)">Needs intervention</h4>
          @foreach ($bottom as $row)
            <div class="list-row">
              <span class="avatar avatar-sm">{{ $row['student']->initials() }}</span>
              <span class="grow t-sm fw-semibold clamp-1">{{ $row['student']->name }}</span>
              <span class="chip chip-danger">{{ $row['average'] }}%</span>
            </div>
          @endforeach
        </div>
      </div>
    </section>

    <p class="t-2xs subtle" style="margin-top:var(--s-8);text-align:center">
      Generated by ClassMate 9ja on {{ now()->format('j F Y') }}
    </p>
  </div>
  @endif
</div>
