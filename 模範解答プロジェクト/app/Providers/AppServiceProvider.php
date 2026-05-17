<?php

declare(strict_types=1);

namespace App\Providers;

use App\View\Composers\SidebarBadgeComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StripeClient::class, function () {
            return new StripeClient((string) config('services.stripe.secret'));
        });
    }

    public function boot(): void
    {
        View::composer('layouts._partials.sidebar-*', SidebarBadgeComposer::class);
    }
}
