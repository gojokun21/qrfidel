<?php

/*
 * Proces separat pentru testele de concurență: aprobă aceeași cerere de ștampilă ca procesul-pereche.
 *
 * Argumente: <stamp_request_id> <staff_user_id> <idempotency_key> <start_at_unix_float>
 * Ieșire (stdout): JSON {"status":"ok","transaction_id":..,"late":..} sau {"status":"refused","message":..,"late":..}
 */

use App\Exceptions\LoyaltyException;
use App\Models\StampRequest;
use App\Models\User;
use App\Services\Loyalty\StampRequestService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $requestId, $staffId, $key, $startAt] = $argv;

$request = StampRequest::query()->findOrFail((int) $requestId);
$staff = User::query()->findOrFail((int) $staffId);
$service = $app->make(StampRequestService::class);

// Bariera: ambele procese au conexiunea și modelele încărcate înainte să pornească.
$wait = (float) $startAt - microtime(true);
$late = $wait <= 0;
if (! $late) {
    usleep((int) ($wait * 1_000_000));
}

try {
    $result = $service->approve($request, $staff, 1, $key);
    echo json_encode(['status' => 'ok', 'transaction_id' => $result->transaction->id, 'late' => $late]);
} catch (LoyaltyException $e) {
    echo json_encode(['status' => 'refused', 'message' => $e->getMessage(), 'late' => $late]);
}
