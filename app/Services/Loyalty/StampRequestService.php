<?php

namespace App\Services\Loyalty;

use App\Enums\Role;
use App\Enums\StampRequestStatus;
use App\Enums\TransactionSource;
use App\Exceptions\EnrollmentRefused;
use App\Exceptions\IdempotencyConflict;
use App\Exceptions\StampRequestUnavailable;
use App\Exceptions\StampRuleViolation;
use App\Models\Card;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Program;
use App\Models\StampRequest;
use App\Models\User;
use App\Services\Loyalty\Concerns\GuardsCardActions;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Fluxul B (SDD §5.3, ADR 012): clientul cere o ștampilă de la tejghea, angajatul o aprobă sau o respinge.
 * Aprobarea trece prin StampService::add(), deci toate regulile programului se aplică la fel ca la scanare.
 *
 * Lock-urile se iau mereu în ordinea card → cerere (și la client, și la angajat), ca să nu apară deadlock-uri.
 * Expirarea e „leneșă”: o cerere pending trecută de termen e tratată ca expirată și marcată la prima atingere.
 */
class StampRequestService
{
    use GuardsCardActions;

    /** O cerere închisă mai rămâne afișată clientului atâtea minute (aprobată / respinsă / expirată). */
    private const RECENT_MINUTES = 30;

    public function __construct(
        private readonly StampService $stamps,
        private readonly EnrollmentService $enrollment,
        private readonly AuditTrail $audit,
        private readonly CurrentBusiness $currentBusiness,
    ) {}

    /**
     * Cererea clientului la locația de pe tejghea. Clientul neînscris e înscris acum (un singur tap).
     * O cerere încă deschisă pe card e întoarsă din nou (dublu-tap), mutată la locația unde a cerut ultima oară.
     *
     * @throws EnrollmentRefused afacere suspendată sau program inactiv
     * @throws StampRequestUnavailable prea multe cereri noi pe card (SDD §6)
     */
    public function request(Customer $customer, Location $location, Program $program): StampRequest
    {
        if ($program->business_id !== $location->business_id) {
            throw (new ModelNotFoundException)->setModel(Program::class, [$program->id]);
        }

        $card = $this->enrollment->enroll($customer, $program)->card;
        $limiterKey = 'stamp-request:'.$card->id;

        $request = $this->currentBusiness->runAs($location->business, fn (): StampRequest => DB::transaction(function () use ($customer, $location, $card, $limiterKey): StampRequest {
            $card = $this->lockCard($card->id);

            $pending = StampRequest::query()
                ->where('card_id', $card->id)
                ->where('status', StampRequestStatus::Pending)
                ->lockForUpdate()
                ->first();

            if ($pending !== null && $pending->isOpen()) {
                if ($pending->location_id !== $location->id) {
                    $pending->location_id = $location->id;
                    $pending->save();
                }

                return $pending;
            }

            // Se numără doar cererile noi: un dublu-tap pe cererea deschisă nu consumă limita.
            if (RateLimiter::tooManyAttempts($limiterKey, (int) config('loyalty.requests.per_hour'))) {
                throw StampRequestUnavailable::tooMany(RateLimiter::availableIn($limiterKey));
            }

            // Indexul unic „un pending per card” cere ca cererea veche, trecută de termen, să fie închisă întâi.
            $pending?->forceFill(['status' => StampRequestStatus::Expired])->save();

            $request = StampRequest::query()->create([
                'business_id' => $card->business_id,
                'card_id' => $card->id,
                'location_id' => $location->id,
                'quantity' => 1,
                'expires_at' => now()->addMinutes((int) config('loyalty.requests.ttl_minutes')),
            ]);
            $this->audit->record($customer, $card, 'stamp_request.created', $request, ['location_id' => $location->id]);

            return $request;
        }));

        // După commit: o cerere anulată de o eroare nu consumă limita clientului.
        if ($request->wasRecentlyCreated) {
            RateLimiter::hit($limiterKey, 3600);
        }

        return $request;
    }

    /**
     * Aprobarea: ștampila (1…max_stamps_per_visit, aleasă de angajat) se dă la locația cererii.
     * Aceeași cheie de idempotency pe o cerere deja aprobată de ea = replay. O regulă încălcată (cooldown,
     * limită zilnică) anulează tot și lasă cererea pending: angajatul o poate respinge.
     *
     * @throws StampRequestUnavailable
     * @throws StampRuleViolation
     * @throws AuthorizationException
     */
    public function approve(StampRequest $request, User $staff, int $quantity, string $idempotencyKey): StampResult
    {
        $result = $this->transactionally(function () use ($request, $staff, $quantity, $idempotencyKey): ?StampResult {
            [$card, $locked, $location] = $this->lockForStaff($request, $staff);
            $meta = ['stamp_request_id' => $locked->id];

            if ($locked->status === StampRequestStatus::Approved) {
                if ($locked->transaction?->idempotency_key === $idempotencyKey) {
                    return $this->stamps->add($card, $staff, $location, $locked->quantity, $idempotencyKey, TransactionSource::Request, $meta);
                }

                throw StampRequestUnavailable::alreadyHandled();
            }

            if ($locked->status === StampRequestStatus::Rejected) {
                throw StampRequestUnavailable::alreadyHandled();
            }

            if (! $locked->isOpen()) {
                // Marcarea trebuie să rămână: refuzul se aruncă după commit.
                $locked->forceFill(['status' => StampRequestStatus::Expired])->save();

                return null;
            }

            $result = $this->stamps->add($card, $staff, $location, $quantity, $idempotencyKey, TransactionSource::Request, $meta);

            // Cheia unei ștampile mai vechi a cardului (aceeași cantitate și locație) ar da replay: cererea ar fi
            // marcată aprobată fără ștampilă nouă.
            if ($result->replayed) {
                throw new IdempotencyConflict('Cheia de idempotency a fost deja folosită pentru o altă acțiune.');
            }

            $locked->forceFill([
                'status' => StampRequestStatus::Approved,
                'quantity' => $quantity,
                'handled_by' => $staff->id,
                'handled_at' => now(),
                'card_transaction_id' => $result->transaction->id,
            ])->save();

            $this->audit->record($staff, $result->card, 'stamp_request.approved', $locked, [
                'location_id' => $location->id,
                'quantity' => $quantity,
                'transaction_id' => $result->transaction->id,
            ]);

            return $result;
        });

        return $result ?? throw StampRequestUnavailable::expired();
    }

    /**
     * Respingerea. Idempotentă pe stare: o cerere deja respinsă sau expirată rămâne așa; una aprobată e refuzată.
     *
     * @throws StampRequestUnavailable
     * @throws AuthorizationException
     */
    public function reject(StampRequest $request, User $staff): StampRequest
    {
        return DB::transaction(function () use ($request, $staff): StampRequest {
            [$card, $locked, $location] = $this->lockForStaff($request, $staff);

            if ($locked->status === StampRequestStatus::Approved) {
                throw StampRequestUnavailable::alreadyHandled();
            }

            if ($locked->status !== StampRequestStatus::Pending) {
                return $locked;
            }

            if (! $locked->isOpen()) {
                $locked->forceFill(['status' => StampRequestStatus::Expired])->save();

                return $locked;
            }

            $locked->forceFill([
                'status' => StampRequestStatus::Rejected,
                'handled_by' => $staff->id,
                'handled_at' => now(),
            ])->save();

            $this->audit->record($staff, $card, 'stamp_request.rejected', $locked, ['location_id' => $location->id]);

            return $locked;
        });
    }

    /**
     * Cererile deschise ale unei locații, cele mai vechi primele (panoul angajatului).
     *
     * @return Collection<int, StampRequest>
     */
    public function openFor(Location $location): Collection
    {
        return StampRequest::query()
            ->where('location_id', $location->id)
            ->open()
            ->with(['card.customer', 'card.program'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Ultima cerere a cardului, dacă e deschisă sau s-a închis recent (pagina cardului și polling-ul clientului).
     */
    public function latestFor(Card $card): ?StampRequest
    {
        return $this->latestForCards([$card->id])->get($card->id);
    }

    /**
     * Ultimele cereri recente ale mai multor carduri, într-o singură interogare, după card_id.
     *
     * @param  list<int>  $cardIds
     * @return SupportCollection<int, StampRequest>
     */
    public function latestForCards(array $cardIds): SupportCollection
    {
        if ($cardIds === []) {
            return collect();
        }

        $latestIds = StampRequest::query()->withoutGlobalScopes()
            ->selectRaw('max(id)')
            ->whereIn('card_id', $cardIds)
            ->groupBy('card_id');

        $recent = now()->subMinutes(self::RECENT_MINUTES);

        return StampRequest::query()->withoutGlobalScopes()
            ->whereIn('id', $latestIds)
            ->get()
            ->reject(fn (StampRequest $request) => $request->closedAt()?->lt($recent) ?? false)
            ->keyBy('card_id');
    }

    /**
     * Lock pe card, apoi pe cerere (aceeași ordine ca la client); angajatul trebuie să poată lucra la locația cererii.
     *
     * @return array{Card, StampRequest, Location}
     */
    private function lockForStaff(StampRequest $request, User $staff): array
    {
        $card = $this->lockCard($request->card_id);
        $locked = StampRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
        $location = Location::query()->findOrFail($locked->location_id);

        $this->authorizeMember($staff, $card, Role::Staff, $location);

        return [$card, $locked, $location];
    }
}
