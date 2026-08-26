<div>
  <h1 class="auth-title">Your account is with an administrator</h1>

  @if (auth()->user()->isRejected())
    <p class="auth-sub">
      An administrator could not approve this account.
      @if (auth()->user()->rejected_reason)
        <br><b>Reason given:</b> {{ auth()->user()->rejected_reason }}
      @endif
      <br>If you think this is a mistake, speak to your school and ask them to
      review it again.
    </p>
  @else
    <p class="auth-sub">
      Teachers mark other people&rsquo;s children, so somebody at
      {{ auth()->user()->school?->name ?? 'your school' }} checks every new
      account before it goes live. You will be able to sign straight in once
      that is done &mdash; nothing else is needed from you.
    </p>
  @endif

  <div class="card" style="margin:var(--s-6) 0">
    <div class="card-body stack gap-3">
      <div class="row between">
        <span class="subtle">Name</span>
        <b>{{ auth()->user()->name }}</b>
      </div>
      <div class="row between">
        <span class="subtle">Email</span>
        <b>{{ auth()->user()->email }}</b>
      </div>
      <div class="row between">
        <span class="subtle">School</span>
        <b>{{ auth()->user()->school?->name ?? 'Not set' }}</b>
      </div>
      <div class="row between">
        <span class="subtle">Teaching</span>
        <b>
          @forelse (auth()->user()->teachingSummary() as $row)
            {{ $row['subject']->short_name }}
            ({{ \App\Support\Levels::describe($row['levels']) }}){{ ! $loop->last ? ', ' : '' }}
          @empty
            Nothing selected yet
          @endforelse
        </b>
      </div>
      <div class="row between">
        <span class="subtle">Requested</span>
        <b>{{ auth()->user()->created_at->diffForHumans() }}</b>
      </div>
    </div>
  </div>

  <form method="POST" action="{{ route('logout') }}">
    @csrf
    <button class="btn btn-secondary btn-block" type="submit">Sign out</button>
  </form>
</div>
