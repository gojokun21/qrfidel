<?php

namespace App\Http\Requests\Dashboard\Concerns;

use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

trait ValidatesBusinessLocation
{
    /**
     * Locația trebuie să fie a afacerii curente.
     */
    protected function locationOfCurrentBusiness(): Exists
    {
        return Rule::exists('locations', 'id')->where('business_id', app(CurrentBusiness::class)->id());
    }
}
