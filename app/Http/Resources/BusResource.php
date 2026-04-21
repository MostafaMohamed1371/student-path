<?php

namespace App\Http\Resources;

use App\Models\Bus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Bus */
class BusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'busId' => (string) $this->id,
            'busName' => $this->name,
            'busType' => $this->type,
            'busCity' => $this->city,
            'busNumber' => $this->number,
            'busColor' => $this->color,
            'busCapacity' => (int) $this->capacity,
            'fuelType' => $this->fuel_type,
            'busStatus' => $this->status,
            'busAnnualStatus' => (bool) $this->annual_status,
            'busInsurance' => (bool) $this->insurance,
        ];
    }
}
