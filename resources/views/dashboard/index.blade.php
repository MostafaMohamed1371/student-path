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
        </div>

    @endcomponent
@endsection
