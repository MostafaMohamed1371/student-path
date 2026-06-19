<?php

namespace App\Models;

use App\Services\Drivers\DriverDeletionService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'school_id',
    'district_id',
    'area_id',
    'user_id',
    'first_name',
    'father_name',
    'grandfather_name',
    'last_name',
    'age',
    'id_card_number',
    'license_number',
    'primary_phone',
    'emergency_phone',
    'residential_address',
    'route_description',
    'status',
    'monthly_subscription_price',
    'shift_period',
    'id_card_image',
    'license_image',
    'non_conviction_certificate',
])]
class Driver extends Model
{
    protected static function booted(): void
    {
        static::deleting(function (Driver $driver): void {
            app(DriverDeletionService::class)->deleteTripsForDriver($driver);
        });
    }

    protected function casts(): array
    {
        return [
            'monthly_subscription_price' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
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
        return $this->belongsToMany(Neighborhood::class, 'driver_neighborhood');
    }

    public function serviceAreas(): HasMany
    {
        return $this->hasMany(DriverServiceArea::class)->orderBy('sort_order')->orderBy('id');
    }

    public function bus(): HasOne
    {
        return $this->hasOne(Bus::class);
    }

    public function transportRoutes(): HasMany
    {
        return $this->hasMany(TransportRoute::class);
    }

    public function tripHistories(): HasMany
    {
        return $this->hasMany(TripHistory::class);
    }
}
