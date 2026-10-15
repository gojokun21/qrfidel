<?php

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Location;
use App\Models\Program;
use App\Models\Reward;
use App\Models\StampRequest;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Cu config cache, phpunit.xml e ignorat și RefreshDatabase ar goli baza de dezvoltare.
if (is_file(__DIR__.'/../bootstrap/cache/config.php')) {
    throw new RuntimeException('Config-ul e în cache. Rulează `php artisan config:clear` (sau `composer test`).');
}

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Setează (sau golește) afacerea curentă, ca middleware-ul EnsureBusinessMember.
 */
function inBusiness(?Business $business): void
{
    app(CurrentBusiness::class)->set($business);
}

/**
 * Creează o afacere cu câte un rând din fiecare tabel de business.
 *
 * @return array<string, Model>
 */
function businessGraph(): array
{
    $business = Business::factory()->create();
    $location = Location::factory()->for($business)->create();
    $program = Program::factory()->for($business)->active()->create();
    $card = Card::factory()->for($program)->create();

    return [
        'business' => $business,
        'location' => $location,
        'program' => $program,
        'card' => $card,
        'transaction' => CardTransaction::factory()->for($card)->create(['location_id' => $location->id]),
        'reward' => Reward::factory()->for($card)->create(),
        'stampRequest' => StampRequest::factory()->for($card)->create(['location_id' => $location->id]),
        'auditLog' => AuditLog::factory()->for($business)->create(),
    ];
}

/**
 * Rulează în savepoint, ca o eroare Postgres să nu anuleze tranzacția testului.
 */
function inSavepoint(Closure $callback): mixed
{
    return DB::transaction($callback);
}
