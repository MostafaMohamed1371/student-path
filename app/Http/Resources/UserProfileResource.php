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
        $imagePath = null;
        if (is_string($this->image) && $this->image !== '') {
            $imagePath = ltrim($this->image, '/');
            $imagePath = (string) preg_replace('#^(?:student-path/)?storage/app/public/#', '', $imagePath);
            $imagePath = (string) preg_replace('#^public/storage/#', '', $imagePath);
        }

        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'image' => $imagePath ? Storage::disk('public')->url($imagePath) : null,
            'phone' => $this->phone,
            'city' => $this->city,
            'licenceNumber' => $this->licence_number,
            'votes' => (int) $this->votes,
            'rate' => (float) $this->rate,
            'isVerified' => (bool) $this->is_verified,
        ];
    }
}
