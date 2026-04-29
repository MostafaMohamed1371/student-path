<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ManagesDashboardScoping;
use App\Models\School;
use App\Models\TripHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardTripController extends Controller
{
    use ManagesDashboardScoping;

    public function index(): View
    {
        $trips = TripHistory::query()
            ->with('school')
            ->tap(fn (Builder $q) => $this->constrainToScopingSchool($q))
            ->orderByDesc('start_time')
            ->paginate(12);

        return view('dashboard.trips.index', compact('trips'));
    }

    public function create(): View|RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);
        $schools = School::query()->orderBy('name_en')->get();
        if ($schools->isEmpty()) {
            return $this->redirectToSchoolCreateForAdminsOrHomeForStaff('dashboard.create_school_first');
        }

        return view('dashboard.trips.create', compact('schools'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);
        $trip = TripHistory::query()->create($request->validate($this->rules()));

        return redirect()->route('dashboard.trips.index')
            ->with('success', __('dashboard.trip_created'));
    }

    public function edit(TripHistory $trip): View
    {
        abort_unless($this->isAdmin(), 403);
        $schools = School::query()->orderBy('name_en')->get();

        return view('dashboard.trips.edit', compact('trip', 'schools'));
    }

    public function update(Request $request, TripHistory $trip): RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);
        $trip->update($request->validate($this->rules(true)));

        return redirect()->route('dashboard.trips.index')
            ->with('success', __('dashboard.trip_updated'));
    }

    public function destroy(TripHistory $trip): RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);
        $trip->delete();

        return redirect()->route('dashboard.trips.index')
            ->with('success', __('dashboard.trip_deleted'));
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
        ];
    }

    private function isAdmin(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }
}

