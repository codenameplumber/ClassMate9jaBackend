<div>
  <div class="page-head">
    <div>
      <h1>Courses</h1>
      <p class="sub">Subjects, the levels they are taught at, and what has been written.</p>
    </div>
    <button class="btn btn-primary" wire:click="create">Add a course</button>
  </div>

  @if (session('status'))
    <div class="alert alert-success" style="margin-bottom:var(--s-5)">{{ session('status') }}</div>
  @endif
  @if (session('warning'))
    <div class="alert alert-warn" style="margin-bottom:var(--s-5)">{{ session('warning') }}</div>
  @endif

  {{-- ============================================ create / edit ===== --}}
  @if ($editing)
    <div class="card" style="margin-bottom:var(--s-6)">
      <div class="card-body">
        <h2 class="t-lg fw-bold" style="margin-bottom:var(--s-4)">
          {{ $subjectId ? 'Edit course' : 'New course' }}
        </h2>

        <form wire:submit="save" class="stack gap-4">
          <div class="grid grid-2" style="gap:var(--s-4)">
            <div class="field">
              <label class="label" for="c-name">Name</label>
              <input class="input" id="c-name" wire:model.live.debounce.400ms="name"
                     placeholder="Basic Science">
              @error('name') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <div class="field">
              <label class="label" for="c-short">Short name</label>
              <input class="input" id="c-short" wire:model="shortName" placeholder="Basic Sci.">
              @error('shortName') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <div class="field">
              <label class="label" for="c-slug">Slug</label>
              <input class="input" id="c-slug" wire:model="slug" placeholder="basic-science"
                     @if($subjectId) readonly @endif>
              <p class="hint">
                @if ($subjectId)
                  Fixed once a course exists — devices have already downloaded it under this name.
                @else
                  Used in URLs and by the offline app.
                @endif
              </p>
              @error('slug') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <div class="field">
              <label class="label" for="c-emoji">Icon</label>
              <input class="input" id="c-emoji" wire:model="emoji" maxlength="4" style="max-width:100px">
              @error('emoji') <p class="error-text">{{ $message }}</p> @enderror
            </div>
          </div>

          <div class="field">
            <label class="label">Colour</label>
            <div class="row" style="gap:var(--s-2)">
              @foreach ($tones as $t)
                <button type="button" wire:click="$set('tone', '{{ $t }}')"
                        class="btn btn-sm {{ $tone === $t ? 'btn-primary' : 'btn-secondary' }}">{{ $t }}</button>
              @endforeach
            </div>
          </div>

          <div class="field">
            <label class="label" for="c-blurb">One line about it</label>
            <textarea class="input" id="c-blurb" rows="2" wire:model="blurb"
                      placeholder="Cells, ecology, genetics and human physiology."></textarea>
            @error('blurb') <p class="error-text">{{ $message }}</p> @enderror
          </div>

          <div class="field">
            <label class="label">Taught at</label>
            <p class="hint" style="margin-bottom:var(--s-2)">
              A subject with no levels cannot be taken by anyone. Junior-only and
              senior-only courses are normal.
            </p>
            <div class="row" style="gap:var(--s-2);flex-wrap:wrap">
              @foreach ($allLevels as $level)
                <button type="button" wire:click="toggleLevel('{{ $level }}')"
                        class="btn btn-sm {{ in_array($level, $levels, true) ? 'btn-primary' : 'btn-secondary' }}">
                  {{ $level }}
                </button>
              @endforeach
            </div>
            @error('levels') <p class="error-text" style="margin-top:var(--s-2)">{{ $message }}</p> @enderror
          </div>

          <label class="row" style="gap:var(--s-2);align-items:center">
            <input type="checkbox" wire:model="isCore">
            <span class="t-sm">Offer this as a core subject during sign-up</span>
          </label>

          <div class="row" style="gap:var(--s-2)">
            <button class="btn btn-primary" type="submit">Save course</button>
            <button class="btn btn-ghost" type="button" wire:click="cancel">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  @endif

  {{-- ============================================ generation queue == --}}
  @if ($runs->isNotEmpty())
    <div class="card" style="margin-bottom:var(--s-6)">
      <div class="card-body">
        <h2 class="t-lg fw-bold" style="margin-bottom:var(--s-3)">Recently written</h2>
        <div class="stack gap-2">
          @foreach ($runs as $run)
            <div class="row between" style="gap:var(--s-3);flex-wrap:wrap">
              <span class="t-sm">
                <b>{{ $run->subject?->short_name ?? 'Removed' }} {{ $run->level }}</b>
                <span class="muted">&middot; {{ $run->requester?->firstName() }}</span>
                <span class="muted">&middot; {{ $run->created_at->diffForHumans() }}</span>
              </span>
              <span class="row" style="gap:var(--s-2)">
                @if ($run->isRunning())
                  <span class="chip chip-amber">{{ ucfirst($run->status) }}</span>
                @elseif ($run->hasFailed())
                  <span class="chip chip-danger" title="{{ $run->error }}">Failed</span>
                @else
                  <span class="chip chip-success">
                    {{ $run->topics_written }} topics ·
                    {{ $run->lessons_written }} lessons ·
                    {{ $run->questions_written }} questions
                  </span>
                  @if ($run->duration()) <span class="hint tnum">{{ $run->duration() }}</span> @endif
                @endif
              </span>
            </div>
            @if ($run->hasFailed() && $run->error)
              <p class="hint" style="margin-top:-4px">{{ Str::limit($run->error, 180) }}</p>
            @endif
          @endforeach
        </div>
      </div>
    </div>
  @endif

  {{-- ============================================ the list ========== --}}
  <input class="input" type="search" wire:model.live.debounce.300ms="search"
         placeholder="Search courses" style="margin-bottom:var(--s-4)">

  <div class="stack gap-3">
    @forelse ($subjects as $subject)
      <div class="card">
        <div class="card-body stack gap-3">

          <div class="row between" style="gap:var(--s-3);flex-wrap:wrap">
            <div class="row grow" style="gap:var(--s-3);align-items:center">
              <span class="itile itile-sm" style="font-size:18px">{{ $subject->emoji }}</span>
              <span>
                <b>{{ $subject->name }}</b>
                @if ($subject->is_core) <span class="chip chip-brand">Core</span> @endif
                <div class="hint">
                  {{ $subject->slug }} &middot;
                  {{ $subject->topics_count }} topics &middot;
                  {{ $subject->questions_count }} questions
                  @if ($subject->ai_questions_count)
                    <span title="Written by a model, and not yet reviewed by anyone">
                      ({{ number_format($subject->ai_questions_count) }} AI)
                    </span>
                  @endif
                  &middot;
                  {{ $subject->students_count }} students
                </div>
              </span>
            </div>

            <div class="row" style="gap:var(--s-2)">
              <button class="btn btn-secondary btn-sm" wire:click="edit({{ $subject->id }})">Edit</button>
              <button class="btn btn-ghost btn-sm" wire:click="confirmDelete({{ $subject->id }})">Remove</button>
            </div>
          </div>

          {{-- Levels, each with its state and a way to have it written --}}
          <div class="row" style="gap:var(--s-2);flex-wrap:wrap">
            @forelse ($subject->levels as $offering)
              <span class="row" style="gap:var(--s-1);align-items:center">
                <span class="chip {{ $offering->isPublished() ? 'chip-success' : ($offering->isGenerating() ? 'chip-amber' : 'chip-outline') }}">
                  {{ $offering->level }}
                  @if ($offering->isPublished())
                    · {{ $offering->topic_count }}
                  @elseif ($offering->isGenerating())
                    · writing
                  @else
                    · empty
                  @endif
                </span>
                @if (! $offering->isGenerating())
                  <button class="btn btn-ghost btn-sm"
                          wire:click="startGeneration({{ $subject->id }}, '{{ $offering->level }}')"
                          title="Have the course written">✨</button>
                  @if ($offering->topic_count > 0)
                    <button class="btn btn-ghost btn-sm"
                            wire:click="stockQuestions({{ $subject->id }}, '{{ $offering->level }}')"
                            wire:loading.attr="disabled"
                            title="Stock the practice bank for {{ $offering->level }}">📝</button>
                  @endif
                @endif
              </span>
            @empty
              <span class="muted t-sm">No levels set — nobody can take this course.</span>
            @endforelse
          </div>

          {{-- Ask for a course to be written --}}
          @if ($generatingFor === $subject->id)
            <div class="stack gap-2" style="border-top:1px solid var(--line);padding-top:var(--s-3)">
              <label class="label" for="brief-{{ $subject->id }}">
                Explain {{ $subject->short_name }} {{ $genLevel }}
              </label>
              <p class="hint">
                What should it cover, in what order, and what do students at this
                level get wrong? The more specific this is, the less generic the
                course comes back.
              </p>
              <textarea class="input" id="brief-{{ $subject->id }}" rows="5" wire:model="brief"
                        placeholder="Twelve weeks following the NERDC scheme. Start with…"></textarea>
              @error('brief') <p class="error-text">{{ $message }}</p> @enderror
              <div class="row" style="gap:var(--s-2)">
                <button class="btn btn-primary btn-sm" wire:click="requestGeneration"
                        wire:loading.attr="disabled" wire:target="requestGeneration">
                  Write this course
                </button>
                <button class="btn btn-ghost btn-sm" wire:click="cancelGeneration">Cancel</button>
              </div>
            </div>
          @endif

          {{-- Removal --}}
          @if ($deletingId === $subject->id)
            <div class="stack gap-2" style="border-top:1px solid var(--line);padding-top:var(--s-3)">
              <p class="t-sm">
                Remove <b>{{ $subject->name }}</b>? Its
                {{ $subject->topics_count }} topics and
                {{ $subject->questions_count }} questions go with it, and this
                cannot be undone.
              </p>
              <div class="row" style="gap:var(--s-2)">
                <button class="btn btn-danger btn-sm" wire:click="delete">Yes, remove it</button>
                <button class="btn btn-ghost btn-sm" wire:click="cancelDelete">Keep it</button>
              </div>
            </div>
          @endif

        </div>
      </div>
    @empty
      <div class="card"><div class="card-body"><p class="muted">No course matches that.</p></div></div>
    @endforelse
  </div>

  <div style="margin-top:var(--s-5)">{{ $subjects->links() }}</div>
</div>
