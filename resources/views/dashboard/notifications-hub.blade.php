@extends('dashboard.layout')

@section('title', __('dashboard.menu_notifications_hub'))

@section('content')
    @php($title = __('dashboard.menu_notifications_hub'))
    @component('dashboard.partials.shell', ['title' => $title])
        <p style="color: var(--text-muted); margin: 0 0 20px;">{{ __('dashboard.notifications_hub_intro') }}</p>

        @include('dashboard.partials.notification_hub_nav')

        @include('dashboard.partials.school_driver_filter')

        @include('dashboard.partials.notifications_hub_quick_links')

        <div class="stats-grid" style="margin-bottom: 24px;">
            <article class="stat-card">
                <h3>{{ __('dashboard.notifications_stat_in_app_7d') }}</h3>
                <p>{{ $stats['in_app_7d'] }}</p>
            </article>
            <article class="stat-card">
                <h3>{{ __('dashboard.notifications_stat_unread') }}</h3>
                <p>{{ $stats['in_app_unread'] }}</p>
            </article>
            <article class="stat-card">
                <h3>{{ __('dashboard.notifications_stat_fcm_tokens') }}</h3>
                <p>{{ $stats['fcm_tokens'] }}</p>
            </article>
            <article class="stat-card">
                <h3>{{ __('dashboard.notifications_stat_delay_7d') }}</h3>
                <p>{{ $stats['delay_7d'] }}</p>
            </article>
            <article class="stat-card">
                <h3>{{ __('dashboard.notifications_stat_sos_active') }}</h3>
                <p>{{ $stats['sos_active'] }}</p>
            </article>
            <article class="stat-card">
                <h3>{{ __('dashboard.notifications_stat_trips_completed_7d') }}</h3>
                <p>{{ $stats['trips_completed_7d'] }}</p>
            </article>
        </div>

        <section class="card" style="margin-bottom: 24px;">
            <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px;">
                <h3 style="margin:0;">{{ __('dashboard.notifications_hub_recent_title') }}</h3>
                <a href="{{ route('dashboard.in_app_notifications', request()->only(['school_id', 'driver_id'])) }}"
                   class="btn-primary"
                   style="width:auto;padding:8px 14px;text-decoration:none;">
                    {{ __('dashboard.notifications_hub_view_all_in_app') }}
                </a>
            </div>
            <div style="overflow:auto;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{ __('dashboard.table_col_user') }}</th>
                        <th>{{ __('dashboard.table_col_notification_type') }}</th>
                        <th>{{ __('dashboard.table_col_trip') }}</th>
                        <th>{{ __('dashboard.table_col_title') }}</th>
                        <th>{{ __('dashboard.table_col_read_status') }}</th>
                        <th>{{ __('dashboard.table_col_created') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($recentNotifications as $n)
                        @php($u = $n->user)
                        @php($dataType = \App\Support\Dashboard\InAppNotificationPresenter::dataType($n))
                        @php($tripRef = \App\Support\Dashboard\InAppNotificationPresenter::tripReference($n))
                        <tr>
                            <td>
                                <a href="{{ route('dashboard.in_app_notifications.show', array_merge(['notification' => $n->id], request()->only(['school_id', 'driver_id']))) }}" class="link mono">
                                    {{ $n->id }}
                                </a>
                            </td>
                            <td>{{ $u?->name ?? '—' }}</td>
                            <td>
                                @if($dataType)
                                    <span class="mono" style="font-size:12px;">{{ \App\Support\Dashboard\InAppNotificationPresenter::typeLabel($dataType) }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="mono">{{ $tripRef ?? '—' }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($n->title ?? '', 60) }}</td>
                            <td>
                                @if($n->read_at)
                                    <span style="color:var(--text-muted);">{{ __('dashboard.notification_read') }}</span>
                                @else
                                    <span style="color:var(--accent, #2563eb);font-weight:600;">{{ __('dashboard.notification_unread') }}</span>
                                @endif
                            </td>
                            <td>{{ $n->created_at?->toDateTimeString() ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">{{ __('dashboard.table_empty') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h3 style="margin: 0 0 12px;">{{ __('dashboard.notifications_hub_by_type_title') }}</h3>
            <div style="overflow:auto;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>{{ __('dashboard.table_col_notification_type') }}</th>
                        <th>{{ __('dashboard.notifications_hub_count_7d') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($typeCounts7d as $row)
                        <tr>
                            <td>{{ \App\Support\Dashboard\InAppNotificationPresenter::typeLabel($row['type']) }}</td>
                            <td>{{ $row['count'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2">{{ __('dashboard.table_empty') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endcomponent
@endsection
