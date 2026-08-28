{{--
    Pagination, in this project's design system.

    Laravel ships a Tailwind pager by default, and this application does not
    build Tailwind — it links one hand-written stylesheet shared with the
    offline app. So the default pager rendered markup whose classes resolved to
    nothing, which is why the admin lists looked broken.

    Built from the buttons that already exist rather than new CSS, so it
    inherits the theme, the dark mode and the touch targets for free.
--}}
@if ($paginator->hasPages())
  <nav class="row between" style="gap:var(--s-3);flex-wrap:wrap;align-items:center"
       role="navigation" aria-label="Pagination">

    <span class="t-sm muted tnum">
      @if ($paginator->firstItem())
        {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}
      @else
        {{ $paginator->total() }} results
      @endif
    </span>

    <div class="row" style="gap:var(--s-2);flex-wrap:wrap">

      {{-- Previous --}}
      @if ($paginator->onFirstPage())
        <span class="btn btn-secondary btn-sm" aria-disabled="true"
              style="opacity:.45;pointer-events:none">&larr; Back</span>
      @else
        <button type="button" class="btn btn-secondary btn-sm"
                wire:click="previousPage('{{ $paginator->getPageName() }}')"
                wire:loading.attr="disabled"
                rel="prev">&larr; Back</button>
      @endif

      {{-- Page numbers. Hidden on the narrowest screens, where Back/Next and
           the count are all that fit usefully. --}}
      <div class="row hide-mobile" style="gap:var(--s-2)">
        @foreach ($elements as $element)
          @if (is_string($element))
            <span class="btn btn-ghost btn-sm" style="pointer-events:none">{{ $element }}</span>
          @endif

          @if (is_array($element))
            @foreach ($element as $page => $url)
              @if ($page == $paginator->currentPage())
                <span class="btn btn-primary btn-sm tnum" aria-current="page">{{ $page }}</span>
              @else
                <button type="button" class="btn btn-secondary btn-sm tnum"
                        wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                        wire:loading.attr="disabled">{{ $page }}</button>
              @endif
            @endforeach
          @endif
        @endforeach
      </div>

      {{-- Next --}}
      @if ($paginator->hasMorePages())
        <button type="button" class="btn btn-secondary btn-sm"
                wire:click="nextPage('{{ $paginator->getPageName() }}')"
                wire:loading.attr="disabled"
                rel="next">Next &rarr;</button>
      @else
        <span class="btn btn-secondary btn-sm" aria-disabled="true"
              style="opacity:.45;pointer-events:none">Next &rarr;</span>
      @endif

    </div>
  </nav>
@endif
