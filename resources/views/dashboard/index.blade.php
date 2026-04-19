@extends('dashboard.layout')

@section('title', __('dashboard.dashboard_title'))

@section('content')
    @php($title = __('dashboard.dashboard_title'))
    @component('dashboard.partials.shell', ['title' => $title])
        <div class="stats-grid">
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
        </div>

        <section class="card" style="margin-top: 18px;">
            <h2>{{ __('dashboard.project_overview_title') }}</h2>
            <p>{{ __('dashboard.project_overview_text') }}</p>
        </section>
    @endcomponent
@endsection
