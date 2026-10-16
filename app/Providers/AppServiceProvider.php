<?php

namespace App\Providers;

use App\Http\Middleware\EnsureBusinessMember;
use App\Models\User;
use App\Support\Tenancy\BusinessResolver;
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

        $this->configureRateLimiting();
    }

    /**
     * Limitatorii numiți (SDD §6, ADR 013): fiecare are cheia lui (angajat, client, afacere sau IP), spre deosebire
     * de `throttle:N,1`, care împarte aceeași cheie pe IP între toate rutele. Răspunsul 429 e în bootstrap/app.php.
     */
    private function configureRateLimiting(): void
    {
        $limit = fn (string $name): int => (int) config("loyalty.rate_limits.{$name}");

        // Scanare 30/min per angajat (face nepractică ghicirea codurilor scurte, ADR 011).
        RateLimiter::for('staff-scan', fn (Request $request) => Limit::perMinute((int) config('loyalty.scan.per_minute'))->by('staff-scan:'.$request->user()?->getAuthIdentifier()));

        // Ștampilă, recompensă, aprobare/respingere, anulare: plasă de siguranță peste idempotency și tichete.
        RateLimiter::for('staff-actions', fn (Request $request) => Limit::perMinute($limit('staff_actions_per_minute'))->by('staff-actions:'.$request->user()?->getAuthIdentifier()));

        // Înscriere și cerere de ștampilă: per client, nu per IP (rețele mobile cu NAT).
        RateLimiter::for('customer-actions', fn (Request $request) => Limit::perMinute($limit('customer_actions_per_minute'))->by('customer-actions:'.$request->user('customer')?->getAuthIdentifier()));

        // QR-ul rotativ (ADR 010): PWA-ul cere 2/min; limita e per client, nu per IP.
        RateLimiter::for('customer-qr', fn (Request $request) => Limit::perMinute($limit('customer_qr_per_minute'))->by('customer-qr:'.$request->user('customer')?->getAuthIdentifier()));

        // Polling-ul cardului (ADR 012): 12/min cât e o cerere deschisă, 4/min altfel; limita lasă loc pentru mai multe taburi.
        RateLimiter::for('customer-status', fn (Request $request) => Limit::perMinute($limit('customer_status_per_minute'))->by('customer-status:'.$request->user('customer')?->getAuthIdentifier()));

        // Formularele publice, înainte de login: per IP. Limita per email a magic link-ului stă în MagicLinkService.
        RateLimiter::for('magic-link-send', fn (Request $request) => Limit::perMinute($limit('magic_link_per_minute'))->by('magic-link-send:'.$request->ip()));
        RateLimiter::for('magic-link-consume', fn (Request $request) => Limit::perMinute($limit('magic_link_per_minute'))->by('magic-link-consume:'.$request->ip()));
        RateLimiter::for('invitation-register', fn (Request $request) => Limit::perMinute($limit('invitation_register_per_minute'))->by('invitation-register:'.$request->ip()));

        // Emailurile de invitație: per afacere, oricare owner le-ar trimite. Throttle-ul rulează înaintea middleware-ului
        // `business` (prioritatea Laravel), deci afacerea se rezolvă aici la fel ca acolo.
        RateLimiter::for('invitations', function (Request $request) use ($limit) {
            $user = $request->user();
            $business = $user instanceof User
                ? BusinessResolver::resolve($user, $request->session()->get(EnsureBusinessMember::SESSION_KEY))
                : null;

            return Limit::perHour($limit('invitations_per_hour'))
                ->by('invitations:'.($business?->getKey() ?? 'user:'.$user?->getAuthIdentifier()));
        });
    }
}
