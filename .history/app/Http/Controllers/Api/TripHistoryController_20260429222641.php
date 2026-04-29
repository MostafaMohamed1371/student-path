<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AppliesApiSchoolScoping;
use App\Http\Controllers\Controller;
use App\Models\TripHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class TripHistoryController extends Controller
{
    use AppliesApiSchoolScoping;

    public function history(Request $request): JsonResponse
    {
        $validator = Validator::make(
            $request->query(),
            [
                'period' => ['nullable', 'in:MORNING,EVENING'],
                'page' => ['nullable', 'integer', 'min:1'],
                'pageSize' => ['nullable', 'integer', 'min:1', 'max:100'],
                'from' => ['nullable', 'date_format:Y-m-d'],
                'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            ],
            [
                'period.in' => 'Invalid period value. Expected MORNING or EVENING.',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 400,
                'message' => $validator->errors()->first(),
                'data' => null,
            ], 400);
        }

        $page = (int) ($request->query('page', 1));
        $pageSize = (int) ($request->query('pageSize', 20));
        $period = (string) $request->query('period', '');
        $from = $request->query('from');
        $to = $request->query('to');

        $query = TripHistory::query();
        $this->applyApiScopeBySchoolIdColumn($query, $request->user());

        if ($period === 'MORNING') {
            $query->whereTime('start_time', '<', '12:00:00');
        } elseif ($period === 'EVENING') {
            $query->whereTime('start_time', '>=', '12:00:00');
        }

        if ($from) {
            $query->whereDate('start_time', '>=', (string) $from);
        }
        if ($to) {
            $query->whereDate('start_time', '<=', (string) $to);
        }

        $rows = $query
            ->with('school:id,address')
            ->orderByDesc('start_time')
            ->forPage($page, $pageSize)
            ->get();

        $data = $rows->map(function (TripHistory $trip): array {
            $start = $trip->start_time instanceof Carbon ? $trip->start_time : Carbon::parse((string) $trip->start_time);
            $end = $trip->end_time ? ($trip->end_time instanceof Carbon ? $trip->end_time : Carbon::parse((string) $trip->end_time)) : null;
            $preview = is_array($trip->students_preview) ? $trip->students_preview : [];

            return [
                'id' => (string) $trip->id,
                'period' => ((int) $start->format('H') < 12) ? 'MORNING' : 'EVENING',
                'date' => $start->toDateString(),
                'bus_number' => (string) ($trip->bus_number ?? ''),
                'route_title' => (string) ($trip->route_title ?? ''),
                'location' => (string) ($trip->school?->address ?? $trip->location ?? ''),
                'students_count' => (int) $trip->students_count,
                'distance_km' => (float) $trip->distance_km,
                'start_time' => $start->toIso8601String(),
                'end_time' => $end?->toIso8601String() ?? '',
                'status' => (string) ($trip->status ?? ''),
                'note' => (string) ($trip->note ?? ''),
                'students_preview' => array_values(array_map(
                    static fn ($item): array => [
                        'id' => (string) ($item['id'] ?? ''),
                        'name' => (string) ($item['name'] ?? ''),
                        'stop_name' => (string) ($item['stop_name'] ?? ''),
                        'grade_label' => (string) ($item['grade_label'] ?? ''),
                    ],
                    $preview
                )),
            ];
        })->values()->all();

        return response()->json([
            'status' => 200,
            'message' => $data === [] ? 'No trips found' : 'Success',
            'data' => $data,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = TripHistory::query();
        $this->applyApiScopeBySchoolIdColumn($query, $request->user());
        $rows = $query->latest('id')->get();

        return response()->json([
            'success' => true,
            'data' => $rows,
            'msg' => 'success',
        ]);
    }

    public function show(Request $request, TripHistory $trip): JsonResponse
    {
        if ($resp = $this->ensureApiTargetsOwnSchoolOrAdmin($request->user(), $trip->school_id)) {
            return $resp;
        }

        return response()->json([
            'success' => true,
            'data' => $trip,
            'msg' => 'success',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($resp = $this->ensureApiAdminForMutations($request->user())) {
            return $resp;
        }
        $payload = $request->validate($this->rules());
        $trip = TripHistory::query()->create($payload);

        return response()->json([
            'success' => true,
            'data' => $trip,
            'msg' => 'trip created successfully',
        ], 201);
    }

    public function update(Request $request, TripHistory $trip): JsonResponse
    {
        if ($resp = $this->ensureApiAdminForMutations($request->user())) {
            return $resp;
        }
        $payload = $request->validate($this->rules(true));
        $trip->update($payload);

        return response()->json([
            'success' => true,
            'data' => $trip->fresh(),
            'msg' => 'trip updated successfully',
        ]);
    }

    public function destroy(Request $request, TripHistory $trip): JsonResponse
    {
        if ($resp = $this->ensureApiAdminForMutations($request->user())) {
            return $resp;
        }
        $trip->delete();

        return response()->json([
            'success' => true,
            'data' => (object) [],
            'msg' => 'trip deleted successfully',
        ]);
    }

    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'school_id' => [$required, 'integer', 'exists:schools,id'],
            'bus_number' => [$required, 'string', 'max:64'],
            'route_title' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'students_count' => [$required, 'integer', 'min:0'],
            'distance_km' => [$required, 'numeric', 'min:0'],
            'start_time' => [$required, 'date'],
            'end_time' => ['nullable', 'date', 'after_or_equal:start_time'],
            'status' => [$required, 'in:PRESENT,ABSENT,CANCELLED'],
            'note' => ['nullable', 'string'],
            'students_preview' => ['nullable', 'array'],
            'students_preview.*.id' => ['nullable', 'string', 'max:64'],
            'students_preview.*.name' => ['nullable', 'string', 'max:255'],
            'students_preview.*.stop_name' => ['nullable', 'string', 'max:255'],
            'students_preview.*.grade_label' => ['nullable', 'string', 'max:255'],
        ];
    }
}

