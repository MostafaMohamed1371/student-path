<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\AppliesApiSchoolScoping;
use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Models\Driver;
use App\Models\TripRequest;
use App\Services\Trips\TripRequestAcceptanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use AppliesApiSchoolScoping;
    use FormatsParentApiResponse;

    public function __construct(
        private readonly TripRequestAcceptanceService $tripRequestAcceptanceService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'string', 'in:pending,accepted,rejected,cancelled'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $driver = $this->currentDriver($request);
        $query = TripRequest::query()
            ->with(['student.school', 'driver.bus'])
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

        $paginator = $query->paginate(min(100, max(1, (int) $request->query('per_page', 20))));

        $items = collect($paginator->items())->map(fn (TripRequest $tr): array => $this->orderCard($tr))->values()->all();

        $merge = [];
        if ($driver instanceof Driver) {
            $merge['meta'] = $this->driverOrdersMeta($driver);
        }

        $merge['pagination'] = [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];

        return $this->parentSuccess($items, 'Retrieve Orders Successfully', 200, $merge);
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
            return $this->parentError('Only pending orders can be updated.', null, 422);
        }

        $this->tripRequestAcceptanceService->applyDriverDecision($tripRequest, $validated['status']);

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
