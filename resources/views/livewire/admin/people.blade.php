<div>
  <div class="page-head">
    <div>
      <h1>People</h1>
      <p class="sub">Every account on the platform</p>
    </div>
    <div class="row gap-2">
      <input class="input" style="min-height:44px;width:220px" placeholder="Search name or email"
             wire:model.live.debounce.300ms="search">
      <select class="select" style="min-height:44px;width:auto" wire:model.live="role">
        <option value="">All roles</option>
        <option value="student">Students</option>
        <option value="teacher">Teachers</option>
        <option value="parent">Parents</option>
        <option value="admin">Admins</option>
      </select>
    </div>
  </div>

  <section class="card">
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Class</th><th>Joined</th></tr></thead>
        <tbody>
          @forelse ($people as $person)
            <tr>
              <td>
                <span class="row gap-3">
                  <span class="avatar avatar-sm">{{ $person->initials() }}</span>
                  <span class="fw-semibold">{{ $person->name }}</span>
                </span>
              </td>
              <td class="subtle">{{ $person->email }}</td>
              <td><span class="chip chip-outline">{{ ucfirst($person->role) }}</span></td>
              <td>{{ $person->level ?? '—' }}</td>
              <td class="subtle">{{ $person->created_at->format('j M Y') }}</td>
            </tr>
          @empty
            <tr><td colspan="5"><div class="empty"><p class="t-sm">Nobody matches that search.</p></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div style="margin-top:var(--s-4)">{{ $people->links() }}</div>
  </section>
</div>
