<div>
  @if (! $summary)
    <div class="empty card">
      <div class="itile">👨🏾‍👩🏾‍👦🏾</div>
      <h3>No child linked yet</h3>
      <p class="t-sm">Ask your child to open Settings in their app and read out their link code.</p>
    </div>
  @else
  @php
    $child = $summary['student'];
    [$band, $bandNote, $bandTone] = \App\Support\ChildSummary::band($summary['average']);
    $days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
    $peak = max(max($summary['week']), 1);
  @endphp

  <div class="page-head">
    <div>
      <h1>How {{ $child->firstName() }} is doing</h1>
      <p class="sub">Week of {{ now()->startOfWeek()->format('j F') }} · {{ $child->level }}</p>
    </div>
    <a class="btn btn-primary" href="{{ route('parent.report') }}">📄 Full weekly report</a>
  </div>

  <div class="row gap-3 wrap" style="margin-bottom:var(--s-6)">
    @foreach ($children as $c)
      <button class="child-card {{ $child->id === $c->id ? 'is-active' : '' }}"
              wire:click="$set('childId', {{ $c->id }})">
        <span class="avatar">{{ $c->initials() }}</span>
        <div style="text-align:left">
          <div class="fw-bold t-sm">{{ $c->name }}</div>
          <div class="hint">{{ $c->level }} · {{ $c->school?->name }}</div>
        </div>
      </button>
    @endforeach
  </div>

  {{-- A verdict, not a dashboard. --}}
  <section class="continue" style="margin-bottom:var(--s-6);{{ $summary['on_track'] ? '' : 'background:linear-gradient(135deg,var(--clay-600),var(--clay-400))' }}">
    <div>
      <div class="eye">This week</div>
      <h2>
        {{ $summary['on_track']
            ? $child->firstName() . ' is keeping up'
            : $child->firstName() . ' needs a push this week' }}
      </h2>
      <div class="meta">
        @if ($summary['on_track'])
          Studied on {{ $summary['days_studied'] }} of 7 days,
          {{ intdiv($summary['minutes'], 60) }}h {{ $summary['minutes'] % 60 }}m in total, and kept a
          {{ $summary['streak'] }}-day streak.
          @if ($summary['weakest'])
            The weakest subject is {{ ucfirst($summary['weakest']['slug']) }} — that is where your encouragement counts.
          @endif
        @else
          Only {{ $summary['days_studied'] }} study {{ Str::plural('day', $summary['days_studied']) }} this week and
          {{ intdiv($summary['minutes'], 60) }}h {{ $summary['minutes'] % 60 }}m in total. Nothing has gone wrong
          yet, but at this pace the weaker subjects will not be ready for WAEC.
        @endif
      </div>
      <div class="row gap-3 wrap" style="margin-top:var(--s-5)">
        <a class="btn btn-amber" href="{{ route('parent.report') }}">Read the full report</a>
        <button class="btn btn-secondary" wire:click="nudge"
                style="background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.3);color:#fff">
          Send an encouragement
        </button>
      </div>
    </div>

    <div class="center-text">
      <div style="font-size:56px;font-weight:800;line-height:1">{{ $summary['days_studied'] }}/7</div>
      <div class="t-sm" style="opacity:.85">days studied</div>
    </div>
  </section>

  <section class="stat-grid" style="margin-bottom:var(--s-6)">
    <div class="stat-card">
      <div class="k">Study time</div>
      <div class="v">{{ intdiv($summary['minutes'], 60) }}h {{ $summary['minutes'] % 60 }}m</div>
      <div class="d">This week, measured on the device</div>
    </div>
    <div class="stat-card">
      <div class="k">Streak</div>
      <div class="v">🔥 {{ $summary['streak'] }}</div>
      <div class="d">Best ever {{ $summary['best_streak'] }} days</div>
    </div>
    <div class="stat-card">
      <div class="k">Test average</div>
      <div class="v">{{ $summary['average'] }}%</div>
      <div class="d">{{ $band }} · {{ $bandNote }}</div>
    </div>
    <div class="stat-card">
      <div class="k">Weakest subject</div>
      <div class="v" style="font-size:var(--text-xl)">
        {{ $summary['weakest'] ? ucfirst($summary['weakest']['slug']) : '—' }}
      </div>
      <div class="d danger-text">
        {{ $summary['weakest'] ? $summary['weakest']['mastery'] . '% mastery' : '' }}
      </div>
    </div>
  </section>

  <div class="grid" style="grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);gap:var(--s-6)">
    <div class="stack gap-6">
      <section class="card card-pad-lg">
        <div class="card-header">
          <div>
            <span class="card-title">Study time, day by day</span>
            <div class="hint">A short session every day beats one long night</div>
          </div>
        </div>
        <div style="display:flex;align-items:flex-end;gap:2%;height:170px">
          @foreach ($summary['week'] as $i => $mins)
            <div style="flex:1;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;height:100%;gap:6px">
              <span class="t-2xs tnum subtle">{{ $mins ?: '' }}</span>
              <div style="width:100%;height:{{ max(2, round($mins / $peak * 100)) }}%;border-radius:6px 6px 2px 2px;background:{{ $mins >= 30 ? 'var(--primary)' : 'var(--green-200)' }}"></div>
              <span class="t-2xs subtle">{{ $days[$i] }}</span>
            </div>
          @endforeach
        </div>
      </section>

      <section class="card card-pad-lg">
        <div class="card-header">
          <span class="card-title">Subject by subject</span>
          <span class="chip chip-outline">{{ $summary['subjects']->count() }} subjects</span>
        </div>
        <div class="stack gap-4">
          @foreach ($summary['subjects'] as $s)
            <div>
              <div class="row between t-sm" style="margin-bottom:6px">
                <span class="fw-medium">{{ ucfirst($s['slug']) }}</span>
                <span class="subtle tnum">{{ $s['mastery'] }}%</span>
              </div>
              <div class="bar">
                <i class="{{ $s['mastery'] >= 70 ? '' : ($s['mastery'] >= 50 ? 'amber' : 'danger') }}"
                   style="width:{{ $s['mastery'] }}%"></i>
              </div>
            </div>
          @endforeach
        </div>
        <p class="hint" style="margin-top:var(--s-5)">
          Mastery comes from marked work, not from time spent. A subject below 50%
          is the one to ask about at dinner.
        </p>
      </section>
    </div>

    <div class="stack gap-6">
      <section class="card">
        <div class="card-header"><span class="card-title">How you can help this week</span></div>
        <div class="stack gap-4">
          @if ($summary['weakest'])
            <div class="row gap-3 items-start">
              <span class="itile itile-sm">🎯</span>
              <div>
                <div class="fw-semibold t-sm">Ask about {{ ucfirst($summary['weakest']['slug']) }}</div>
                <p class="t-xs muted" style="margin-top:2px">
                  Do not ask "did you read?". Ask them to explain one topic to you.
                  If they cannot explain it simply, they have not understood it yet.
                </p>
              </div>
            </div>
          @endif
          <div class="row gap-3 items-start">
            <span class="itile itile-sm">⏰</span>
            <div>
              <div class="fw-semibold t-sm">Fix a time, not a duration</div>
              <p class="t-xs muted" style="margin-top:2px">
                Thirty minutes at the same time each evening builds the habit faster
                than three hours on Saturday.
              </p>
            </div>
          </div>
          <div class="row gap-3 items-start">
            <span class="itile itile-sm">🗣️</span>
            <div>
              <div class="fw-semibold t-sm">Praise the effort, not the brain</div>
              <p class="t-xs muted" style="margin-top:2px">
                "You worked hard on that" builds a student who tries again after a
                bad score.
              </p>
            </div>
          </div>
        </div>
      </section>

      <section class="card">
        <div class="card-header">
          <span class="card-title">Weekly email</span>
          <span class="chip chip-brand">On</span>
        </div>
        <p class="t-sm muted" style="margin-bottom:var(--s-4)">
          Sent every Sunday to {{ auth()->user()->email }}.
        </p>
        <div class="sms-preview">{{ \App\Support\ChildSummary::smsText($summary) }}</div>
      </section>
    </div>
  </div>
  @endif
</div>
