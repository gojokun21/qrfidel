<?php

namespace App\Services\Loyalty;

use App\Enums\RewardStatus;
use App\Enums\TransactionType;
use App\Models\Business;
use App\Models\CardTransaction;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Reward;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use InvalidArgumentException;

/**
 * Rapoartele v1 (SDD §9, ADR 014): interogări agregate pe `card_transactions` și `rewards`.
 *
 * - zilele se taie la miezul nopții în timezone-ul afacerii; perioada = ultimele N zile, inclusiv azi;
 * - se numără doar ștampilele și folosirile neanulate (`voided_by_id IS NULL`); rândurile void/expire nu intră;
 * - managerul legat de o locație vede doar locația lui (ca în TransactionLog / VoidService).
 */
class ReportService
{
    /** @var list<int> */
    public const PERIODS = [7, 30, 90];

    public const DEFAULT_PERIOD = 30;

    public function __construct(private readonly TransactionLog $log) {}

    /**
     * Perioada cerută dacă e una permisă, altfel cea implicită.
     */
    public static function period(mixed $days): int
    {
        return in_array((int) $days, self::PERIODS, true) ? (int) $days : self::DEFAULT_PERIOD;
    }

    public static function periodLabel(int $days): string
    {
        return $days === 7 ? 'Ultimele 7 zile' : "Ultimele {$days} de zile";
    }

    public function forMember(Business $business, Membership $membership, int $days = self::DEFAULT_PERIOD): BusinessReport
    {
        $this->assertMemberOf($business, $membership);

        return $this->forBusiness($business, $days, $this->log->ownLocation($membership));
    }

    /**
     * Doar cifrele rapide de pe home (ștampile azi / 7 zile, clienți activi în perioada implicită), dintr-o singură interogare.
     *
     * @return array{stampsToday: int, stampsWeek: int, activeCustomers: int}
     */
    public function headline(Business $business, Membership $membership): array
    {
        $this->assertMemberOf($business, $membership);
        [$today, $week, $from] = $this->window($business, self::DEFAULT_PERIOD);
        $totals = $this->totals($business, $this->log->ownLocation($membership), $today, $week, $from);

        return [
            'stampsToday' => (int) ($totals->stamps_today ?? 0),
            'stampsWeek' => (int) ($totals->stamps_week ?? 0),
            'activeCustomers' => (int) ($totals->customers ?? 0),
        ];
    }

    public function forBusiness(Business $business, int $days = self::DEFAULT_PERIOD, ?int $locationId = null): BusinessReport
    {
        $days = self::period($days);
        [$today, $week, $from] = $this->window($business, $days);
        $totals = $this->totals($business, $locationId, $today, $week, $from);

        return new BusinessReport(
            days: $days,
            from: $from,
            locationId: $locationId,
            activeCustomers: (int) ($totals->customers ?? 0),
            stampsToday: (int) ($totals->stamps_today ?? 0),
            stampsWeek: (int) ($totals->stamps_week ?? 0),
            stamps: (int) ($totals->stamps ?? 0),
            rewardsIssued: $this->rewardsIssued($business, $locationId, $from),
            rewardsRedeemed: (int) ($totals->redeems ?? 0),
            locations: $this->locations($business, $locationId, $from),
            staff: $this->staff($business, $locationId, $from),
        );
    }

    private function assertMemberOf(Business $business, Membership $membership): void
    {
        if ($membership->business_id !== $business->id) {
            throw new InvalidArgumentException('Membrul nu face parte din afacere.');
        }
    }

    /**
     * Începutul zilei de azi, al ultimelor 7 zile și al perioadei, în timezone-ul afacerii.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: CarbonImmutable}
     */
    private function window(Business $business, int $days): array
    {
        $today = CarbonImmutable::now($business->timezone)->startOfDay();

        return [$today, $today->subDays(6), $today->subDays(self::period($days) - 1)];
    }

    private function totals(Business $business, ?int $locationId, CarbonImmutable $today, CarbonImmutable $week, CarbonImmutable $from): ?object
    {
        return $this->counted($business, $locationId, $from->min($week))
            ->join('cards', 'cards.id', '=', 'card_transactions.card_id')
            ->selectRaw(
                'COALESCE(SUM(card_transactions.quantity) FILTER (WHERE card_transactions.type = ? AND card_transactions.created_at >= ?), 0) AS stamps_today,
                 COALESCE(SUM(card_transactions.quantity) FILTER (WHERE card_transactions.type = ? AND card_transactions.created_at >= ?), 0) AS stamps_week,
                 COALESCE(SUM(card_transactions.quantity) FILTER (WHERE card_transactions.type = ? AND card_transactions.created_at >= ?), 0) AS stamps,
                 COUNT(*) FILTER (WHERE card_transactions.type = ? AND card_transactions.created_at >= ?) AS redeems,
                 COUNT(DISTINCT cards.customer_id) FILTER (WHERE card_transactions.created_at >= ?) AS customers',
                [
                    TransactionType::Stamp->value, $today->utc(),
                    TransactionType::Stamp->value, $week->utc(),
                    TransactionType::Stamp->value, $from->utc(),
                    TransactionType::Redeem->value, $from->utc(),
                    $from->utc(),
                ],
            )
            ->toBase()
            ->first();
    }

    /**
     * Ștampilele și folosirile neanulate ale afacerii (eventual ale unei locații) de la `$from` încoace.
     *
     * @return Builder<CardTransaction>
     */
    private function counted(Business $business, ?int $locationId, CarbonImmutable $from): Builder
    {
        return CardTransaction::query()
            ->where('card_transactions.business_id', $business->id)
            ->when($locationId !== null, fn (Builder $query) => $query->where('card_transactions.location_id', $locationId))
            ->whereIn('card_transactions.type', [TransactionType::Stamp, TransactionType::Redeem])
            ->whereNull('card_transactions.voided_by_id')
            ->where('card_transactions.created_at', '>=', $from->utc());
    }

    /**
     * Recompensele emise în perioadă (fără cele anulate odată cu ștampila care le-a emis). Pentru o locație,
     * recompensa ține de locația ștampilei care a emis-o.
     */
    private function rewardsIssued(Business $business, ?int $locationId, CarbonImmutable $from): int
    {
        return Reward::query()
            ->where('rewards.business_id', $business->id)
            ->where('rewards.status', '!=', RewardStatus::Voided)
            ->where('rewards.issued_at', '>=', $from->utc())
            ->when($locationId !== null, fn (Builder $query) => $query->whereExists(fn (QueryBuilder $issuer) => $issuer
                ->selectRaw('1')
                ->from('card_transactions')
                ->whereColumn('card_transactions.id', 'rewards.issued_by_transaction_id')
                ->where('card_transactions.location_id', $locationId)))
            ->count();
    }

    /**
     * Toate locațiile (sau doar cea a managerului), cele mai active primele; cele fără activitate au 0.
     *
     * @return list<array{id: int, name: string, stamps: int, redeems: int}>
     */
    private function locations(Business $business, ?int $locationId, CarbonImmutable $from): array
    {
        $activity = $this->counted($business, $locationId, $from)
            ->whereNotNull('location_id')
            ->groupBy('location_id')
            ->selectRaw('location_id, COALESCE(SUM(quantity) FILTER (WHERE type = ?), 0) AS stamps, COUNT(*) FILTER (WHERE type = ?) AS redeems', [
                TransactionType::Stamp->value, TransactionType::Redeem->value,
            ])
            ->toBase()
            ->get()
            ->keyBy('location_id');

        return Location::query()
            ->where('business_id', $business->id)
            ->when($locationId !== null, fn (Builder $query) => $query->whereKey($locationId))
            ->get(['id', 'name'])
            ->map(fn (Location $location) => [
                'id' => $location->id,
                'name' => $location->name,
                'stamps' => (int) ($activity[$location->id]->stamps ?? 0),
                'redeems' => (int) ($activity[$location->id]->redeems ?? 0),
            ])
            ->sort(fn (array $a, array $b) => [$b['stamps'], $b['redeems'], $a['name']] <=> [$a['stamps'], $a['redeems'], $b['name']])
            ->values()
            ->all();
    }

    /**
     * Ce a făcut fiecare angajat în perioadă: ștampile și folosiri neanulate, plus anulările făcute de el.
     *
     * @return list<array{id: int, name: string, stamps: int, redeems: int, voids: int}>
     */
    private function staff(Business $business, ?int $locationId, CarbonImmutable $from): array
    {
        $rows = CardTransaction::query()
            ->where('business_id', $business->id)
            ->when($locationId !== null, fn (Builder $query) => $query->where('location_id', $locationId))
            ->where('created_at', '>=', $from->utc())
            ->whereNotNull('staff_user_id')
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $counted) => $counted
                    ->whereIn('type', [TransactionType::Stamp, TransactionType::Redeem])
                    ->whereNull('voided_by_id'))
                ->orWhere('type', TransactionType::Void))
            ->groupBy('staff_user_id')
            ->selectRaw(
                'staff_user_id,
                 COALESCE(SUM(quantity) FILTER (WHERE type = ?), 0) AS stamps,
                 COUNT(*) FILTER (WHERE type = ?) AS redeems,
                 COUNT(*) FILTER (WHERE type = ?) AS voids',
                [TransactionType::Stamp->value, TransactionType::Redeem->value, TransactionType::Void->value],
            )
            ->toBase()
            ->get();

        $names = User::query()->whereKey($rows->pluck('staff_user_id'))->pluck('name', 'id');

        return $rows
            ->map(fn (object $row) => [
                'id' => (int) $row->staff_user_id,
                'name' => (string) ($names[$row->staff_user_id] ?? 'Cont șters'),
                'stamps' => (int) $row->stamps,
                'redeems' => (int) $row->redeems,
                'voids' => (int) $row->voids,
            ])
            ->sort(fn (array $a, array $b) => [$b['stamps'], $b['redeems'], $a['name']] <=> [$a['stamps'], $a['redeems'], $b['name']])
            ->values()
            ->all();
    }
}
