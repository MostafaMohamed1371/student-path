<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\AppliesApiSchoolScoping;
use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Models\Driver;
use App\Models\TripRequest;
use App\Services\Trips\TripRequestAcceptanceService;
use App\Services\Trips\TripRequestConflictGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    use AppliesApiSchoolScoping;
    use FormatsParentApiResponse;

    public function __construct(
        private readonly TripRequestAcceptanceService $tripRequestAcceptanceService,
        private readonly TripRequestConflictGuard $tripRequestConflictGuard,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'string', 'in:pending,accepted,rejected,cancelled'],
        ]);

        $driver = $this->currentDriver($request);
        $query = TripRequest::query()
            ->with(['user.guardian', 'student.guardian', 'student.school', 'driver.bus'])
            ->latest('trip_requests.id');

        if ($driver instanceof Driver) {
            $query->where('driver_id', $driver->id);
        } elseif ($this->isApiAdmin($request->user())) {
            // Same as trip-requests list: admins without driver role see only their own rows.
            $query->where('user_id', $request->user()->id);
        } else {
            $query->where('user_id', $request->user()->id);
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        $orders = $query
            ->get()
            ->map(fn (TripRequest $tr): array => $this->orderCard($tr))
            ->values()
            ->all();

        if ($driver instanceof Driver) {
            $data = array_merge($this->driverOrdersMeta($driver), ['orders' => $orders]);
        } else {
            $data = $orders;
        }

        return $this->parentSuccess($data, 'Retrieve Orders Successfully');
    }

    /**
     * Accept or reject an order (trip request). Intended for the assigned driver account.
     *
     * @param  string  $order  Numeric id or `order_{id}`
     */
    public function update(Request $request, string $order): JsonResponse
    {
        $tripRequest = $this->resolveOrderRouteParameter($order);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:accepted,rejected'],
            'order_id' => ['nullable', 'string'],
        ]);

        $driver = $this->currentDriver($request);
        if (! $driver instanceof Driver) {
            return $this->parentError('Only drivers can accept or reject orders.', null, 403);
        }

        if ((int) $tripRequest->driver_id !== (int) $driver->id) {
            return $this->parentError('forbidden', null, 403);
        }

        if ($tripRequest->status !== 'pending') {
            if ($validated['status'] === 'accepted'
                && $this->tripRequestConflictGuard->slotTakenByAnotherDriver($tripRequest)) {
                return $this->parentError(
                    __('dashboard.trip_request_slot_taken_by_another_driver'),
                    ['status' => [__('dashboard.trip_request_slot_taken_by_another_driver')]],
                    422,
                );
            }

            return $this->parentError('Only pending orders can be updated.', null, 422);
        }

        try {
            $this->tripRequestAcceptanceService->applyDriverDecision($tripRequest, $validated['status']);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first()
                ?: 'Only pending orders can be updated.';

            return $this->parentError($message, $e->errors(), 422);
        }

        $msg = $validated['status'] === 'accepted'
            ? 'Order accepted successfully'
            : 'Order rejected successfully';

        return $this->parentSuccess([
            'id' => (int) $tripRequest->id,
            'status' => $validated['status'],
        ], $msg);
    }

    /**
     * @return array<string, mixed>
     */
    private function orderCard(TripRequest $tr): array
    {
        $student = $tr->student;
        $school = $student?->school;

        $subscribe = $tr->subscribe_price;
        if ($subscribe === null && $tr->driver?->monthly_subscription_price !== null) {
            $subscribe = (float) $tr->driver->monthly_subscription_price;
        }

        $studentResource = $student
            ? (new StudentResource($student))->toArray(request())
            : [];

        $image = $studentResource['profilePhoto'] ?? null;
        if (is_string($image) && $image !== '' && ! str_starts_with($image, 'http')) {
            $image = url($image);
        }

        return [
            'id' => (int) $tr->id,
            'status' => $tr->status,
            'parentName' => $tr->parentDisplayName(),
            'parentPhone' => $tr->parentDisplayPhone(),
            'driverName' => $tr->driver ? $tr->driverDisplayName() : null,
            'student' => [
                'id' => $student ? (string) $student->id : '',
                'name' => $student?->full_name ?? '',
                'image' => $image,
                'movingPoint' => $tr->moving_point ?? '',
                'grade' => $student?->grade ?? '',
                'schoolName' => $school?->name_ar ?? $school?->name_en ?? '',
                'stopPoint' => $tr->stop_point ?? ($school?->name_ar ?? $school?->name_en ?? ''),
                'subscribePrice' => $subscribe !== null ? round((float) $subscribe, 2) : null,
                'presentType' => $tr->present_type ?? '',
            ],
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function driverOrdersMeta(Driver $driver): array
    {
        $driver->loadMissing('bus');

        $pendingCount = TripRequest::query()
            ->where('driver_id', $driver->id)
            ->where('status', 'pending')
            ->count();

        $capacity = (int) ($driver->bus?->capacity ?? 0);
        $usedSeats = TripRequest::query()
            ->where('driver_id', $driver->id)
            ->whereIn('status', ['pending', 'accepted'])
            ->count();

        $available = $capacity > 0 ? max(0, $capacity - $usedSeats) : null;

        return [
            'pending_count' => $pendingCount,
            'available_seats' => $available,
            'total_seats' => $capacity > 0 ? $capacity : null,
            'available_seats_label' => $capacity > 0 && $available !== null
                ? $available.'/'.$capacity
                : null,
        ];
    }

    private function resolveOrderRouteParameter(string $order): TripRequest
    {
        if (! preg_match('/^(?:order_)?(\d+)$/', $order, $m)) {
            abort(404);
        }

        return TripRequest::query()->findOrFail((int) $m[1]);
    }

    private function currentDriver(Request $request): ?Driver
    {
        return Driver::query()->where('user_id', $request->user()->id)->first();
    }
}
