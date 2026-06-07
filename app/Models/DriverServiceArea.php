<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'driver_id',
    'district_id',
    'area_id',
    'monthly_subscription_price',
    'sort_order',
])]
class DriverServiceArea extends Model
{
    protected function casts(): array
    {
        return [
            'monthly_subscription_price' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function neighborhoods(): BelongsToMany
    {
        return $this->belongsToMany(Neighborhood::class, 'driver_service_area_neighborhood');
    }
}
