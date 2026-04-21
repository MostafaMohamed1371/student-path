<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreDashboardUserRequest;
use App\Http\Requests\Web\UpdateDashboardUserRequest;
use App\Models\User;
use App\Services\Phone\PhoneNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DashboardUserController extends Controller
{
    public function index(): View
    {
        $users = User::query()->latest('id')->paginate(12);

        return view('dashboard.users.index', compact('users'));
    }

    public function create(): View
    {
        return view('dashboard.users.create');
    }

    public function store(StoreDashboardUserRequest $request, PhoneNormalizer $phoneNormalizer): RedirectResponse
    {
        $validated = $request->validated();
        $imagePath = $request->hasFile('image')
            ? $request->file('image')->store('profiles', 'public')
            : null;

        User::query()->create([
            'name' => $validated['name'] ?? null,
            'image' => $imagePath,
            'phone' => $phoneNormalizer->normalize($validated['phone']),
            'city' => $validated['city'] ?? null,
            'licence_number' => $validated['licence_number'] ?? null,
            'votes' => $validated['votes'],
            'rate' => $validated['rate'],
            'is_verified' => $validated['is_verified'] ?? false,
            'password' => $validated['password'],
            'is_active' => $validated['is_active'] ?? true,
            'phone_verified_at' => now(),
        ]);

        return redirect()->route('dashboard.users.index')->with('success', __('dashboard.user_created'));
    }

    public function edit(User $user): View
    {
        return view('dashboard.users.edit', compact('user'));
    }

    public function update(UpdateDashboardUserRequest $request, User $user, PhoneNormalizer $phoneNormalizer): RedirectResponse
    {
        $validated = $request->validated();

        $payload = [
            'name' => $validated['name'] ?? null,
            'phone' => $phoneNormalizer->normalize($validated['phone']),
            'city' => $validated['city'] ?? null,
            'licence_number' => $validated['licence_number'] ?? null,
            'votes' => $validated['votes'],
            'rate' => $validated['rate'],
            'is_verified' => $validated['is_verified'] ?? false,
            'is_active' => $validated['is_active'] ?? false,
        ];

        if ($request->hasFile('image')) {
            $payload['image'] = $request->file('image')->store('profiles', 'public');

            if ($user->image) {
                Storage::disk('public')->delete($user->image);
            }
        }

        if (! empty($validated['password'])) {
            $payload['password'] = $validated['password'];
        }

        $user->update($payload);

        return redirect()->route('dashboard.users.index')->with('success', __('dashboard.user_updated'));
    }

    public function destroy(User $user): RedirectResponse
    {
        if (auth()->id() === $user->id) {
            return redirect()->route('dashboard.users.index')->with('error', __('dashboard.cannot_delete_self'));
        }

        $user->delete();

        return redirect()->route('dashboard.users.index')->with('success', __('dashboard.user_deleted'));
    }
}
