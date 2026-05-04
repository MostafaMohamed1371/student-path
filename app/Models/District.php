<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    protected $fillable = [
        'name',
        'sort_order',
    ];

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }
}
