<?php

namespace App\Providers;

use App\Support\Tenancy\CurrentBusiness;
use App\View\Composers\SidebarComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped: un context nou pentru fiecare request/job (inclusiv sub Octane sau în worker-ul de cozi).
        $this->app->scoped(CurrentBusiness::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.sidebar', SidebarComposer::class);
    }
}
