@extends('dashboard.layout')

@section('title', __('dashboard.dashboard_title'))

@section('content')
    @php($title = __('dashboard.dashboard_title'))
    @component('dashboard.partials.shell', ['title' => $title])
        <div class="stats-grid">
            <article class="stat-card">
                <h3>{{ __('dashboard.stats_schools') }}</h3>
                <p>{{ $schoolsCount }}</p>
            </article>
            <article class="stat-card">
                <h3>{{ __('dashboard.stats_drivers') }}</h3>
                <p>{{ $driversCount }}</p>
            </article>
            <article class="stat-card">
                <h3>{{ __('dashboard.stats_students') }}</h3>
                <p>{{ $studentsCount }}</p>
            </article>
            <article class="stat-card">
                <h3>{{ __('dashboard.stats_guardians') }}</h3>
                <p>{{ $guardiansCount }}</p>
            </article>
            <article class="stat-card">
                <h3>{{ __('dashboard.stats_users') }}</h3>
                <p>{{ $usersCount }}</p>
            </article>
            <article class="stat-card">
                <h3>{{ __('dashboard.stats_active_users') }}</h3>
                <p>{{ $activeUsersCount }}</p>
            </article>
            <article class="stat-card">
                <h3>{{ __('dashboard.stats_otp_records') }}</h3>
                <p>{{ $otpCount }}</p>
            </article>
            <article class="stat-card">
                <h3>{{ __('dashboard.stats_verified_users') }}</h3>
                <p>{{ $verifiedUsersCount }}</p>
            </article>
            <article class="stat-card">
                <h3>{{ __('dashboard.stats_buses') }}</h3>
                <p>{{ $busesCount }}</p>
            </article>
            <article class="stat-card">
                <h3>{{ __('dashboard.stats_assigned_buses') }}</h3>
                <p>{{ $assignedBusesCount }}</p>
            </article>
            <article class="stat-card">
                <h3>{{ __('dashboard.stats_trips') }}</h3>
                <p>{{ $tripsCount }}</p>
            </article>
            <article class="stat-card">
                <h3>{{ __('dashboard.stats_trip_requests') }}</h3>
                <p>{{ $tripRequestsCount }}</p>
            </article>
            <article class="stat-card">
                <h3>{{ __('dashboard.stats_absences') }}</h3>
                <p>{{ $absencesCount }}</p>
            </article>
            <article class="stat-card">
                <h3>{{ __('dashboard.stats_support_complaints') }}</h3>
                <p>{{ $supportComplaintsCount }}</p>
            </article>
        </div>

        <section class="card" style="margin-top: 24px;">
            <h3 style="margin: 0 0 8px;">{{ __('dashboard.payments_hub_cards_title') }}</h3>
            <p style="margin: 0 0 14px; color: var(--text-muted);">{{ __('dashboard.payments_hub_cards_text') }}</p>
            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                <a href="{{ route('dashboard.payments') }}" class="btn-primary" style="width: auto; padding: 10px 14px; text-decoration: none;">{{ __('dashboard.menu_payments') }}</a>
                <a href="{{ route('dashboard.in_app_notifications') }}" class="btn-primary" style="width: auto; padding: 10px 14px; text-decoration: none;">{{ __('dashboard.menu_in_app_notifications') }}</a>
                <a href="{{ route('dashboard.trip_requests.index') }}" class="btn-primary" style="width: auto; padding: 10px 14px; text-decoration: none;">{{ __('dashboard.menu_trip_requests') }}</a>
                <a href="{{ route('dashboard.absences.index') }}" class="btn-primary" style="width: auto; padding: 10px 14px; text-decoration: none;">{{ __('dashboard.menu_absences') }}</a>
                <a href="{{ route('dashboard.support_complaints.index') }}" class="btn-primary" style="width: auto; padding: 10px 14px; text-decoration: none;">{{ __('dashboard.menu_support_complaints') }}</a>
            </div>
            <p style="margin: 14px 0 6px; color: var(--text-muted); font-size: 0.9rem;">{{ __('dashboard.overview_quick_add') }}</p>
            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                <a href="{{ route('dashboard.trip_requests.create') }}" class="btn-muted" style="width: auto; padding: 8px 12px; text-decoration: none;">{{ __('dashboard.add_trip_request') }}</a>
                <a href="{{ route('dashboard.absences.create') }}" class="btn-muted" style="width: auto; padding: 8px 12px; text-decoration: none;">{{ __('dashboard.add_absence') }}</a>
                <a href="{{ route('dashboard.support_complaints.create') }}" class="btn-muted" style="width: auto; padding: 8px 12px; text-decoration: none;">{{ __('dashboard.add_support_complaint') }}</a>
            </div>
        </section>

    @endcomponent
@endsection
