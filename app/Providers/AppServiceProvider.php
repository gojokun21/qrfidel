<?php

namespace App\Providers;

use App\Support\Tenancy\CurrentBusiness;
use App\View\Composers\SidebarComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

        // SDD §6: scanare 30/min per angajat (face nepractică ghicirea codurilor scurte, ADR 011).
        RateLimiter::for('staff-scan', fn (Request $request) => Limit::perMinute((int) config('loyalty.scan.per_minute'))->by('staff-scan:'.$request->user()?->getAuthIdentifier()));

        // QR-ul rotativ (ADR 010): PWA-ul cere 2/min; limita e per client, nu per IP (rețele mobile cu NAT).
        RateLimiter::for('customer-qr', fn (Request $request) => Limit::perMinute(10)->by('customer-qr:'.$request->user('customer')?->getAuthIdentifier()));
    }
}
