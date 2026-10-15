<?php

namespace App\Models\Concerns;

use App\Exceptions\TenancyViolation;
use App\Models\Business;
use App\Support\Tenancy\BusinessScope;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Multi-tenancy pentru tabelele de business (SDD §3):
 * - global scope pe afacerea curentă (doar când contextul e setat);
 * - la creare, business_id se completează din context sau din părinte și trebuie să coincidă cu toate sursele;
 * - un rând nu poate fi mutat la altă afacere.
 */
trait BelongsToBusiness
{
    public static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope(new BusinessScope);

        static::creating(function (self $model): void {
            $model->assignBusiness();
        });

        static::updating(function (self $model): void {
            if ($model->isDirty('business_id')) {
                throw new TenancyViolation(sprintf('%s #%s cannot be moved to another business.', static::class, $model->getKey()));
            }

            $model->assertParentsBelongToBusiness();
        });
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Cheile străine spre modele care aparțin și ele unei afaceri; afacerea lor trebuie să fie aceeași.
     *
     * @return array<string, class-string<Model>>
     */
    protected function businessParents(): array
    {
        return [];
    }

    /**
     * Rândurile fără afacere (de ex. audit la nivel de platformă) sunt permise doar unde modelul o spune.
     */
    protected function businessIsOptional(): bool
    {
        return false;
    }

    protected function assignBusiness(): void
    {
        $candidates = [];

        if ($this->getAttribute('business_id') !== null) {
            $candidates['business_id'] = (int) $this->getAttribute('business_id');
        }

        $contextId = app(CurrentBusiness::class)->id();
        if ($contextId !== null) {
            $candidates['current business'] = $contextId;
        }

        foreach (array_keys($this->businessParents()) as $foreignKey) {
            $parentBusinessId = $this->parentBusinessId($foreignKey);
            if ($parentBusinessId !== null) {
                $candidates[$foreignKey] = $parentBusinessId;
            }
        }

        $businessIds = array_values(array_unique($candidates));

        if (count($businessIds) > 1) {
            throw new TenancyViolation(sprintf(
                'Cannot create %s across businesses: %s.',
                static::class,
                collect($candidates)->map(fn (int $id, string $source) => "{$source}={$id}")->implode(', '),
            ));
        }

        if ($businessIds === []) {
            if ($this->businessIsOptional()) {
                return;
            }

            throw new TenancyViolation(sprintf('Cannot create %s without a business.', static::class));
        }

        $this->setAttribute('business_id', $businessIds[0]);
    }

    /**
     * La update, un părinte schimbat (de ex. location_id) trebuie să fie tot din afacerea rândului.
     */
    protected function assertParentsBelongToBusiness(): void
    {
        foreach (array_keys($this->businessParents()) as $foreignKey) {
            if (! $this->isDirty($foreignKey)) {
                continue;
            }

            $parentBusinessId = $this->parentBusinessId($foreignKey);

            if ($parentBusinessId !== null && $parentBusinessId !== (int) $this->getAttribute('business_id')) {
                throw new TenancyViolation(sprintf(
                    '%s #%s cannot point %s to another business.',
                    static::class,
                    $this->getKey(),
                    $foreignKey,
                ));
            }
        }
    }

    private function parentBusinessId(string $foreignKey): ?int
    {
        $parentId = $this->getAttribute($foreignKey);
        $parentClass = $this->businessParents()[$foreignKey];

        if ($parentId === null) {
            return null;
        }

        $businessId = $parentClass::query()->withoutGlobalScopes()->whereKey($parentId)->value('business_id');

        return $businessId === null ? null : (int) $businessId;
    }
}
