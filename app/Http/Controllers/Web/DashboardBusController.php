<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ManagesDashboardScoping;
use App\Http\Controllers\Controller;
use App\Models\Bus;
use App\Models\Driver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardBusController extends Controller
{
    use ManagesDashboardScoping;

    public function index(): View
    {
        $buses = Bus::query()
            ->with('driver.school')
            ->tap(fn (Builder $q) => $this->constrainBusesToScopingDriverSchool($q))
            ->latest('id')
            ->paginate(12);

        return view('dashboard.buses.index', compact('buses'));
    }

    public function create(): View|RedirectResponse
    {
        $this->abortUnlessCanMutateSchoolRoster();
        $schools = $this->schoolsForRosterForm();
        if ($schools->isEmpty()) {
            return redirect()->route('dashboard.schools.index')
                ->with('error', __('dashboard.no_schools'));
        }

        $schoolId = (int) old('school_id', $schools->first()?->id ?? 0);

        return view('dashboard.buses.create', [
            'schools' => $schools,
            'drivers' => $this->driversForBusForm($schoolId > 0 ? $schoolId : null),
            'formOptionsUrl' => route('dashboard.buses.form_options'),
        ]);
    }

    public function edit(Bus $bus): View
    {
        abort_unless($this->busVisible($bus), 404);
        $this->abortUnlessCanMutateSchoolRoster();
        $bus->loadMissing('driver');

        $schools = $this->schoolsForRosterForm();
        $schoolId = (int) old('school_id', $bus->driver?->school_id ?? $schools->first()?->id ?? 0);

        return view('dashboard.buses.edit', [
            'bus' => $bus,
            'schools' => $schools,
            'drivers' => $this->driversForBusForm($schoolId > 0 ? $schoolId : null, $bus->id),
            'formOptionsUrl' => route('dashboard.buses.form_options'),
        ]);
    }

    public function formOptions(Request $request): JsonResponse
    {
        $this->abortUnlessCanMutateSchoolRoster();

        $validated = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'except_bus_id' => ['nullable', 'integer', 'exists:buses,id'],
        ]);

        $schoolId = (int) $validated['school_id'];
        $this->abortUnlessCanMutateSchoolRosterForSchool($schoolId);

        $exceptBusId = isset($validated['except_bus_id']) ? (int) $validated['except_bus_id'] : null;

        return response()->json([
            'drivers' => $this->driversForBusForm($schoolId, $exceptBusId)
                ->map(fn (Driver $driver): array => $this->driverOptionRow($driver))
                ->values()
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->abortUnlessCanMutateSchoolRoster();
        $this->normalizeBusOptionalFields($request);
        $validated = $request->validate($this->rules());
        $this->assertDriverBelongsToScopingSchool((int) $validated['driver_id']);

        $validated['annual_status'] = $request->boolean('annual_status');
        $validated['insurance'] = $request->boolean('insurance');
        if (! $this->mergeDriverUserIdInto($validated)) {
            return back()->withInput()->withErrors([
                'driver_id' => __('dashboard.driver_missing_user_account'),
            ]);
        }

        Bus::query()->create($validated);

        return redirect()->route('dashboard.buses.index')->with('success', __('dashboard.bus_created'));
    }

    public function update(Request $request, Bus $bus): RedirectResponse
    {
        abort_unless($this->busVisible($bus), 404);
        $this->abortUnlessCanMutateSchoolRoster();
        $this->normalizeBusOptionalFields($request);
        $validated = $request->validate($this->rules($bus->id));
        $this->assertDriverBelongsToScopingSchool((int) $validated['driver_id']);

        $validated['annual_status'] = $request->boolean('annual_status');
        $validated['insurance'] = $request->boolean('insurance');
        if (! $this->mergeDriverUserIdInto($validated)) {
            return back()->withInput()->withErrors([
                'driver_id' => __('dashboard.driver_missing_user_account'),
            ]);
        }

        $bus->update($validated);

        return redirect()->route('dashboard.buses.index')->with('success', __('dashboard.bus_updated'));
    }

    public function destroy(Bus $bus): RedirectResponse
    {
        abort_unless($this->busVisible($bus), 404);
        $this->abortUnlessCanMutateSchoolRoster();
        $bus->delete();

        return redirect()->route('dashboard.buses.index')->with('success', __('dashboard.bus_deleted'));
    }

    private function rules(?int $busId = null): array
    {
        return [
            'driver_id' => [
                'required',
                'integer',
                Rule::exists('drivers', 'id')->where(fn ($query) => $query->whereNotNull('user_id')),
                Rule::unique('buses', 'driver_id')->ignore($busId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'vehicle_model_year' => ['nullable', 'integer', 'min:1980', 'max:'.(date('Y') + 1)],
            'ac_status' => ['nullable', 'string', 'in:yes,no'],
            'city' => ['required', 'string', 'max:255'],
            'number' => ['required', 'string', 'max:255', 'unique:buses,number,'.($busId ?? 'NULL').',id'],
            'color' => ['nullable', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1'],
            'fuel_type' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:255'],
            'annual_status' => ['nullable', 'boolean'],
            'insurance' => ['nullable', 'boolean'],
        ];
    }

    private function normalizeBusOptionalFields(Request $request): void
    {
        $year = $request->input('vehicle_model_year');
        if ($year === '' || $year === null) {
            $request->merge(['vehicle_model_year' => null]);
        } elseif (is_numeric($year)) {
            $request->merge(['vehicle_model_year' => (int) $year]);
        }

        if ($request->input('ac_status') === '') {
            $request->merge(['ac_status' => null]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function mergeDriverUserIdInto(array &$validated): bool
    {
        $userId = Driver::query()->whereKey($validated['driver_id'])->value('user_id');
        if ($userId === null) {
            return false;
        }
        $validated['user_id'] = (int) $userId;

        return true;
    }

    private function busVisible(Bus $bus): bool
    {
        if ((bool) auth()->user()?->is_admin) {
            return true;
        }

        $sid = auth()->user()?->scopingSchoolId();
        if ($sid === null) {
            return false;
        }

        $bus->loadMissing('driver');

        return (int) ($bus->driver?->school_id ?? 0) === (int) $sid;
    }

    private function assertDriverBelongsToScopingSchool(int $driverId): void
    {
        if ((bool) auth()->user()?->is_admin) {
            return;
        }

        $sid = auth()->user()?->scopingSchoolId();
        abort_unless($sid !== null, 403);
        abort_unless(
            Driver::query()->whereKey($driverId)->where('school_id', $sid)->exists(),
            403,
        );
    }

    /**
     * Drivers eligible for a new bus at the given school (no bus yet, or current bus on edit).
     *
     * @return Collection<int, Driver>
     */
    private function driversForBusForm(?int $schoolId, ?int $exceptBusId = null): Collection
    {
        if ($schoolId === null || $schoolId <= 0) {
            return collect();
        }

        $exceptDriverId = null;
        if ($exceptBusId !== null && $exceptBusId > 0) {
            $exceptDriverId = Bus::query()->whereKey($exceptBusId)->value('driver_id');
            $exceptDriverId = $exceptDriverId !== null ? (int) $exceptDriverId : null;
        }

        $query = Driver::query()
            ->with('school')
            ->where('school_id', $schoolId)
            ->whereNotNull('user_id')
            ->where(function ($q) use ($exceptDriverId): void {
                $q->whereDoesntHave('bus');
                if ($exceptDriverId !== null) {
                    $q->orWhereKey($exceptDriverId);
                }
            })
            ->orderBy('first_name')
            ->orderBy('last_name');

        return $query->get();
    }

    /**
     * @return array{id: int, label: string}
     */
    private function driverOptionRow(Driver $driver): array
    {
        $name = trim(
            ($driver->first_name ?? '').' '
            .($driver->father_name ?? '').' '
            .($driver->last_name ?? '')
        );

        return [
            'id' => (int) $driver->id,
            'label' => $name !== '' ? $name : '#'.(int) $driver->id,
        ];
    }
}
