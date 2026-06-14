<?php

namespace App\Services\Trips;

use App\Models\InAppNotification;
use App\Models\TripRequest;

final class TripRequestNotificationService
{
    public function notifyDriverOfNewPendingRequest(TripRequest $tripRequest): void
    {
        if (! config('trips.notifications_enabled', true)) {
            return;
        }

        $tripRequest->loadMissing(['driver', 'student', 'user.guardian', 'student.guardian']);

        $driverUserId = $tripRequest->driver?->user_id;
        if ($driverUserId === null || (int) $driverUserId <= 0) {
            return;
        }

        $studentName = trim((string) ($tripRequest->student?->full_name ?? ''));
        if ($studentName === '') {
            $studentName = 'الطالب';
        }

        $parentName = $tripRequest->parentDisplayName();
        if ($parentName === '—') {
            $parentName = 'ولي الأمر';
        }

        InAppNotification::query()->create([
            'user_id' => (int) $driverUserId,
            'title' => 'طلب رحلة جديد',
            'body' => "طلب رحلة من {$parentName} للطالب {$studentName}. يرجى الموافقة أو الرفض.",
            'data' => [
                'type' => 'TRIP_REQUEST',
                'trip_request_id' => $tripRequest->id,
                'student_id' => $tripRequest->student_id,
                'status' => 'pending',
            ],
        ]);
    }

    public function notifyParentOfDriverDecision(TripRequest $tripRequest, string $status): void
    {
        if (! config('trips.notifications_enabled', true)) {
            return;
        }

        if (! in_array($status, ['accepted', 'rejected'], true)) {
            return;
        }

        $tripRequest->loadMissing(['user', 'driver', 'student', 'tripHistory']);

        $parentUserId = (int) ($tripRequest->user_id ?? 0);
        if ($parentUserId <= 0) {
            return;
        }

        $studentName = trim((string) ($tripRequest->student?->full_name ?? ''));
        if ($studentName === '') {
            $studentName = 'الطالب';
        }

        $driverName = $tripRequest->driverDisplayName();
        if ($driverName === '—') {
            $driverName = 'السائق';
        }

        if ($status === 'accepted') {
            $title = 'تم قبول طلب الرحلة';
            $body = "قبل السائق {$driverName} طلب الرحلة للطالب {$studentName} (الذهاب والعودة).";
            $type = 'TRIP_REQUEST_ACCEPTED';
        } else {
            $title = 'تم رفض طلب الرحلة';
            $body = "رفض السائق {$driverName} طلب الرحلة للطالب {$studentName}.";
            $type = 'TRIP_REQUEST_REJECTED';
        }

        InAppNotification::query()->create([
            'user_id' => $parentUserId,
            'title' => $title,
            'body' => $body,
            'data' => [
                'type' => $type,
                'trip_request_id' => $tripRequest->id,
                'student_id' => $tripRequest->student_id,
                'driver_id' => $tripRequest->driver_id,
                'status' => $status,
            ],
        ]);
    }

    public function notifyDriverOfCancelledRequest(TripRequest $tripRequest): void
    {
        if (! config('trips.notifications_enabled', true)) {
            return;
        }

        $tripRequest->loadMissing(['driver', 'student', 'user.guardian', 'student.guardian', 'tripHistory']);

        $driverUserId = $tripRequest->driver?->user_id;
        if ($driverUserId === null || (int) $driverUserId <= 0) {
            return;
        }

        $studentName = trim((string) ($tripRequest->student?->full_name ?? ''));
        if ($studentName === '') {
            $studentName = 'الطالب';
        }

        $parentName = $tripRequest->parentDisplayName();
        if ($parentName === '—') {
            $parentName = 'ولي الأمر';
        }

        $slotKey = app(TripRequestSlotKeyResolver::class)->slotKeyForRequest($tripRequest);
        $slotLabel = $this->slotLabel($slotKey);

        InAppNotification::query()->create([
            'user_id' => (int) $driverUserId,
            'title' => 'إلغاء طلب رحلة',
            'body' => "ألغى {$parentName} طلب {$slotLabel} للطالب {$studentName}.",
            'data' => [
                'type' => 'TRIP_REQUEST_CANCELLED',
                'trip_request_id' => $tripRequest->id,
                'student_id' => $tripRequest->student_id,
                'trip_slot' => $slotKey,
                'status' => 'cancelled',
            ],
        ]);
    }

    private function slotLabel(?string $slotKey): string
    {
        return match ($slotKey) {
            'MORNING_PICKUP' => 'الذهاب الصباحي',
            'MORNING_RETURN' => 'العودة الصباحية',
            'EVENING_PICKUP' => 'الذهاب المسائي',
            'EVENING_RETURN' => 'العودة المسائية',
            default => 'الرحلة',
        };
    }
}
