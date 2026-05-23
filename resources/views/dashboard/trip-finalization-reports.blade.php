@extends('dashboard.layout')

@section('title', __('dashboard.menu_trip_finalization_reports'))

@section('content')
    @php($title = __('dashboard.menu_trip_finalization_reports'))
    @component('dashboard.partials.shell', ['title' => $title])
        <p style="color: var(--text-muted); margin: 0 0 20px;">{{ __('dashboard.trip_finalization_reports_page_intro') }}</p>

        @include('dashboard.partials.notification_hub_nav')

        @include('dashboard.partials.school_driver_filter')

        <section class="card">
            <div style="overflow:auto;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{ __('dashboard.school') }}</th>
                        <th>{{ __('dashboard.driver') }}</th>
                        <th>{{ __('dashboard.trip_status') }}</th>
                        <th>{{ __('dashboard.trip_final_location') }}</th>
                        <th>{{ __('dashboard.trip_final_notes') }}</th>
                        <th>{{ __('dashboard.trip_end_time') }}</th>
                        <th>{{ __('dashboard.table_col_created') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($trips as $trip)
                        @php($driverName = trim(($trip->driver?->first_name ?? '').' '.($trip->driver?->last_name ?? '')))
                        <tr>
                            <td class="mono">TRP-{{ $trip->id }}</td>
                            <td>{{ $trip->school?->name_en ?? $trip->school?->name_ar ?? '—' }}</td>
                            <td>{{ $driverName !== '' ? $driverName : '—' }}</td>
                            <td><span class="mono">{{ $trip->status }}</span></td>
                            <td class="mono">
                                @if($trip->final_lat !== null && $trip->final_lng !== null)
                                    {{ $trip->final_lat }}, {{ $trip->final_lng }}
                                @else
                                    —
                                @endif
                            </td>
                            <td style="max-width: 320px;">{{ \Illuminate\Support\Str::limit($trip->note ?? '—', 120) }}</td>
                            <td>{{ $trip->end_time?->toDateTimeString() ?? '—' }}</td>
                            <td>{{ $trip->created_at?->toDateTimeString() ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">{{ __('dashboard.table_empty') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($trips->total() > 0)
                <div style="margin-top:16px;">{{ $trips->links() }}</div>
            @endif
        </section>
    @endcomponent
@endsection
