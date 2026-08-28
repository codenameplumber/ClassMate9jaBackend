<?php

namespace App\Livewire\Concerns;

/**
 * Paginate with this project's own markup.
 *
 * Livewire resolves its pager through `paginationView()`, which returns
 * `livewire::tailwind` by default and ignores `Paginator::defaultView()`
 * entirely. This application does not build Tailwind — it shares one
 * hand-written stylesheet with the offline app — so the default pager rendered
 * utility classes that resolved to nothing, and every admin list looked like
 * its pagination was broken.
 *
 * A trait rather than a config change because the view is ours, not one of
 * Livewire's themes, and because a component that paginates should say so.
 */
trait PaginatesInHouse
{
    public function paginationView(): string
    {
        return 'vendor.pagination.default';
    }

    public function paginationSimpleView(): string
    {
        return 'vendor.pagination.default';
    }
}
