<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ManagesDashboardScoping;
use App\Http\Controllers\Controller;
use App\Models\Bus;
use App\Models\Driver;
use Illuminate\Database\Eloquent\Builder;
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
        $drivers = $this->driversForBusForm();
        if ($drivers->isEmpty()) {
            return redirect()->route('dashboard.drivers.create')
                ->with('error', __('dashboard.create_driver_first'));
        }

        return view('dashboard.buses.create', compact('drivers'));
    }

    public function edit(Bus $bus): View
    {
        abort_unless($this->busVisible($bus), 404);
        $this->abortUnlessCanMutateSchoolRoster();

        return view('dashboard.buses.edit', [
            'bus' => $bus,
            'drivers' => $this->driversForBusForm(),
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
     * @return Collection<int, Driver>
     */
    private function driversForBusForm(): Collection
    {
        $query = Driver::query()
            ->with('school')
            ->whereNotNull('user_id')
            ->orderBy('first_name')
            ->orderBy('last_name');

        $this->constrainToScopingSchool($query);

        return $query->get();
    }
}
