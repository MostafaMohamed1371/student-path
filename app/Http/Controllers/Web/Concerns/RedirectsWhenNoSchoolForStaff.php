<?php

namespace App\Http\Controllers\Web\Concerns;

use Illuminate\Http\RedirectResponse;

trait RedirectsWhenNoSchoolForStaff
{
    /**
     * When a school is required to show a "create" form but none exist: send admins to
     * create a school, and staff to the dashboard (never to school create — that route is admin-only and would 403).
     */
    protected function redirectToSchoolCreateForAdminsOrHomeForStaff(string $adminMessageKey): RedirectResponse
    {
        if ((bool) auth()->user()?->is_admin) {
            return redirect()->route('dashboard.schools.create')
                ->with('error', __($adminMessageKey));
        }

        return redirect()->route('dashboard')
            ->with('error', __('dashboard.school_scope_required_for_staff'));
    }
}
