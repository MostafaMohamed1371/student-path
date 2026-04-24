<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ManagesDashboardScoping;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreDashboardDriverRequest;
use App\Http\Requests\Web\UpdateDashboardDriverRequest;
use App\Models\Driver;
use App\Models\School;
use App\Models\User;
use App\Services\Phone\PhoneNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DashboardDriverController extends Controller
{
    use ManagesDashboardScoping;

    public function index(): View
    {
        $drivers = Driver::query()
            ->with(['user', 'school', 'bus'])
            ->tap(fn (Builder $q) => $this->constrainToScopingSchool($q))
            ->latest('id')
            ->paginate(12);

        return view('dashboard.drivers.index', compact('drivers'));
    }

    public function create(): View|RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);
        $schools = School::query()->orderBy('name_en')->get();
        if ($schools->isEmpty()) {
            return $this->redirectToSchoolCreateForAdminsOrHomeForStaff('dashboard.create_school_first');
        }

        return view('dashboard.drivers.create', compact('schools'));
    }

    public function store(StoreDashboardDriverRequest $request, PhoneNormalizer $phoneNormalizer): RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);
        $validated = $request->validated();
        $user = $this->resolveDriverUser($validated, $phoneNormalizer);

        Driver::query()->create([
            ...$validated,
            'user_id' => $user->id,
            'id_card_image' => $this->storeFile($request->file('id_card_image'), 'drivers'),
            'license_image' => $this->storeFile($request->file('license_image'), 'drivers'),
            'non_conviction_certificate' => $this->storeFile($request->file('non_conviction_certificate'), 'drivers'),
        ]);

        return redirect()->route('dashboard.drivers.index')->with('success', __('dashboard.driver_created'));
    }

    public function edit(Driver $driver): View
    {
        abort_unless($this->isAdmin(), 403);
        $schools = School::query()->orderBy('name_en')->get();

        return view('dashboard.drivers.edit', compact('driver', 'schools'));
    }

    public function update(UpdateDashboardDriverRequest $request, Driver $driver, PhoneNormalizer $phoneNormalizer): RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);
        $validated = $request->validated();
        $user = $this->resolveDriverUser($validated, $phoneNormalizer);

        $payload = [...$validated];
        $payload['user_id'] = $user->id;
        $payload['id_card_image'] = $this->replaceFile($request->file('id_card_image'), $driver->id_card_image, 'drivers');
        $payload['license_image'] = $this->replaceFile($request->file('license_image'), $driver->license_image, 'drivers');
        $payload['non_conviction_certificate'] = $this->replaceFile($request->file('non_conviction_certificate'), $driver->non_conviction_certificate, 'drivers');

        $driver->update($payload);

        return redirect()->route('dashboard.drivers.index')->with('success', __('dashboard.driver_updated'));
    }

    public function destroy(Driver $driver): RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);
        foreach (['id_card_image', 'license_image', 'non_conviction_certificate'] as $fileField) {
            if ($driver->{$fileField}) {
                Storage::disk('public')->delete((string) $driver->{$fileField});
            }
        }

        $driver->delete();

        return redirect()->route('dashboard.drivers.index')->with('success', __('dashboard.driver_deleted'));
    }

    private function storeFile(?UploadedFile $file, string $folder): ?string
    {
        return $file?->store($folder, 'public');
    }

    private function replaceFile(?UploadedFile $file, ?string $existing, string $folder): ?string
    {
        if (! $file) {
            return $existing;
        }

        if ($existing) {
            Storage::disk('public')->delete($existing);
        }

        return $file->store($folder, 'public');
    }

    private function resolveDriverUser(array $validated, PhoneNormalizer $phoneNormalizer): User
    {
        $phone = $phoneNormalizer->normalize((string) ($validated['primary_phone'] ?? ''));
        $name = trim(
            ($validated['first_name'] ?? '').' '.
            ($validated['father_name'] ?? '').' '.
            ($validated['last_name'] ?? '')
        );

        $user = User::query()->firstOrCreate(
            ['phone' => $phone],
            [
                'name' => $name !== '' ? $name : null,
                'password' => config('dashboard.seed_password'),
                'is_active' => true,
                'phone_verified_at' => now(),
            ]
        );

        if ($user->name === null && $name !== '') {
            $user->forceFill(['name' => $name])->save();
        }

        return $user;
    }

    private function isAdmin(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }
}
