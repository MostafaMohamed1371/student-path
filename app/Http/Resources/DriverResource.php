<?php

namespace App\Http\Resources;

use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Driver */
class DriverResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $idCardImage = $this->normalizeStoragePath($this->id_card_image);
        $licenseImage = $this->normalizeStoragePath($this->license_image);
        $nonConvictionCertificate = $this->normalizeStoragePath($this->non_conviction_certificate);

        return [
            'driverId' => (string) $this->id,
            'userId' => $this->user_id ? (string) $this->user_id : null,
            'schoolId' => (string) $this->school_id,
            'firstName' => $this->first_name,
            'fatherName' => $this->father_name,
            'grandfatherName' => $this->grandfather_name,
            'lastName' => $this->last_name,
            'age' => (int) $this->age,
            'idCardNumber' => $this->id_card_number,
            'licenseNumber' => $this->license_number,
            'primaryPhone' => $this->primary_phone,
            'emergencyPhone' => $this->emergency_phone,
            'residentialAddress' => $this->residential_address,
            'status' => $this->status,
            'monthlySubscriptionPrice' => $this->monthly_subscription_price !== null
                ? (int) $this->monthly_subscription_price
                : null,
            'idCardImage' => $idCardImage,
            'licenseImage' => $licenseImage,
            'nonConvictionCertificate' => $nonConvictionCertificate,
            'busId' => $this->bus?->id ? (string) $this->bus->id : null,
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
