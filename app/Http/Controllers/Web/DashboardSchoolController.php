<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ManagesDashboardScoping;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use App\Services\Phone\PhoneNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardSchoolController extends Controller
{
    use ManagesDashboardScoping;

    public function index(): View
    {
        $schools = School::query()
            ->withCount(['buses'])
            ->tap(fn (Builder $q) => $this->constrainToScopingSchoolRow($q))
            ->latest('id')
            ->paginate(10);

        return view('dashboard.schools.index', compact('schools'));
    }

    public function create(): View
    {
        abort_unless($this->isAdmin(), 403);
        return view('dashboard.schools.create');
    }

    public function store(Request $request, PhoneNormalizer $phoneNormalizer): RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);
        $payload = $this->validated($request);

        if ($request->hasFile('attachment')) {
            $payload['attachment'] = $request->file('attachment')->store('schools', 'public');
        }

        $school = School::query()->create($payload);
        $this->syncSchoolAdminUser($school, $phoneNormalizer);

        return redirect()->route('dashboard.schools.index')->with('success', __('dashboard.school_created'));
    }

    public function edit(School $school): View
    {
        abort_unless($this->isAdmin(), 403);
        return view('dashboard.schools.edit', compact('school'));
    }

    public function update(Request $request, School $school, PhoneNormalizer $phoneNormalizer): RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);
        $payload = $this->validated($request);

        if ($request->hasFile('attachment')) {
            $payload['attachment'] = $request->file('attachment')->store('schools', 'public');
        }

        $school->update($payload);
        $this->syncSchoolAdminUser($school->fresh(), $phoneNormalizer);

        return redirect()->route('dashboard.schools.index')->with('success', __('dashboard.school_updated'));
    }

    public function destroy(School $school): RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);
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
            'admin_phone' => ['nullable', 'string', 'size:10', 'regex:/^[1-9]\d{9}$/'],
            'authorized_person_name' => ['nullable', 'string', 'max:255'],
            'authorized_person_phone' => ['nullable', 'string', 'size:10', 'regex:/^[1-9]\d{9}$/'],
            'notes' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:4096'],
        ]);
    }

    private function syncSchoolAdminUser(School $school, PhoneNormalizer $phoneNormalizer): void
    {
        if (! $school->admin_phone || ! $phoneNormalizer->isValidIraqiMobile((string) $school->admin_phone)) {
            return;
        }

        $phone = $phoneNormalizer->normalize((string) $school->admin_phone);
        $name = $school->principal_name ?: $school->name_en ?: $school->name_ar;

        User::query()->firstOrCreate(
            ['phone' => $phone],
            [
                'name' => $name,
                'school_id' => $school->id,
                'password' => config('dashboard.seed_password'),
                'is_active' => $school->status === 'active',
                'phone_verified_at' => now(),
            ]
        );
    }

    private function isAdmin(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }
}
