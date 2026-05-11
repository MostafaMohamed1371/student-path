@extends('dashboard.layout')

@section('title', __('dashboard.menu_sos_alerts'))

@section('content')
    @php($title = __('dashboard.menu_sos_alerts'))
    @component('dashboard.partials.shell', ['title' => $title])
        <p style="color: var(--text-muted); margin: 0 0 20px;">{{ __('dashboard.sos_alerts_page_intro') }}</p>

        <section class="card">
            <div style="overflow:auto;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{ __('dashboard.table_col_trip') }}</th>
                        <th>{{ __('dashboard.driver') }}</th>
                        <th>{{ __('dashboard.table_col_user') }}</th>
                        <th>{{ __('dashboard.table_col_type') }}</th>
                        <th>{{ __('dashboard.table_col_status') }}</th>
                        <th>{{ __('dashboard.location') }}</th>
                        <th>{{ __('dashboard.table_col_notes') }}</th>
                        <th>{{ __('dashboard.table_col_created') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($alerts as $a)
                        @php($driverName = trim(($a->driver?->first_name ?? '').' '.($a->driver?->last_name ?? '')))
                        <tr>
                            <td class="mono">SOS-{{ $a->id }}</td>
                            <td class="mono">TRP-{{ $a->trip_history_id }}</td>
                            <td>{{ $driverName !== '' ? $driverName : '—' }}</td>
                            <td>{{ $a->user?->name ?? '—' }}</td>
                            <td><span class="mono">{{ $a->emergency_type }}</span></td>
                            <td><span class="mono">{{ $a->status }}</span></td>
                            <td class="mono">
                                {{ $a->driver_lat }}, {{ $a->driver_lng }}
                                @if($a->final_lat !== null && $a->final_lng !== null)
                                    <br>
                                    <small>Final: {{ $a->final_lat }}, {{ $a->final_lng }}</small>
                                @endif
                            </td>
                            <td style="max-width: 280px;">{{ \Illuminate\Support\Str::limit($a->stop_reason ?? '—', 120) }}</td>
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
            @if ($alerts->hasPages())
                <div style="margin-top: 12px;">{{ $alerts->withQueryString()->links() }}</div>
            @endif
        </section>
    @endcomponent
@endsection
