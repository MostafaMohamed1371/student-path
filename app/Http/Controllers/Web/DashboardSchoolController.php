<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardSchoolController extends Controller
{
    public function index(): View
    {
        $schools = School::query()
            ->withCount(['buses'])
            ->latest('id')
            ->paginate(10);

        return view('dashboard.schools.index', compact('schools'));
    }

    public function create(): View
    {
        return view('dashboard.schools.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->validated($request);

        if ($request->hasFile('attachment')) {
            $payload['attachment'] = $request->file('attachment')->store('schools', 'public');
        }

        School::query()->create($payload);

        return redirect()->route('dashboard.schools.index')->with('success', __('dashboard.school_created'));
    }

    public function edit(School $school): View
    {
        return view('dashboard.schools.edit', compact('school'));
    }

    public function update(Request $request, School $school): RedirectResponse
    {
        $payload = $this->validated($request);

        if ($request->hasFile('attachment')) {
            $payload['attachment'] = $request->file('attachment')->store('schools', 'public');
        }

        $school->update($payload);

        return redirect()->route('dashboard.schools.index')->with('success', __('dashboard.school_updated'));
    }

    public function destroy(School $school): RedirectResponse
    {
        $school->delete();

        return redirect()->route('dashboard.schools.index')->with('success', __('dashboard.school_deleted'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'district' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['required', 'string', 'max:32'],
            'principal_name' => ['nullable', 'string', 'max:255'],
            'admin_phone' => ['nullable', 'string', 'max:20'],
            'authorized_person_name' => ['nullable', 'string', 'max:255'],
            'authorized_person_phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:4096'],
        ]);
    }
}
