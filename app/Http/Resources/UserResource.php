<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $driver = $this->relationLoaded('driver') ? $this->driver : null;
        $driverName = null;
        if ($driver) {
            $driverName = trim(($driver->first_name ?? '').' '.($driver->father_name ?? '').' '.($driver->last_name ?? ''));
            $driverName = $driverName !== '' ? $driverName : null;
        }

        return [
            'id' => $driver?->id ?? $this->id,
            'name' => $driverName ?? $this->name,
            'phone' => $driver?->primary_phone ?? $this->phone,
            'phone_verified_at' => $this->phone_verified_at?->toIso8601String(),
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
