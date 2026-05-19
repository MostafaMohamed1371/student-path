@extends('dashboard.layout')

@section('title', __('dashboard.dashboard_title'))

@section('content')
    @php($title = __('dashboard.dashboard_title'))
    @component('dashboard.partials.shell', ['title' => $title])
        @if(isset($driverScheduledTrips, $driverTripHomeStats, $driverOverviewData) && is_array($driverScheduledTrips) && is_array($driverTripHomeStats) && is_array($driverOverviewData))
            @if(isset($driverActiveSos) && $driverActiveSos)
                <section class="card" style="margin-bottom: 16px; border-color: #fecaca; background: #fff7f7;">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                        <div>
                            <h3 style="margin:0 0 6px; color:#b91c1c;">{{ __('dashboard.driver_active_sos_title') }}</h3>
                            <div style="color:var(--text-muted);font-size:0.9rem;">
                                <span class="mono">SOS-{{ $driverActiveSos->id }}</span>
                                <span style="margin-inline:8px;">•</span>
                                <span class="mono">TRP-{{ $driverActiveSos->trip_history_id }}</span>
                                <span style="margin-inline:8px;">•</span>
                                <span class="mono">{{ $driverActiveSos->emergency_type }}</span>
                            </div>
                            <div style="margin-top:6px;color:var(--text-muted);font-size:0.85rem;">
                                {{ __('dashboard.driver_active_sos_since') }}: {{ $driverActiveSos->triggered_at?->format('Y-m-d H:i:s') ?? $driverActiveSos->created_at?->format('Y-m-d H:i:s') ?? '—' }}
                                <span style="margin-inline:8px;">•</span>
                                <span class="mono">{{ $driverActiveSos->driver_lat }}, {{ $driverActiveSos->driver_lng }}</span>
                            </div>
                        </div>
                        <a href="{{ route('dashboard.sos_alerts') }}" class="btn-primary" style="width:auto;padding:10px 14px;text-decoration:none;background:#b91c1c;border-color:#b91c1c;">{{ __('dashboard.driver_active_sos_open') }}</a>
                    </div>
                </section>
            @endif

            <div class="stats-grid" style="margin-bottom: 20px;">
                <article class="stat-card">
                    <h3>{{ __('dashboard.driver_overview_total_available_seats') }}</h3>
                    <p>{{ $driverOverviewData['all_available_seats'] ?? 0 }}</p>
                </article>
                <article class="stat-card">
                    <h3>{{ __('dashboard.driver_overview_total_unavailable_seats') }}</h3>
                    <p>{{ $driverOverviewData['all_unavailable_seats'] ?? 0 }}</p>
                </article>
                <article class="stat-card">
                    <h3>{{ __('dashboard.driver_overview_all_students') }}</h3>
                    <p>{{ $driverOverviewData['all_students'] ?? 0 }}</p>
                </article>
            </div>

            <div class="stats-grid" style="margin-bottom: 20px;">
                <article class="stat-card">
                    <h3>{{ __('dashboard.driver_overview_available_seats_card') }}</h3>
                    <p>{{ $driverOverviewData['all_available_seats'] ?? 0 }}/{{ $driverTripHomeStats['bus_capacity'] ?? 0 }}</p>
                </article>
                <article class="stat-card">
                    <h3>{{ __('dashboard.driver_overview_pending_orders') }}</h3>
                    <p>{{ $driverPendingOrders ?? 0 }}</p>
                </article>
            </div>

            <section class="card" style="margin-bottom: 24px;">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
                    <h3 style="margin:0;">{{ __('dashboard.driver_scheduled_trips_title') }}</h3>
                    <span style="color:var(--text-muted);">{{ __('dashboard.driver_scheduled_trips_count', ['count' => count($driverScheduledTrips)]) }}</span>
                </div>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    @forelse($driverScheduledTrips as $row)
                        @php($st = $row['status'] ?? '')
                        @php($badge = match ($st) {
                            'ongoing' => __('dashboard.driver_scheduled_status_ongoing'),
                            'upcoming' => __('dashboard.driver_scheduled_status_upcoming'),
                            'completed' => __('dashboard.driver_scheduled_status_completed'),
                            'cancelled' => __('dashboard.driver_scheduled_status_cancelled'),
                            default => $st,
                        })
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 14px;border:1px solid #e2e8f0;border-radius:8px;{{ $st === 'ongoing' ? 'border-color:#0ea5e9;' : '' }}">
                            <div>
                                <strong>{{ $row['title'] ?? '' }}</strong>
                                <div style="color:var(--text-muted);font-size:0.9rem;margin-top:4px;">{{ $row['time'] ?? '' }} @if(! empty($row['type']))<span class="mono" style="margin-inline-start:8px;">{{ $row['type'] }}</span>@endif</div>
                            </div>
                            <span style="font-size:0.85rem;padding:4px 10px;border-radius:999px;background:{{ $st === 'ongoing' ? '#dcfce7' : ($st === 'upcoming' ? '#f1f5f9' : '#fef3c7') }};color:#0f172a;">{{ $badge }}</span>
                        </div>
                    @empty
                        <p style="color:var(--text-muted);margin:0;">—</p>
                    @endforelse
                </div>
            </section>
        @endif

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
                <a href="{{ route('dashboard.delay_alerts') }}" class="btn-primary" style="width: auto; padding: 10px 14px; text-decoration: none;">{{ __('dashboard.menu_delay_alerts') }}</a>
                <a href="{{ route('dashboard.sos_alerts') }}" class="btn-primary" style="width: auto; padding: 10px 14px; text-decoration: none;">{{ __('dashboard.menu_sos_alerts') }}</a>
                <a href="{{ route('dashboard.trip_finalization_reports') }}" class="btn-primary" style="width: auto; padding: 10px 14px; text-decoration: none;">{{ __('dashboard.menu_trip_finalization_reports') }}</a>
                <a href="{{ route('dashboard.trip_requests.index') }}" class="btn-primary" style="width: auto; padding: 10px 14px; text-decoration: none;">{{ __('dashboard.menu_trip_requests') }}</a>
                <a href="{{ route('dashboard.absences.index') }}" class="btn-primary" style="width: auto; padding: 10px 14px; text-decoration: none;">{{ __('dashboard.menu_absences') }}</a>
                <a href="{{ route('dashboard.support_complaints.index') }}" class="btn-primary" style="width: auto; padding: 10px 14px; text-decoration: none;">{{ __('dashboard.menu_support_complaints') }}</a>
            </div>
            <p style="margin: 14px 0 6px; color: var(--text-muted); font-size: 0.9rem;">{{ __('dashboard.overview_quick_add') }}</p>
            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                <a href="{{ route('dashboard.absences.create') }}" class="btn-muted" style="width: auto; padding: 8px 12px; text-decoration: none;">{{ __('dashboard.add_absence') }}</a>
                <a href="{{ route('dashboard.support_complaints.create') }}" class="btn-muted" style="width: auto; padding: 8px 12px; text-decoration: none;">{{ __('dashboard.add_support_complaint') }}</a>
            </div>
        </section>

    @endcomponent
@endsection
