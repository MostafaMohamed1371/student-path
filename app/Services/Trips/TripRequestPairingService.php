<?php

namespace App\Services\Trips;

use App\Enums\TripType;
use App\Models\School;
use App\Models\Student;
use App\Models\TripHistory;
use App\Models\TripRequest;
use App\Models\User;

/**
 * Pickup and return are one transport subscription: parent can send both slots,
 * and driver acceptance assigns the student to both trip legs.
 */
final class TripRequestPairingService
{
    public function __construct(
        private readonly TripRequestSlotKeyResolver $slotKeyResolver,
        private readonly PickupReturnTripPairPlanner $pairPlanner,
        private readonly TripRequestCreator $tripRequestCreator,
    ) {}

    public function isPickupSlot(?string $slotKey): bool
    {
        return in_array($slotKey, [
            TripType::MORNING_PICKUP->value,
            TripType::EVENING_PICKUP->value,
        ], true);
    }

    public function isReturnSlot(?string $slotKey): bool
    {
        return in_array($slotKey, [
            TripType::MORNING_RETURN->value,
            TripType::EVENING_RETURN->value,
        ], true);
    }

    /**
     * When parent submits a pickup-slot request, also open the paired return-slot request.
     *
     * @return array{0: TripRequest|null, 1: bool} companion row and whether it was newly created
     */
    public function createPendingReturnCompanion(
        User $user,
        Student $student,
        TripRequest $primaryRequest,
    ): array {
        $primaryRequest->loadMissing('tripHistory');
        $slotKey = $this->slotKeyResolver->slotKeyForRequest($primaryRequest);
        if (! $this->isPickupSlot($slotKey)) {
            return [null, false];
        }

        $returnSlot = TripType::pairedReturnTypeFor((string) $slotKey);
        if ($returnSlot === null || $returnSlot === '') {
            return [null, false];
        }

        $returnTripId = $this->resolveReturnTripHistoryId($primaryRequest, $student);
        $presentType = $this->presentTypeForReturnSlot((string) $slotKey);

        return $this->tripRequestCreator->createOrReturnExistingPending(
            $user,
            $student,
            $primaryRequest->driver_id !== null ? (int) $primaryRequest->driver_id : null,
            [
                'trip_history_id' => $returnTripId,
                'status' => 'pending',
                'present_type' => $presentType,
                'moving_point' => $primaryRequest->moving_point,
                'stop_point' => $primaryRequest->stop_point,
                'subscribe_price' => $primaryRequest->subscribe_price,
                'notes' => $primaryRequest->notes,
            ],
        );
    }

    public function ensureStudentOnPairedTripLeg(TripRequest $tripRequest, TripHistory $primaryTrip): ?TripHistory
    {
        $tripType = trim((string) ($primaryTrip->trip_type ?? ''));
        $pairedTrip = null;

        if (TripType::isPickup($tripType)) {
            $student = $tripRequest->student;
            $school = $student?->school;
            if (! $school instanceof School) {
                $school = School::query()->find((int) $primaryTrip->school_id);
            }
            if ($school instanceof School) {
                $pairedTrip = $this->pairPlanner->ensureReturnTripForPickup($primaryTrip, $school);
            }
        } elseif (TripType::isReturn($tripType)) {
            $pairedTrip = $this->pairPlanner->findPickupTripForReturn($primaryTrip);
        }

        if (! $pairedTrip instanceof TripHistory || $this->tripIsTerminal($pairedTrip)) {
            return null;
        }

        return $pairedTrip;
    }

    /**
     * If parent already has a pending companion request (return after pickup), accept it with the same driver decision.
     */
    public function acceptPendingCompanionRequest(TripRequest $accepted, TripHistory $primaryTrip): void
    {
        $accepted->loadMissing('tripHistory');
        $slotKey = $this->slotKeyResolver->slotKeyForRequest($accepted);
        $companionSlot = match ($slotKey) {
            TripType::MORNING_PICKUP->value => TripType::MORNING_RETURN->value,
            TripType::EVENING_PICKUP->value => TripType::EVENING_RETURN->value,
            TripType::MORNING_RETURN->value => TripType::MORNING_PICKUP->value,
            TripType::EVENING_RETURN->value => TripType::EVENING_PICKUP->value,
            default => null,
        };

        if ($companionSlot === null
            || $accepted->user_id === null
            || $accepted->student_id === null
            || $accepted->driver_id === null) {
            return;
        }

        $companion = TripRequest::query()
            ->where('user_id', (int) $accepted->user_id)
            ->where('student_id', (int) $accepted->student_id)
            ->where('driver_id', (int) $accepted->driver_id)
            ->where('status', 'pending')
            ->where('id', '!=', (int) $accepted->id)
            ->with('tripHistory')
            ->get()
            ->first(fn (TripRequest $row): bool => $this->slotKeyResolver->slotKeyForRequest($row) === $companionSlot);

        if (! $companion instanceof TripRequest) {
            return;
        }

        $companionTrip = $this->resolveCompanionTripForAcceptance($companion, $primaryTrip, $companionSlot);
        if ($companionTrip instanceof TripHistory) {
            $companion->forceFill(['trip_history_id' => $companionTrip->id])->save();
        }

        $companion->update(['status' => 'accepted']);
    }

    private function resolveCompanionTripForAcceptance(
        TripRequest $companion,
        TripHistory $primaryTrip,
        string $companionSlot,
    ): ?TripHistory {
        if ($companion->trip_history_id !== null) {
            $linked = TripHistory::query()->find((int) $companion->trip_history_id);
            if ($linked instanceof TripHistory && ! $this->tripIsTerminal($linked)) {
                return $linked;
            }
        }

        if ($this->isReturnSlot($companionSlot) && TripType::isPickup((string) ($primaryTrip->trip_type ?? ''))) {
            $student = $companion->student;
            $school = $student?->school ?? School::query()->find((int) $primaryTrip->school_id);
            if ($school instanceof School) {
                return $this->pairPlanner->ensureReturnTripForPickup($primaryTrip, $school);
            }
        }

        if ($this->isPickupSlot($companionSlot) && TripType::isReturn((string) ($primaryTrip->trip_type ?? ''))) {
            return $this->pairPlanner->findPickupTripForReturn($primaryTrip);
        }

        return null;
    }

    private function resolveReturnTripHistoryId(TripRequest $primaryRequest, Student $student): ?int
    {
        $pickupTrip = $primaryRequest->tripHistory;
        if ($pickupTrip instanceof TripHistory) {
            $returnTrip = $this->pairPlanner->findReturnTripForPickup($pickupTrip);

            return $returnTrip instanceof TripHistory ? (int) $returnTrip->id : null;
        }

        $driverId = (int) ($primaryRequest->driver_id ?? 0);
        if ($driverId <= 0) {
            return null;
        }

        $slotKey = $this->slotKeyResolver->slotKeyForRequest($primaryRequest);
        $returnType = TripType::pairedReturnTypeFor((string) $slotKey);
        if ($returnType === null || $returnType === '') {
            return null;
        }

        $returnTrip = TripHistory::query()
            ->where('school_id', (int) $student->school_id)
            ->where('driver_id', $driverId)
            ->where('trip_type', $returnType)
            ->whereNotIn('status', ['CANCELLED', 'COMPLETED', 'DONE'])
            ->whereDate('start_time', now()->toDateString())
            ->orderByDesc('start_time')
            ->orderByDesc('id')
            ->first();

        return $returnTrip instanceof TripHistory ? (int) $returnTrip->id : null;
    }

    private function presentTypeForReturnSlot(string $pickupSlot): string
    {
        return match ($pickupSlot) {
            TripType::EVENING_PICKUP->value => 'مسائي - عودة',
            default => 'صباحي - عودة',
        };
    }

    private function tripIsTerminal(TripHistory $trip): bool
    {
        return in_array(strtoupper((string) $trip->status), ['CANCELLED', 'COMPLETED', 'DONE'], true);
    }
}
