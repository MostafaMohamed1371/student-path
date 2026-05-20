<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ManagesDashboardScoping;
use App\Http\Controllers\Web\Concerns\ProvidesDashboardSchoolDriverFilters;
use App\Http\Requests\Web\StoreDashboardDriverRequest;
use App\Http\Requests\Web\UpdateDashboardDriverRequest;
use App\Models\Driver;
use App\Models\User;
use App\Services\Phone\PhoneNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DashboardDriverController extends Controller
{
    use ManagesDashboardScoping;
    use ProvidesDashboardSchoolDriverFilters;

    public function index(Request $request): View
    {
        $filters = $this->dashboardReportFilterContext($request, withShiftFilter: true);

        $query = Driver::query()
            ->with(['user', 'school', 'bus'])
            ->latest('id');
        $this->applyDashboardReportFilters($query, $filters, 'roster_school');
        if ((int) $filters['filterDriverId'] > 0) {
            $this->applyDashboardReportFilters($query, $filters, 'driver_roster');
        }
        $this->applyRosterShiftFilter($query, $filters);

        $drivers = $query->paginate($this->dashboardListPerPage())->withQueryString();

        return view('dashboard.drivers.index', array_merge($filters, [
            'filterAction' => route('dashboard.drivers.index'),
            'drivers' => $drivers,
        ]));
    }

    public function create(): View|RedirectResponse
    {
        $this->abortUnlessCanMutateSchoolRoster();
        $schools = $this->schoolsForRosterForm();
        if ($schools->isEmpty()) {
            return $this->redirectToSchoolCreateForAdminsOrHomeForStaff('dashboard.create_school_first');
        }

        return view('dashboard.drivers.create', compact('schools'));
    }

    public function store(StoreDashboardDriverRequest $request, PhoneNormalizer $phoneNormalizer): RedirectResponse
    {
        $this->abortUnlessCanMutateSchoolRoster();
        $validated = $this->enforceRosterSchoolIdForStaff($request->validated());
        $this->abortUnlessCanMutateSchoolRosterForSchool((int) $validated['school_id']);
        $ratingAvg = Arr::pull($validated, 'rating_avg');
        $ratingCount = Arr::pull($validated, 'rating_count');
        $patchExistingUserRatings = $request->filled('rating_avg') || $request->filled('rating_count');
        $user = $this->resolveDriverUser($validated, $phoneNormalizer, $ratingAvg, $ratingCount, false, $patchExistingUserRatings);
        $this->syncDriverProfileImage($user, $request->file('profile_image'));

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
        $this->abortUnlessCanMutateSchoolRosterForSchool((int) $driver->school_id);
        $driver->loadMissing('user');
        $schools = $this->schoolsForRosterForm();

        return view('dashboard.drivers.edit', compact('driver', 'schools'));
    }

    public function update(UpdateDashboardDriverRequest $request, Driver $driver, PhoneNormalizer $phoneNormalizer): RedirectResponse
    {
        $validated = $this->enforceRosterSchoolIdForStaff($request->validated());
        $this->abortUnlessCanMutateSchoolRosterForSchool((int) ($validated['school_id'] ?? $driver->school_id));
        $ratingAvg = Arr::pull($validated, 'rating_avg');
        $ratingCount = Arr::pull($validated, 'rating_count');
        $user = $this->resolveDriverUser($validated, $phoneNormalizer, $ratingAvg, $ratingCount, true, false);
        $this->syncDriverProfileImage($user, $request->file('profile_image'));

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
        $this->abortUnlessCanMutateSchoolRosterForSchool((int) $driver->school_id);
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

    private function syncDriverProfileImage(User $user, ?UploadedFile $file): void
    {
        if (! $file instanceof UploadedFile) {
            return;
        }

        $path = $file->store('profiles', 'public');
        if (is_string($user->image) && $user->image !== '') {
            Storage::disk('public')->delete($user->image);
        }

        $user->forceFill(['image' => $path])->save();
    }

    private function resolveDriverUser(
        array $validated,
        PhoneNormalizer $phoneNormalizer,
        mixed $ratingAvg,
        mixed $ratingCount,
        bool $syncRatingsFromForm,
        bool $patchExistingUserRatingsOnCreate,
    ): User {
        $phone = $phoneNormalizer->normalize((string) ($validated['primary_phone'] ?? ''));
        $name = trim(
            ($validated['first_name'] ?? '').' '.
            ($validated['father_name'] ?? '').' '.
            ($validated['last_name'] ?? '')
        );

        $initialRate = $this->normalizeDriverUserRatingAvg($ratingAvg);
        $initialVotes = $this->normalizeDriverUserRatingCount($ratingCount);

        $user = User::query()->firstOrCreate(
            ['phone' => $phone],
            [
                'name' => $name !== '' ? $name : null,
                'password' => config('dashboard.seed_password'),
                'is_active' => true,
                'phone_verified_at' => now(),
                'votes' => $initialVotes,
                'rate' => $initialRate,
            ]
        );

        if ($syncRatingsFromForm) {
            $user->forceFill([
                'votes' => $this->normalizeDriverUserRatingCount($ratingCount),
                'rate' => $this->normalizeDriverUserRatingAvg($ratingAvg),
            ])->save();
        } elseif (! $user->wasRecentlyCreated && $patchExistingUserRatingsOnCreate) {
            $attrs = [];
            if ($ratingAvg !== null && $ratingAvg !== '') {
                $attrs['rate'] = $this->normalizeDriverUserRatingAvg($ratingAvg);
            }
            if ($ratingCount !== null && $ratingCount !== '') {
                $attrs['votes'] = $this->normalizeDriverUserRatingCount($ratingCount);
            }
            if ($attrs !== []) {
                $user->forceFill($attrs)->save();
            }
        }

        if ($user->name === null && $name !== '') {
            $user->forceFill(['name' => $name])->save();
        }

        return $user->fresh();
    }

    private function normalizeDriverUserRatingAvg(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return round(max(0.0, min(5.0, (float) $value)), 1);
    }

    private function normalizeDriverUserRatingCount(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return max(0, min(999999, (int) $value));
    }
}
