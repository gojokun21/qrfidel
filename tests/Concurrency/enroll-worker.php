<?php

/*
 * Proces separat pentru testul de concurență la înscriere: bootează aplicația pe baza de test primită prin mediu,
 * așteaptă momentul comun de start și înscrie același client în același program ca procesul-pereche.
 *
 * Argumente: <customer_id> <program_id> <start_at_unix_float>
 * Ieșire (stdout): JSON {"status":"ok","card_id":..,"created":..,"late":..}
 */

use App\Models\Customer;
use App\Models\Program;
use App\Services\Loyalty\EnrollmentService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $customerId, $programId, $startAt] = $argv;

$customer = Customer::query()->findOrFail((int) $customerId);
$program = Program::query()->findOrFail((int) $programId);
$service = $app->make(EnrollmentService::class);

// Bariera: ambele procese au conexiunea și modelele încărcate înainte să pornească.
$wait = (float) $startAt - microtime(true);
$late = $wait <= 0;
if (! $late) {
    usleep((int) ($wait * 1_000_000));
}

$result = $service->enroll($customer, $program);
echo json_encode(['status' => 'ok', 'card_id' => $result->card->id, 'created' => $result->created, 'late' => $late]);
