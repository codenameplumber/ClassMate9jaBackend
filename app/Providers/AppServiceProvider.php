<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Laravel's default pager is Tailwind markup, and this application does
        // not build Tailwind -- it shares one hand-written stylesheet with the
        // offline app. Without this the admin lists rendered a pager whose
        // classes resolved to nothing at all.
        Paginator::defaultView('vendor.pagination.default');
        Paginator::defaultSimpleView('vendor.pagination.default');
    }
}
