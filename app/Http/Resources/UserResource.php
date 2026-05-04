<?php

namespace App\Http\Resources;

use App\Models\Driver;
use App\Models\Guardian;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Authenticated user payload for AuthController (verify-otp, me):
 * full account fields plus linked driver, school, and guardian.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $driver = $user->relationLoaded('driver') ? $user->driver : null;

        $driverName = null;
        if ($driver instanceof Driver) {
            $driverName = trim(implode(' ', array_filter([
                $driver->first_name ?? null,
                $driver->father_name ?? null,
                $driver->last_name ?? null,
            ])));
            $driverName = $driverName !== '' ? $driverName : null;
        }

        $legacyId = $driver?->id ?? $user->id;
        $legacyName = $driverName ?? $user->name;
        $legacyPhone = $driver?->primary_phone ?? $user->phone;

        return [
            'id' => $legacyId,
            'userId' => $user->id,
            'name' => $legacyName,
            'nameFromAccount' => $user->name,
            'phone' => $legacyPhone,
            'phoneFromAccount' => $user->phone,
            'image' => $this->publicProfileImageUrl($user->image),
            'city' => $driver?->residential_address ?? $user->city,
            'cityFromAccount' => $user->city,
            'licenceNumber' => $driver?->license_number ?? $user->licence_number,
            'licenceNumberFromAccount' => $user->licence_number,
            'votes' => (int) $user->votes,
            'rate' => (float) $user->rate,
            'isVerified' => (bool) $user->is_verified,
            'isActive' => (bool) $user->is_active,
            'isAdmin' => (bool) $user->is_admin,
            'preferredLanguage' => $user->preferred_language,
            'schoolId' => $user->school_id,
            'guardianId' => $user->guardian_id,
            'phoneVerifiedAt' => $user->phone_verified_at?->toIso8601String(),
            'createdAt' => $user->created_at?->toIso8601String(),
            'updatedAt' => $user->updated_at?->toIso8601String(),
            'driver' => $driver instanceof Driver ? $this->driverDetail($driver) : null,
            'school' => ($user->relationLoaded('school') && $user->school instanceof School)
                ? $this->schoolSummary($user->school)
                : null,
            'guardian' => ($user->relationLoaded('guardian') && $user->guardian instanceof Guardian)
                ? $this->guardianSummary($user->guardian)
                : null,
        ];
    }

    private function publicProfileImageUrl(?string $image): ?string
    {
        if (! is_string($image) || $image === '') {
            return null;
        }
        $imagePath = ltrim($image, '/');
        $imagePath = (string) preg_replace('#^(?:student-path/)?storage/app/public/#', '', $imagePath);
        $imagePath = (string) preg_replace('#^public/storage/#', '', $imagePath);

        return '/student-path/storage/app/public/'.$imagePath;
    }

    /**
     * @return array<string, mixed>
     */
    private function driverDetail(Driver $d): array
    {
        return [
            'id' => $d->id,
            'schoolId' => $d->school_id,
            'userId' => $d->user_id,
            'firstName' => $d->first_name,
            'fatherName' => $d->father_name,
            'grandfatherName' => $d->grandfather_name,
            'lastName' => $d->last_name,
            'age' => $d->age,
            'idCardNumber' => $d->id_card_number,
            'licenseNumber' => $d->license_number,
            'primaryPhone' => $d->primary_phone,
            'emergencyPhone' => $d->emergency_phone,
            'residentialAddress' => $d->residential_address,
            'status' => $d->status,
            'idCardImage' => $d->id_card_image,
            'licenseImage' => $d->license_image,
            'nonConvictionCertificate' => $d->non_conviction_certificate,
            'createdAt' => $d->created_at?->toIso8601String(),
            'updatedAt' => $d->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function schoolSummary(School $s): array
    {
        return [
            'id' => $s->id,
            'nameEn' => $s->name_en,
            'nameAr' => $s->name_ar,
            'province' => $s->province,
            'district' => $s->district,
            'address' => $s->address,
            'latitude' => $s->latitude,
            'longitude' => $s->longitude,
            'status' => $s->status,
            'principalName' => $s->principal_name,
            'adminPhone' => $s->admin_phone,
            'authorizedPersonName' => $s->authorized_person_name,
            'authorizedPersonPhone' => $s->authorized_person_phone,
            'notes' => $s->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function guardianSummary(Guardian $g): array
    {
        return [
            'id' => $g->id,
            'schoolId' => $g->school_id,
            'fullName' => $g->full_name,
            'phone' => $g->phone,
            'backupPhone' => $g->backup_phone,
            'idCardNumber' => $g->id_card_number,
            'status' => $g->status,
        ];
    }
}
