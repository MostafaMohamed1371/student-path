<?php

namespace App\Http\Resources;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Student */
class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'studentId' => (string) $this->id,
            'schoolId' => (string) $this->school_id,
            'guardianId' => $this->guardian_id ? (string) $this->guardian_id : null,
            'fullName' => $this->full_name,
            'gender' => $this->gender,
            'dateOfBirth' => $this->date_of_birth?->format('Y-m-d'),
            'age' => $this->age !== null ? (int) $this->age : null,
            'profilePhoto' => $this->normalizeStoragePath($this->profile_photo),
            'grade' => $this->grade,
            'studentPhone' => $this->student_phone,
            'relationship' => $this->relationship,
            'districtArea' => $this->district_area,
            'nearestLandmark' => $this->nearest_landmark,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'status' => $this->status,
            'guardianName' => $this->guardian?->full_name ?? $this->guardian_name,
            'guardianPhone' => $this->guardian?->phone ?? $this->guardian_primary_phone,
        ];
    }

    private function normalizeStoragePath(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        $normalized = ltrim($path, '/');
        $normalized = (string) preg_replace('#^(?:student-path/)?storage/app/public/#', '', $normalized);
        $normalized = (string) preg_replace('#^public/storage/#', '', $normalized);

        return '/student-path/storage/app/public/'.$normalized;
    }
}
