<div>
  <div class="page-head">
    <div>
      <h1>Curriculum</h1>
      <p class="sub">Every lesson the students receive, in both languages</p>
    </div>
  </div>

  <div class="row gap-2 wrap" style="margin-bottom:var(--s-6)">
    @foreach ($subjects as $s)
      <button class="pick {{ $subject && $subject->id === $s->id ? 'is-active' : '' }}"
              wire:click="$set('subjectSlug', '{{ $s->slug }}')">
        {{ $s->emoji }} {{ $s->short_name }} · {{ $s->topics_count }}
      </button>
    @endforeach
  </div>

  <div class="split" style="--split:300px minmax(0,1fr)">
    <aside class="card side-card">
      <div class="card-header"><span class="card-title">Topics</span></div>
      <div class="stack gap-1" style="max-height:70vh;overflow-y:auto">
        @foreach ($topics as $t)
          <button class="queue-row {{ $topic && $topic->id === $t->id ? 'is-current' : '' }}"
                  wire:click="$set('topicId', {{ $t->id }})">
            <span class="week-pill">WK<br>{{ $t->week }}</span>
            <span class="grow" style="min-width:0">
              <span class="t-sm fw-semibold clamp-1" style="display:block">{{ $t->title }}</span>
              <span class="hint">{{ $t->minutes }} min{{ $t->has_rich_lesson ? ' · full lesson' : '' }}</span>
            </span>
          </button>
        @endforeach
      </div>
    </aside>

    <section class="card card-pad-lg">
      @if (! $lesson)
        <div class="empty"><p class="t-sm">Pick a topic to read its lesson.</p></div>
      @else
        <div class="row between wrap gap-3" style="margin-bottom:var(--s-5)">
          <div>
            <h2 style="font-size:var(--text-xl)">{{ $lesson->title }}</h2>
            <p class="hint">{{ $subject->name }} · Week {{ $topic->week }} · {{ $topic->week_title }}</p>
          </div>
          <div class="segmented">
            <button class="{{ $locale === 'en' ? 'is-active' : '' }}" wire:click="$set('locale','en')">English</button>
            <button class="{{ $locale === 'pcm' ? 'is-active' : '' }}" wire:click="$set('locale','pcm')">Pidgin</button>
          </div>
        </div>

        <div class="card card-flat" style="background:var(--bg-sunken);margin-bottom:var(--s-5)">
          <div class="t-xs fw-bold" style="margin-bottom:var(--s-2)">Objectives</div>
          <ul class="stack gap-2">
            @foreach ($lesson->objectives as $objective)
              <li class="t-sm">• {{ $objective }}</li>
            @endforeach
          </ul>
        </div>

        <div class="prose">
          @foreach ($lesson->body as $block)
            @if (isset($block['h']))       <h3>{{ $block['h'] }}</h3>
            @elseif (isset($block['p']))   <p>{{ $block['p'] }}</p>
            @elseif (isset($block['f']))   <div class="formula">{{ $block['f'] }}</div>
            @elseif (isset($block['naija'])) <div class="callout naija"><b>Naija example:</b> {{ $block['naija'] }}</div>
            @elseif (isset($block['list']))
              <ul>@foreach ($block['list'] as $item)<li>{{ $item }}</li>@endforeach</ul>
            @endif
          @endforeach
        </div>

        @if ($topic->classwork->isNotEmpty())
          <div class="card card-flat" style="background:var(--bg-sunken);margin-top:var(--s-6)">
            <div class="t-xs fw-bold" style="margin-bottom:var(--s-3)">
              Classwork ({{ $topic->classwork->count() }} questions)
            </div>
            <div class="stack gap-3">
              @foreach ($topic->classwork as $q)
                <div class="t-sm">
                  <div class="fw-medium">{{ $q->prompt }}</div>
                  <div class="hint">Answer: {{ $q->options[$q->answer_index] ?? '—' }}</div>
                </div>
              @endforeach
            </div>
          </div>
        @endif
      @endif
    </section>
  </div>
</div>
