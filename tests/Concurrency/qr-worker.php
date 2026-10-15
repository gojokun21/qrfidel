<?php

/*
 * Proces separat pentru testele de concurență: bootează aplicația pe baza de test primită prin mediu,
 * așteaptă momentul comun de start și apelează QrTokenService::resolve() cu același token ca procesul-pereche.
 *
 * Argumente: <token> <staff_user_id> <location_id> <start_at_unix_float>
 * Ieșire (stdout): JSON {"status":"ok","card_id":..} sau {"status":"rejected","message":..}
 */

use App\Exceptions\InvalidQrToken;
use App\Models\Location;
use App\Models\User;
use App\Services\Loyalty\QrTokenService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $token, $staffId, $locationId, $startAt] = $argv;

$staff = User::query()->findOrFail((int) $staffId);
$location = Location::query()->findOrFail((int) $locationId);
$service = $app->make(QrTokenService::class);

// Bariera: ambele procese au conexiunea și modelele încărcate înainte să pornească.
// `late` = procesul a ajuns după momentul comun, deci cursa nu a fost reală (testul o verifică).
$wait = (float) $startAt - microtime(true);
$late = $wait <= 0;
if (! $late) {
    usleep((int) ($wait * 1_000_000));
}

try {
    $card = $service->resolve($token, $staff, $location);
    echo json_encode(['status' => 'ok', 'card_id' => $card->id, 'late' => $late]);
} catch (InvalidQrToken $e) {
    echo json_encode(['status' => 'rejected', 'message' => $e->getMessage(), 'late' => $late]);
}
