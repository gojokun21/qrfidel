<?php

use App\Jobs\ExpireRewards;
use App\Jobs\ExpireStampRequests;
use App\Jobs\ExpireStamps;
use App\Models\Card;
use App\Services\Loyalty\CardBalanceService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Expirările (SDD §8, ADR 013). onOneServer cere un cache cu lock-uri comune (Redis sau database); suprapunerea o previne ShouldBeUnique.
Schedule::job(new ExpireStampRequests)->everyFiveMinutes()->onOneServer();
Schedule::job(new ExpireRewards)->hourly()->onOneServer();
Schedule::job(new ExpireStamps)->hourly()->onOneServer();

// Driverul `database` nu șterge singur ce a expirat (ADR 022): fără asta, codurile QR rotite la 30 s umplu tabelele.
Artisan::command('cache:prune-expired', function () {
    $now = now()->getTimestamp();
    $deleted = 0;

    foreach (['cache', 'cache_locks', 'loyalty_cache', 'loyalty_cache_locks'] as $table) {
        $deleted += DB::table($table)->where('expiration', '<', $now)->delete();
    }

    $this->info("Rânduri expirate șterse: {$deleted}");
})->purpose('Șterge rândurile expirate din tabelele de cache (store-urile database)');

Schedule::command('cache:prune-expired')->hourly()->onOneServer();

// Reconstruiește cache-ul de pe carduri din jurnal (SDD §4).
Artisan::command('cards:recalculate
    {--card= : public_id-ul unui singur card}
    {--business= : doar cardurile unei afaceri (id)}
    {--dry-run : doar raportează diferențele, fără să scrie}', function (CardBalanceService $balances) {
    $cardId = null;

    if (is_string($this->option('card'))) {
        $cardId = Card::query()->withoutGlobalScopes()->where('public_id', $this->option('card'))->value('id');

        if ($cardId === null) {
            $this->error('Cardul nu există.');

            return 1;
        }
    }

    $business = $this->option('business');
    if ($business !== null && ! ctype_digit((string) $business)) {
        $this->error('--business primește id-ul numeric al afacerii.');

        return 1;
    }

    $dryRun = (bool) $this->option('dry-run');

    $result = $balances->recalculateAll(
        businessId: $business === null ? null : (int) $business,
        cardId: $cardId,
        dryRun: $dryRun,
        onDrift: function (Card $card, array $before, array $after) {
            $changes = collect($after)
                ->filter(fn ($value, string $field) => $before[$field] !== $value)
                ->map(fn ($value, string $field) => sprintf('%s %s → %s', $field, json_encode($before[$field]), json_encode($value)))
                ->implode(', ');

            $this->line("Card {$card->public_id}: {$changes}");
        },
    );

    $this->info(sprintf(
        'Carduri verificate: %d, %s: %d',
        $result['checked'],
        $dryRun ? 'de corectat' : 'corectate',
        $result['drifted'],
    ));

    return 0;
})->purpose('Reconstruiește soldurile cardurilor din jurnalul de tranzacții');
