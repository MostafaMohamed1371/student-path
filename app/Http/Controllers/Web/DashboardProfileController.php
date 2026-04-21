<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\UpdateMyProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardProfileController extends Controller
{
    public function edit(): View
    {
        return view('dashboard.profile.edit', [
            'user' => auth()->user(),
        ]);
    }

    public function update(UpdateMyProfileRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('profiles', 'public');
        } else {
            unset($validated['image']);
        }

        $request->user()->update($validated);

        return redirect()->route('dashboard.profile.edit')->with('success', __('dashboard.profile_updated'));
    }
}
