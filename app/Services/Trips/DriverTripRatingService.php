<?php

namespace App\Services\Trips;

use App\Models\Driver;
use App\Models\TripDriverRating;
use App\Models\TripHistory;
use App\Models\User;
use App\Support\ParentContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DriverTripRatingService
{
    /**
     * @return array{
     *     rating: TripDriverRating,
     *     driver_rating_avg: float,
     *     driver_rating_count: int,
     *     updated: bool
     * }
     */
    public function submitForParent(
        User $parent,
        TripHistory $trip,
        int $rating,
        ?string $comment = null,
        ?int $studentId = null,
    ): array {
        $trip->loadMissing(['driver.user', 'tripHistoryStudents.student']);

        if ((int) ($trip->driver_id ?? 0) <= 0 || ! $trip->driver instanceof Driver) {
            throw ValidationException::withMessages([
                'trip' => ['This trip has no driver to rate.'],
            ]);
        }

        if (strtoupper((string) ($trip->status ?? '')) !== 'COMPLETED') {
            throw ValidationException::withMessages([
                'trip' => ['You can rate the driver only after the trip is completed.'],
            ]);
        }

        $ownedStudentIds = array_map('intval', ParentContext::studentIdsFor($parent));
        $tripStudentIds = $trip->tripHistoryStudents
            ->pluck('student_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if ($tripStudentIds === []) {
            throw ValidationException::withMessages([
                'trip' => ['This trip has no students assigned.'],
            ]);
        }

        $eligibleStudentIds = array_values(array_intersect($ownedStudentIds, $tripStudentIds));
        if ($eligibleStudentIds === []) {
            throw ValidationException::withMessages([
                'trip' => ['You are not allowed to rate this trip.'],
            ]);
        }

        if ($studentId !== null) {
            if (! in_array($studentId, $eligibleStudentIds, true)) {
                throw ValidationException::withMessages([
                    'student_id' => ['Select one of your children on this trip.'],
                ]);
            }
        } else {
            sort($eligibleStudentIds);
            $studentId = $eligibleStudentIds[0];
        }

        $comment = $comment !== null ? trim($comment) : null;
        if ($comment === '') {
            $comment = null;
        }

        $driver = $trip->driver;
        $updated = false;

        $record = DB::transaction(function () use (
            $trip,
            $driver,
            $parent,
            $studentId,
            $rating,
            $comment,
            &$updated,
        ): TripDriverRating {
            $existing = TripDriverRating::query()
                ->where('trip_history_id', (int) $trip->id)
                ->where('user_id', (int) $parent->id)
                ->first();

            if ($existing instanceof TripDriverRating) {
                $existing->fill([
                    'driver_id' => (int) $driver->id,
                    'student_id' => $studentId,
                    'rating' => $rating,
                    'comment' => $comment,
                ])->save();
                $updated = true;

                $record = $existing->fresh();
            } else {
                $record = TripDriverRating::query()->create([
                    'trip_history_id' => (int) $trip->id,
                    'driver_id' => (int) $driver->id,
                    'user_id' => (int) $parent->id,
                    'student_id' => $studentId,
                    'rating' => $rating,
                    'comment' => $comment,
                ]);
            }

            $this->syncDriverAccountRatings($driver);

            return $record;
        });

        $driver->loadMissing('user');

        return [
            'rating' => $record,
            'driver_rating_avg' => round((float) ($driver->user?->rate ?? 0), 1),
            'driver_rating_count' => (int) ($driver->user?->votes ?? 0),
            'updated' => $updated,
        ];
    }

    public function syncDriverAccountRatings(Driver $driver): void
    {
        $driver->loadMissing('user');
        $driverUser = $driver->user;
        if ($driverUser === null) {
            return;
        }

        $aggregate = TripDriverRating::query()
            ->where('driver_id', (int) $driver->id)
            ->selectRaw('COUNT(*) as rating_count, AVG(rating) as rating_avg')
            ->first();

        $count = (int) ($aggregate->rating_count ?? 0);
        if ($count <= 0) {
            return;
        }

        $avg = round(max(0.0, min(5.0, (float) ($aggregate->rating_avg ?? 0))), 1);

        $driverUser->forceFill([
            'votes' => $count,
            'rate' => $avg,
        ])->save();
    }
}
