<?php

/*
 * Proces separat pentru testele de concurență: bootează aplicația pe baza de test primită prin mediu,
 * așteaptă momentul comun de start și apelează StampService::add() pe același card ca procesul-pereche.
 *
 * Argumente: <card_id> <staff_user_id> <location_id> <idempotency_key> <start_at_unix_float>
 * Ieșire (stdout): JSON {"status":"ok","transaction_id":..,"replayed":..} sau {"status":"violation","message":..}
 */

use App\Exceptions\StampRuleViolation;
use App\Models\Card;
use App\Models\Location;
use App\Models\User;
use App\Services\Loyalty\StampService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $cardId, $staffId, $locationId, $key, $startAt] = $argv;

$card = Card::query()->findOrFail((int) $cardId);
$staff = User::query()->findOrFail((int) $staffId);
$location = Location::query()->findOrFail((int) $locationId);
$service = $app->make(StampService::class);

// Bariera: ambele procese au conexiunea și modelele încărcate înainte să pornească.
$wait = (float) $startAt - microtime(true);
if ($wait > 0) {
    usleep((int) ($wait * 1_000_000));
}

try {
    $result = $service->add($card, $staff, $location, 1, $key);
    echo json_encode(['status' => 'ok', 'transaction_id' => $result->transaction->id, 'replayed' => $result->replayed]);
} catch (StampRuleViolation $e) {
    echo json_encode(['status' => 'violation', 'message' => $e->getMessage()]);
}
