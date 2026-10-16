<?php

namespace App\Services\Loyalty;

use App\Exceptions\EnrollmentRefused;
use App\Models\Card;
use App\Models\Customer;
use App\Models\Program;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Support\Facades\DB;

/**
 * Înscrierea clientului într-un program (SDD §5.1): creează cardul 0/N.
 */
class EnrollmentService
{
    public function __construct(
        private readonly AuditTrail $audit,
        private readonly CurrentBusiness $currentBusiness,
    ) {}

    /**
     * Idempotentă: un client deja înscris primește cardul existent (UNIQUE(program_id, customer_id)).
     *
     * @throws EnrollmentRefused
     */
    public function enroll(Customer $customer, Program $program): EnrollResult
    {
        $business = $program->business;

        return $this->currentBusiness->runAs($business, fn (): EnrollResult => DB::transaction(function () use ($customer, $program, $business): EnrollResult {
            // FOR SHARE (ADR 008): o micșorare a stamps_required (FOR UPDATE în ProgramService) nu se poate
            // intercala cu crearea cardului; înscrierile între ele nu se blochează.
            $locked = Program::query()->whereKey($program->getKey())->sharedLock()->firstOrFail();

            if (! $business->isActive()) {
                throw EnrollmentRefused::businessInactive();
            }

            if (! $locked->isActive()) {
                throw EnrollmentRefused::programInactive();
            }

            // createOrFirst rulează INSERT-ul în savepoint: la o înscriere paralelă câștigă un singur card.
            $card = Card::query()->createOrFirst([
                'program_id' => $locked->id,
                'customer_id' => $customer->id,
            ]);

            if ($card->wasRecentlyCreated) {
                $this->audit->record($customer, $card, 'card.enrolled', $card, ['program_id' => $locked->id]);
            }

            return new EnrollResult($card, $card->wasRecentlyCreated);
        }));
    }
}
