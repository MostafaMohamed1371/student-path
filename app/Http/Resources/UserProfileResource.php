<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $driver = $this->relationLoaded('driver') ? $this->driver : null;
        $imagePath = null;
        if (is_string($this->image) && $this->image !== '') {
            $imagePath = ltrim($this->image, '/');
            $imagePath = (string) preg_replace('#^(?:student-path/)?storage/app/public/#', '', $imagePath);
            $imagePath = (string) preg_replace('#^public/storage/#', '', $imagePath);
        }

        $driverName = null;
        if ($driver) {
            $driverName = trim(($driver->first_name ?? '').' '.($driver->father_name ?? '').' '.($driver->last_name ?? ''));
            $driverName = $driverName !== '' ? $driverName : null;
        }

        return [
            'id' => (string) ($driver?->id ?? $this->id),
            'name' => $driverName ?? $this->name,
            'image' => $imagePath ? '/student-path/storage/app/public/'.$imagePath : null,
            'phone' => (string) ($driver?->primary_phone ?? $this->phone),
            'city' => $driver?->residential_address ?? $this->city,
            'licenceNumber' => $driver?->license_number ?? $this->licence_number,
            'votes' => (int) $this->votes,
            'rate' => (float) $this->rate,
            'isVerified' => (bool) $this->is_verified,
        ];
    }
}
