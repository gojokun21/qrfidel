<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BusinessScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $businessId = app(CurrentBusiness::class)->id();

        if ($businessId !== null) {
            $builder->where($model->qualifyColumn('business_id'), $businessId);
        }
    }
}
