<?php

namespace App\Services\Loyalty;

use App\Enums\BusinessStatus;
use App\Enums\Role;
use App\Models\Business;
use App\Models\User;
use App\Services\Loyalty\Concerns\RequiresOwner;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Crearea afacerii (self-serve: creatorul devine owner) și setările ei. Slug-ul e fixat la creare
 * (apare în QR-urile tipărite `/b/{slug}`), iar `status` îl schimbă doar adminul platformei (ADR 006).
 */
class BusinessService
{
    use RequiresOwner;

    public function __construct(private AuditTrail $audit) {}

    /**
     * @param  array{name: string, slug?: string|null, type: string, timezone: string, brand_color?: string|null}  $data
     */
    public function create(User $creator, array $data): Business
    {
        try {
            return $this->createBusiness($creator, $data);
        } catch (UniqueConstraintViolationException) {
            // Altcineva a luat același slug între verificare și insert.
            throw ValidationException::withMessages(['slug' => 'Adresa e deja folosită de altă afacere. Încearcă din nou sau alege alta.']);
        }
    }

    /**
     * @param  array{name: string, slug?: string|null, type: string, timezone: string, brand_color?: string|null}  $data
     */
    private function createBusiness(User $creator, array $data): Business
    {
        return DB::transaction(function () use ($creator, $data): Business {
            $business = Business::query()->create([
                'name' => $data['name'],
                'slug' => ($data['slug'] ?? null) ?: $this->uniqueSlug($data['name']),
                'type' => $data['type'],
                'timezone' => $data['timezone'],
                'brand_color' => $this->normalizeColor($data['brand_color'] ?? null),
            ]);

            $business->members()->attach($creator, ['role' => Role::Owner]);

            $this->audit->recordForBusiness($creator, $business, 'business.created', $business, ['slug' => $business->slug]);

            return $business;
        });
    }

    /**
     * @param  array{name: string, type: string, timezone: string, brand_color?: string|null}  $data
     */
    public function update(User $actor, Business $business, array $data): Business
    {
        $this->authorizeOwner($actor, $business);

        return DB::transaction(function () use ($actor, $business, $data): Business {
            $business->fill([
                'name' => $data['name'],
                'type' => $data['type'],
                'timezone' => $data['timezone'],
                'brand_color' => $this->normalizeColor($data['brand_color'] ?? null),
            ]);

            $changes = array_keys($business->getDirty());
            $business->save();

            $this->audit->recordForBusiness($actor, $business, 'business.updated', $business, ['changed' => $changes]);

            return $business;
        });
    }

    /**
     * Suspendarea de către adminul platformei (SDD §3, ADR 014). Idempotentă pe stare: o afacere deja suspendată
     * rămâne așa, fără audit nou. Întoarce false dacă nu s-a schimbat nimic.
     */
    public function suspend(User $admin, Business $business, ?string $reason = null): bool
    {
        return $this->changeStatus($admin, $business, BusinessStatus::Suspended, 'business.suspended', $reason);
    }

    public function reactivate(User $admin, Business $business, ?string $reason = null): bool
    {
        return $this->changeStatus($admin, $business, BusinessStatus::Active, 'business.reactivated', $reason);
    }

    private function changeStatus(User $admin, Business $business, BusinessStatus $status, string $action, ?string $reason): bool
    {
        if (! $admin->isPlatformAdmin()) {
            throw new AuthorizationException('Doar adminul platformei poate suspenda sau reactiva o afacere.');
        }

        $changed = DB::transaction(function () use ($admin, $business, $status, $action, $reason): bool {
            /** @var Business $locked */
            $locked = Business::query()->lockForUpdate()->findOrFail($business->id);

            if ($locked->status === $status) {
                return false;
            }

            $locked->forceFill(['status' => $status])->save();
            $this->audit->recordForBusiness($admin, $locked, $action, $locked, $reason === null ? [] : ['reason' => $reason], withIp: false);

            return true;
        });

        $business->refresh();

        return $changed;
    }

    /**
     * Slug din nume; la coliziune adaugă -2, -3... (unicitatea finală o garantează indexul din DB).
     */
    public function uniqueSlug(string $name): string
    {
        $base = rtrim(Str::limit(Str::slug($name), 50, ''), '-') ?: 'afacere';
        $taken = Business::query()
            ->where('slug', $base)
            ->orWhere('slug', 'like', $base.'-%')
            ->pluck('slug')
            ->all();

        $slug = $base;
        for ($i = 2; in_array($slug, $taken, true); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    private function normalizeColor(?string $color): ?string
    {
        return $color === null || $color === '' ? null : Str::lower($color);
    }
}
