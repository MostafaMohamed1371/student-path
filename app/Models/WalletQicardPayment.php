<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletQicardPayment extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'currency',
        'request_id',
        'payment_id',
        'status',
        'form_url',
        'gateway_create_response',
        'gateway_status_response',
        'credited_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'gateway_create_response' => 'array',
            'gateway_status_response' => 'array',
            'credited_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
