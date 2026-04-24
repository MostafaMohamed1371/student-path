<?php

namespace App\Http\Resources;

use App\Models\Guardian;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Guardian */
class GuardianResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'guardianId' => (string) $this->id,
            'schoolId' => $this->school_id ? (string) $this->school_id : null,
            'fullName' => $this->full_name,
            'phone' => $this->phone,
            'backupPhone' => $this->backup_phone,
            'idCardNumber' => $this->id_card_number,
            'status' => $this->status,
            'childrenCount' => (int) ($this->students_count ?? 0),
        ];
    }
}
