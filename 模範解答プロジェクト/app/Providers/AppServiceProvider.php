<?php

namespace App\Providers;

use App\View\Composers\SidebarBadgeComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('layouts._partials.sidebar-*', SidebarBadgeComposer::class);
    }
}
