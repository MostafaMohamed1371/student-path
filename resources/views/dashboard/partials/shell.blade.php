<div class="dashboard-shell">
    <aside class="sidebar">
        <div>
            <h2 class="sidebar-title">{{ config('dashboard.brand') }}</h2>
            <p class="sidebar-subtitle">{{ __('dashboard.dashboard_title') }}</p>
        </div>

        <nav class="sidebar-nav">
            <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">{{ __('dashboard.menu_overview') }}</a>
            <a href="{{ route('dashboard.schools.index') }}" class="sidebar-link {{ request()->routeIs('dashboard.schools.*') ? 'is-active' : '' }}">{{ __('dashboard.menu_schools') }}</a>
            <a href="{{ route('dashboard.students.index') }}" class="sidebar-link {{ request()->routeIs('dashboard.students.*') ? 'is-active' : '' }}">{{ __('dashboard.menu_students') }}</a>
            <a href="{{ route('dashboard.guardians.index') }}" class="sidebar-link {{ request()->routeIs('dashboard.guardians.*') ? 'is-active' : '' }}">{{ __('dashboard.menu_guardians') }}</a>
            <a href="{{ route('dashboard.drivers.index') }}" class="sidebar-link {{ request()->routeIs('dashboard.drivers.*') ? 'is-active' : '' }}">{{ __('dashboard.menu_drivers') }}</a>
            <a href="{{ route('dashboard.trips.index') }}" class="sidebar-link {{ request()->routeIs('dashboard.trips.*') ? 'is-active' : '' }}">{{ __('dashboard.menu_trips') }}</a>
            <a href="{{ route('dashboard.trip_requests.index') }}" class="sidebar-link {{ request()->routeIs('dashboard.trip_requests.*') ? 'is-active' : '' }}">{{ __('dashboard.menu_trip_requests') }}</a>
            <a href="{{ route('dashboard.absences.index') }}" class="sidebar-link {{ request()->routeIs('dashboard.absences.*') ? 'is-active' : '' }}">{{ __('dashboard.menu_absences') }}</a>
            <a href="{{ route('dashboard.payments') }}" class="sidebar-link {{ request()->routeIs('dashboard.payments') ? 'is-active' : '' }}">{{ __('dashboard.menu_payments') }}</a>
            <a href="{{ route('dashboard.in_app_notifications') }}" class="sidebar-link {{ request()->routeIs('dashboard.in_app_notifications') ? 'is-active' : '' }}">{{ __('dashboard.menu_in_app_notifications') }}</a>
            <a href="{{ route('dashboard.delay_alerts') }}" class="sidebar-link {{ request()->routeIs('dashboard.delay_alerts') ? 'is-active' : '' }}">{{ __('dashboard.menu_delay_alerts') }}</a>
            <a href="{{ route('dashboard.sos_alerts') }}" class="sidebar-link {{ request()->routeIs('dashboard.sos_alerts') ? 'is-active' : '' }}">{{ __('dashboard.menu_sos_alerts') }}</a>
            <a href="{{ route('dashboard.trip_finalization_reports') }}" class="sidebar-link {{ request()->routeIs('dashboard.trip_finalization_reports') ? 'is-active' : '' }}">{{ __('dashboard.menu_trip_finalization_reports') }}</a>
            <a href="{{ route('dashboard.support_complaints.index') }}" class="sidebar-link {{ request()->routeIs('dashboard.support_complaints.*') ? 'is-active' : '' }}">{{ __('dashboard.menu_support_complaints') }}</a>
            @if(auth()->user()?->is_admin)
                <a href="{{ route('dashboard.users.index') }}" class="sidebar-link {{ request()->routeIs('dashboard.users.*') ? 'is-active' : '' }}">{{ __('dashboard.menu_users') }}</a>
            @endif
            <a href="{{ route('dashboard.buses.index') }}" class="sidebar-link {{ request()->routeIs('dashboard.buses.*') ? 'is-active' : '' }}">{{ __('dashboard.menu_bus') }}</a>
        </nav>

        <div class="sidebar-footer">
            <p>{{ __('dashboard.signed_in_as') }}</p>
            <p><strong>{{ auth()->user()?->name ?: '—' }}</strong></p>
            <p class="mono">{{ auth()->user()?->phone }}</p>

            <form method="post" action="{{ route('logout') }}" style="margin-top: 12px;">
                @csrf
                <button type="submit" class="btn-muted">{{ __('dashboard.logout') }}</button>
            </form>
        </div>
    </aside>

    <main class="dash-main">
        <header class="dash-header">
            <h1>{{ $title ?? __('dashboard.dashboard_title') }}</h1>
            <a href="{{ route('locale.switch', ['locale' => app()->getLocale() === 'ar' ? 'en' : 'ar']) }}" class="link">{{ __('dashboard.lang_switch') }}</a>
        </header>

        @if (session('success'))
            <div class="alert alert-success" style="margin-top: 0; margin-bottom: 16px;">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="alert" style="margin-top: 0; margin-bottom: 16px;">{{ session('error') }}</div>
        @endif

        {{ $slot }}
    </main>
</div>
