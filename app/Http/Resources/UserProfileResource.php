<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin User */
class UserProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'image' => $this->image ? Storage::disk('public')->url($this->image) : null,
            'phone' => $this->phone,
            'city' => $this->city,
            'licenceNumber' => $this->licence_number,
            'votes' => (int) $this->votes,
            'rate' => (float) $this->rate,
            'isVerified' => (bool) $this->is_verified,
        ];
    }
}
