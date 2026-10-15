<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Location;
use App\Models\Program;
use App\Models\Reward;
use App\Models\StampRequest;
use App\Models\User;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTruncation;
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

// Testele de concurență pornesc procese PHP separate, care văd doar date commit-uite.
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Concurrency');

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
 * Afacere cu două locații, un program activ (implicit fără cooldown și fără limită zilnică), un card
 * și câte un membru pentru fiecare rol; managerul e legat de prima locație.
 *
 * @param  array<string, mixed>  $program
 * @return array{business: Business, location: Location, otherLocation: Location, program: Program, card: Card, staff: User, manager: User, owner: User}
 */
function loyaltyFixture(array $program = []): array
{
    $business = Business::factory()->create();
    $location = Location::factory()->for($business)->create();
    $otherLocation = Location::factory()->for($business)->create();
    $programModel = Program::factory()->for($business)->active()->create([
        'cooldown_minutes' => 0,
        'daily_limit' => null,
        ...$program,
    ]);

    return [
        'business' => $business,
        'location' => $location,
        'otherLocation' => $otherLocation,
        'program' => $programModel,
        'card' => Card::factory()->for($programModel)->create(),
        'staff' => User::factory()->withRole($business, Role::Staff)->create(),
        'manager' => User::factory()->withRole($business, Role::Manager, $location)->create(),
        'owner' => User::factory()->withRole($business, Role::Owner)->create(),
    ];
}

/**
 * Rulează în savepoint, ca o eroare Postgres să nu anuleze tranzacția testului.
 */
function inSavepoint(Closure $callback): mixed
{
    return DB::transaction($callback);
}
