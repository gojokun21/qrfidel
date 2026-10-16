<?php

namespace App\View\Composers;

use App\Enums\Role;
use App\Http\Middleware\EnsureBusinessMember;
use App\Models\Business;
use App\Models\Membership;
use App\Models\User;
use App\Support\Tenancy\BusinessResolver;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Datele sidebar-ului din dashboard: afacerea curentă, rolul în ea, linkurile permise și celelalte afaceri ale userului.
 *
 * Pe paginile din afara middleware-ului `business` (profil, afacere nouă) afacerea se alege la fel ca în middleware,
 * dar doar pentru afișare: nu se scrie în sesiune și nu se setează contextul de tenancy.
 */
class SidebarComposer
{
    public function __construct(private CurrentBusiness $currentBusiness, private Request $request) {}

    public function compose(View $view): void
    {
        $user = $this->request->user();

        if (! $user instanceof User) {
            return;
        }

        // Rolul vine din pivot, ca linkurile să nu mai ceară câte un query (`roleIn`) fiecare.
        $businesses = $user->businesses()->orderBy('business_user.id')->get();

        $current = $this->currentBusiness->get()
            ?? BusinessResolver::resolve($user, $this->request->session()->get(EnsureBusinessMember::SESSION_KEY), $businesses);

        if ($current !== null && ! $current->isActive()) {
            $current = null;
        }

        $membership = $current ? $businesses->firstWhere('id', $current->getKey())?->getRelation('membership') : null;
        $role = $membership instanceof Membership ? $membership->role : null;

        $view->with([
            'sidebarUser' => $user,
            'sidebarBusiness' => $current,
            'sidebarRole' => $role,
            'sidebarLinks' => $this->links($role, $user->isPlatformAdmin()),
            // Pe o afacere suspendată middleware-ul nu te lasă să intri, deci nu o oferim la comutare.
            'sidebarOtherBusinesses' => $businesses
                ->filter(fn (Business $business) => $business->isActive() && $business->getKey() !== $current?->getKey())
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values(),
        ]);
    }

    /**
     * @return list<array{route: string, label: string, pattern: string|list<string>, icon: string}>
     */
    private function links(?Role $role, bool $platformAdmin): array
    {
        // Adminul platformei are panoul lui și când nu face parte din nicio afacere (ADR 014).
        $admin = $platformAdmin
            ? [['route' => 'admin.index', 'label' => 'Admin platformă', 'pattern' => 'admin.*', 'icon' => 'globe']]
            : [];

        if ($role === null) {
            return $admin;
        }

        $links = [
            ['route' => 'dashboard', 'label' => 'Dashboard', 'pattern' => 'dashboard', 'icon' => 'home'],
            ['route' => 'staff.scan', 'label' => 'Scanează', 'pattern' => ['staff.scan*', 'staff.cards.*', 'staff.location'], 'icon' => 'qr'],
            ['route' => 'staff.requests', 'label' => 'Cereri', 'pattern' => 'staff.requests*', 'icon' => 'list'],
        ];

        if ($role->atLeast(Role::Manager)) {
            $links[] = ['route' => 'dashboard.programs.index', 'label' => 'Programe', 'pattern' => 'dashboard.programs.*', 'icon' => 'gift'];
            $links[] = ['route' => 'dashboard.locations.index', 'label' => 'Locații', 'pattern' => 'dashboard.locations.*', 'icon' => 'map-pin'];
            $links[] = ['route' => 'dashboard.transactions.index', 'label' => 'Tranzacții', 'pattern' => 'dashboard.transactions.*', 'icon' => 'clock'];
            $links[] = ['route' => 'dashboard.reports', 'label' => 'Rapoarte', 'pattern' => 'dashboard.reports', 'icon' => 'chart'];
        }

        if ($role === Role::Owner) {
            $links[] = ['route' => 'dashboard.team.index', 'label' => 'Echipa', 'pattern' => 'dashboard.team.*', 'icon' => 'users'];
            $links[] = ['route' => 'dashboard.business.edit', 'label' => 'Afacerea', 'pattern' => 'dashboard.business.edit', 'icon' => 'building'];
            $links[] = ['route' => 'dashboard.audit.index', 'label' => 'Jurnal', 'pattern' => 'dashboard.audit.*', 'icon' => 'shield'];
        }

        return [...$links, ...$admin];
    }
}
