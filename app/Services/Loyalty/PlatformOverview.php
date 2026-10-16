<?php

namespace App\Services\Loyalty;

use App\Enums\BusinessStatus;
use App\Enums\Role;
use App\Enums\TransactionType;
use App\Models\Business;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Program;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Cifrele panoului de admin (ADR 014), peste toate afacerile. Rutele de admin nu au context de afacere,
 * dar global scope-urile sunt scoase explicit, ca rezultatul să nu depindă de context.
 * „Ultimele 30 de zile” se taie în timezone-ul fiecărei afaceri, ca în rapoartele ei (ReportService).
 */
class PlatformOverview
{
    public const PER_PAGE = 25;

    public const STAMPS_DAYS = 30;

    public function __construct(private readonly ReportService $reports) {}

    /**
     * @return array{businesses_active: int, businesses_suspended: int, customers: int, cards: int, stamps_30_days: int}
     */
    public function totals(): array
    {
        $statuses = Business::query()->toBase()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');

        return [
            'businesses_active' => (int) ($statuses[BusinessStatus::Active->value] ?? 0),
            'businesses_suspended' => (int) ($statuses[BusinessStatus::Suspended->value] ?? 0),
            'customers' => Customer::query()->count(),
            'cards' => Card::query()->withoutGlobalScopes()->count(),
            'stamps_30_days' => (int) $this->recentStamps('stamp_business.timezone')
                ->join('businesses as stamp_business', 'stamp_business.id', '=', 'card_transactions.business_id')
                ->sum('card_transactions.quantity'),
        ];
    }

    /**
     * Afacerile cu ownerii, numărul de locații și carduri și ștampilele din ultimele 30 de zile.
     * Căutarea e după nume sau slug.
     *
     * @return LengthAwarePaginator<int, Business>
     */
    public function businesses(?string $search = null, ?BusinessStatus $status = null): LengthAwarePaginator
    {
        $search = trim((string) $search);

        return Business::query()
            ->select('businesses.*')
            ->selectSub(
                $this->recentStamps('businesses.timezone')
                    ->selectRaw('COALESCE(SUM(card_transactions.quantity), 0)')
                    ->whereColumn('card_transactions.business_id', 'businesses.id'),
                'stamps_30_days',
            )
            ->withCount([
                'locations' => fn (Builder $query) => $query->withoutGlobalScopes(),
                'cards' => fn (Builder $query) => $query->withoutGlobalScopes(),
            ])
            ->with(['members' => fn ($query) => $query->wherePivot('role', Role::Owner->value)->orderBy('users.name')])
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.addcslashes($search, '\\%_').'%';
                $query->where(fn (Builder $match) => $match->where('name', 'ilike', $like)->orWhere('slug', 'ilike', $like));
            })
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * Detaliul unei afaceri: ownerii, locațiile, programele și rapoartele pe ultimele 30 de zile.
     *
     * @return array{owners: Collection<int, User>, locations: Collection<int, Location>, programs: Collection<int, Program>, report: BusinessReport}
     */
    public function details(Business $business): array
    {
        return [
            'owners' => $business->members()->wherePivot('role', Role::Owner->value)->orderBy('users.name')->get(),
            'locations' => $business->locations()->withoutGlobalScopes()->orderBy('name')->get(),
            'programs' => $business->programs()->withoutGlobalScopes()->orderBy('name')->get(),
            'report' => $this->reports->forBusiness($business, self::STAMPS_DAYS),
        ];
    }

    /**
     * Ștampilele neanulate din ultimele 30 de zile, inclusiv azi, cu ziua tăiată la miezul nopții în timezone-ul
     * afacerii (coloana `$timezoneColumn`). `created_at` e în UTC, fără timezone.
     *
     * @return Builder<CardTransaction>
     */
    private function recentStamps(string $timezoneColumn): Builder
    {
        $since = "((date_trunc('day', (?::timestamp AT TIME ZONE 'UTC') AT TIME ZONE {$timezoneColumn}) - interval '"
            .(self::STAMPS_DAYS - 1)." days') AT TIME ZONE {$timezoneColumn}) AT TIME ZONE 'UTC'";

        return CardTransaction::query()
            ->withoutGlobalScopes()
            ->where('card_transactions.type', TransactionType::Stamp)
            ->whereNull('card_transactions.voided_by_id')
            ->whereRaw("card_transactions.created_at >= {$since}", [CarbonImmutable::now('UTC')->format('Y-m-d H:i:s')]);
    }
}
