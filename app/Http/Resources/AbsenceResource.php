<?php

namespace App\Http\Resources;

use App\Enums\AbsenceReason;
use App\Models\Absence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Absence */
class AbsenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $reason = AbsenceReason::tryFrom((string) $this->reason);

        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'driver_id' => $this->driver_id,
            'transport_route_id' => $this->transport_route_id,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'reason' => $this->reason,
            'reason_code' => $reason?->value,
            'reason_label_en' => $reason?->labelEn(),
            'reason_label_ar' => $reason?->labelAr(),
            'notes' => $this->notes,
            'driver_notified' => $this->driver_notified_at !== null,
            'driver_notified_at' => $this->driver_notified_at?->toIso8601String(),
            'school_notified' => $this->school_notified_at !== null,
            'school_notified_at' => $this->school_notified_at?->toIso8601String(),
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student?->id,
                'full_name' => $this->student?->full_name,
                'grade' => $this->student?->grade,
                'school_id' => $this->student?->school_id,
            ]),
            'driver' => $this->whenLoaded('driver', fn () => $this->driver ? [
                'id' => $this->driver->id,
                'name' => trim(implode(' ', array_filter([
                    $this->driver->first_name,
                    $this->driver->father_name,
                    $this->driver->last_name,
                ]))),
                'primary_phone' => $this->driver->primary_phone,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
