<?php

namespace App\Services\Loyalty;

use Carbon\CarbonImmutable;

/**
 * Cifrele din „Rapoarte” (SDD §9, ADR 014) pentru o afacere, eventual limitate la o locație.
 * `stamps`, `rewardsIssued`, `rewardsRedeemed` și `activeCustomers` sunt pe perioada aleasă (`days`);
 * `stampsToday` și `stampsWeek` sunt mereu pe azi și pe ultimele 7 zile.
 */
final readonly class BusinessReport
{
    /**
     * @param  list<array{id: int, name: string, stamps: int, redeems: int}>  $locations
     * @param  list<array{id: int, name: string, stamps: int, redeems: int, voids: int}>  $staff
     */
    public function __construct(
        public int $days,
        public CarbonImmutable $from,
        public ?int $locationId,
        public int $activeCustomers,
        public int $stampsToday,
        public int $stampsWeek,
        public int $stamps,
        public int $rewardsIssued,
        public int $rewardsRedeemed,
        public array $locations,
        public array $staff,
    ) {}

    public function periodLabel(): string
    {
        return ReportService::periodLabel($this->days);
    }
}
