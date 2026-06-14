<?php

namespace App\Http\Resources;

use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin School */
class SchoolResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attachmentPath = null;
        if (is_string($this->attachment) && $this->attachment !== '') {
            $attachmentPath = ltrim($this->attachment, '/');
            $attachmentPath = (string) preg_replace('#^(?:student-path/)?storage/app/public/#', '', $attachmentPath);
            $attachmentPath = (string) preg_replace('#^public/storage/#', '', $attachmentPath);
            $attachmentPath = '/student-path/storage/app/public/'.$attachmentPath;
        }

        return [
            'schoolId' => (string) $this->id,
            'schoolNameAr' => $this->name_ar,
            'schoolNameEn' => $this->name_en,
            'province' => $this->province,
            'district' => $this->district,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'status' => $this->status,
            'principalName' => $this->principal_name,
            'adminPhone' => $this->admin_phone,
            'authorizedPersonName' => $this->authorized_person_name,
            'authorizedPersonPhone' => $this->authorized_person_phone,
            'complaintsSupportPhone' => $this->complaints_support_phone,
            'complaintsSupportWhatsapp' => $this->complaints_support_whatsapp,
            'complaintsSupportHours' => $this->complaints_support_hours,
            'notes' => $this->notes,
            'attachment' => $attachmentPath,
        ];
    }
}
