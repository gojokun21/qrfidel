<?php

namespace App\Support\Tenancy;

use App\Models\Business;
use Closure;

/**
 * Afacerea în contextul căreia rulează request-ul curent (setată de EnsureBusinessMember).
 * Fără context, global scope-ul BelongsToBusiness nu filtrează (rute client/admin, console).
 */
class CurrentBusiness
{
    private ?Business $business = null;

    public function set(?Business $business): void
    {
        $this->business = $business;
    }

    public function get(): ?Business
    {
        return $this->business;
    }

    public function id(): ?int
    {
        return $this->business?->id;
    }

    public function has(): bool
    {
        return $this->business !== null;
    }

    public function clear(): void
    {
        $this->business = null;
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function runAs(?Business $business, Closure $callback): mixed
    {
        $previous = $this->business;
        $this->business = $business;

        try {
            return $callback();
        } finally {
            $this->business = $previous;
        }
    }
}
