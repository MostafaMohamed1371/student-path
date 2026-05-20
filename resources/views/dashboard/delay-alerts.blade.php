@extends('dashboard.layout')

@section('title', __('dashboard.menu_delay_alerts'))

@section('content')
    @php($title = __('dashboard.menu_delay_alerts'))
    @component('dashboard.partials.shell', ['title' => $title])
        <p style="color: var(--text-muted); margin: 0 0 20px;">{{ __('dashboard.delay_alerts_page_intro') }}</p>

        @include('dashboard.partials.school_driver_filter')

        <section class="card">
            <div style="overflow:auto;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{ __('dashboard.table_col_trip') }}</th>
                        <th>{{ __('dashboard.driver') }}</th>
                        <th>{{ __('dashboard.table_col_user') }}</th>
                        <th>{{ __('dashboard.delay_reason_type') }}</th>
                        <th>{{ __('dashboard.delay_duration_minutes') }}</th>
                        <th>{{ __('dashboard.table_col_notes') }}</th>
                        <th>{{ __('dashboard.location') }}</th>
                        <th>{{ __('dashboard.table_col_created') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($alerts as $a)
                        @php($driverName = trim(($a->driver?->first_name ?? '').' '.($a->driver?->last_name ?? '')))
                        <tr>
                            <td>{{ $a->id }}</td>
                            <td class="mono">TRP-{{ $a->trip_history_id }}</td>
                            <td>{{ $driverName !== '' ? $driverName : '—' }}</td>
                            <td>{{ $a->user?->name ?? '—' }}</td>
                            <td><span class="mono">{{ $a->reason_type }}</span></td>
                            <td>{{ $a->delay_duration_minutes }}</td>
                            <td style="max-width: 280px;">{{ \Illuminate\Support\Str::limit($a->note ?? '—', 120) }}</td>
                            <td class="mono">{{ $a->driver_lat }}, {{ $a->driver_lng }}</td>
                            <td>{{ $a->created_at?->toDateTimeString() ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">{{ __('dashboard.table_empty') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($alerts->total() > 0)
                <div style="margin-top:16px;">{{ $alerts->links() }}</div>
            @endif
        </section>
    @endcomponent
@endsection
