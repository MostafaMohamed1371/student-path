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
}
