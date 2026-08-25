<div>
  @if (! $summary)
    <div class="empty card"><div class="itile">📄</div><p class="t-sm">No child linked yet.</p></div>
  @else
  @php
    $child = $summary['student'];
    [$band, $bandNote] = \App\Support\ChildSummary::band($summary['average']);
    $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
  @endphp

  <div class="page-head no-print">
    <div>
      <h1>Weekly report</h1>
      <p class="sub">Also sent to your email, so it reaches you without opening the app.</p>
    </div>
    <div class="row gap-3">
      <button class="btn btn-secondary" onclick="window.print()">🖨 Print / PDF</button>
      <button class="btn btn-primary" wire:click="emailIt">
        <span wire:loading.remove wire:target="emailIt">✉ Email it to me</span>
        <span wire:loading wire:target="emailIt">Sending…</span>
      </button>
    </div>
  </div>

  <div class="sheet-doc">
    <div class="doc-head">
      <div>
        <div class="brand-mark" style="margin-bottom:var(--s-3)">
          <span class="glyph">C</span>ClassMate&nbsp;<span class="naija">9ja</span>
        </div>
        <h2>Weekly progress report</h2>
        <p class="t-sm muted">
          Week of {{ now()->startOfWeek()->format('j F Y') }} · prepared for {{ auth()->user()->name }}
        </p>
      </div>
      <div class="row gap-3 items-center">
        <span class="avatar avatar-lg">{{ $child->initials() }}</span>
        <div>
          <div class="fw-bold">{{ $child->name }}</div>
          <div class="t-sm muted">{{ $child->level }} · {{ $child->school?->name }}</div>
          <span class="chip {{ $summary['average'] >= 50 ? 'chip-brand' : 'chip-danger' }}" style="margin-top:6px">
            {{ $band }} · {{ $bandNote }}
          </span>
        </div>
      </div>
    </div>

    <section style="margin-bottom:var(--s-8)">
      <h4 style="margin-bottom:var(--s-4)">This week at a glance</h4>
      <div class="grid grid-4">
        <div class="stat-card card-flat">
          <div class="k">Study time</div>
          <div class="v">{{ intdiv($summary['minutes'], 60) }}h {{ $summary['minutes'] % 60 }}m</div>
        </div>
        <div class="stat-card card-flat">
          <div class="k">Days studied</div>
          <div class="v">{{ $summary['days_studied'] }}<span class="subtle t-lg">/7</span></div>
        </div>
        <div class="stat-card card-flat"><div class="k">Streak</div><div class="v">🔥 {{ $summary['streak'] }}</div></div>
        <div class="stat-card card-flat"><div class="k">Average</div><div class="v">{{ $summary['average'] }}%</div></div>
      </div>
    </section>

    <section style="margin-bottom:var(--s-8)">
      <h4 style="margin-bottom:var(--s-4)">Day by day</h4>
      <div class="stack gap-2">
        @foreach ($summary['week'] as $i => $mins)
          <div class="doc-row">
            <span>{{ $days[$i] }}</span>
            <span class="row gap-3 items-center">
              <span class="bar bar-sm" style="width:120px">
                <i class="{{ $mins >= 30 ? '' : ($mins > 0 ? 'amber' : 'danger') }}"
                   style="width:{{ min(100, round($mins / 60 * 100)) }}%"></i>
              </span>
              <span class="fw-semibold tnum" style="min-width:56px;text-align:right">
                {{ $mins ? $mins . ' min' : '—' }}
              </span>
            </span>
          </div>
        @endforeach
      </div>
    </section>

    @if ($summary['submissions']->isNotEmpty())
      <section style="margin-bottom:var(--s-8)">
        <h4 style="margin-bottom:var(--s-4)">Marked classwork</h4>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Assignment</th><th>Score</th><th>Grade</th><th>Approved</th></tr></thead>
            <tbody>
              @foreach ($summary['submissions'] as $s)
                @php [$b, $n] = \App\Support\ChildSummary::band($s->percent()); @endphp
                <tr>
                  <td class="fw-semibold">{{ $s->assignment->title }}</td>
                  <td class="tnum">{{ $s->score() }}/{{ $s->max_score }} ({{ $s->percent() }}%)</td>
                  <td><span class="chip {{ $s->percent() >= 50 ? 'chip-brand' : 'chip-danger' }}">{{ $b }}</span></td>
                  <td class="subtle">{{ $s->approved_at?->format('j M') }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </section>
    @endif

    <section style="margin-bottom:var(--s-8)">
      <h4 style="margin-bottom:var(--s-4)">What this means</h4>
      <div class="alert {{ $summary['average'] >= 50 ? 'alert-success' : 'alert-warn' }}">
        <span>
          {{ $child->firstName() }} studied on {{ $summary['days_studied'] }} of 7 days.
          @if ($summary['days_studied'] >= 5)
            That consistency is the single best predictor of a good WAEC result.
          @else
            Getting that to five would make the biggest difference of anything on this page.
          @endif
          Marks are averaging {{ $summary['average'] }}%.
          @if ($summary['weakest'])
            The clearest thing you can do is ask about
            <b>{{ ucfirst($summary['weakest']['slug']) }}</b>@if ($summary['weakest']['weak']),
            specifically {{ $summary['weakest']['weak'][0] }}@endif. Have
            {{ $child->firstName() }} explain that topic to you out loud.
          @endif
        </span>
      </div>
    </section>

    <section class="no-print">
      <h4 style="margin-bottom:var(--s-4)">The short version</h4>
      <div class="sms-preview">{{ \App\Support\ChildSummary::smsText($summary) }}</div>
    </section>

    <p class="t-2xs subtle" style="margin-top:var(--s-8);text-align:center">
      Generated by ClassMate 9ja on {{ now()->format('j F Y') }} ·
      Study time is measured on the device, not self-reported.
    </p>
  </div>
  @endif
</div>
