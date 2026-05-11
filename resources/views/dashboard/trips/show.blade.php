@extends('dashboard.layout')

@section('title', __('dashboard.menu_trips'))

@section('content')
    @php($title = __('dashboard.menu_trips').' #'.$trip->id)
    @component('dashboard.partials.shell', ['title' => $title])
        <p style="color: var(--text-muted); margin: 0 0 16px;">{{ __('dashboard.trip_show_intro') }}</p>

        @if(! empty($tripDetail))
            <section class="card" style="margin-bottom: 16px;">
                <h3 style="margin: 0 0 12px;">{{ __('dashboard.trip_detail_preview') }}</h3>
                <p style="margin: 0 0 8px;"><strong>{{ __('dashboard.route_title') }}:</strong> {{ $tripDetail['title'] }}</p>
                <p style="margin: 0 0 8px;"><strong>{{ __('dashboard.table_col_student') }}:</strong> {{ $tripDetail['students_number'] }}</p>
                <p style="margin: 0 0 8px;"><strong>{{ __('dashboard.distance_km') }}:</strong> {{ $tripDetail['distance_in_km'] }}</p>
                <p style="margin: 0 0 8px;"><strong>{{ __('dashboard.trip_start_time') }}:</strong> {{ $tripDetail['estimated_start_time'] }}</p>
                <p style="margin: 0 0 8px;"><strong>{{ __('dashboard.location') }}:</strong> {{ $tripDetail['location'] }}</p>
                <p style="margin: 0;"><strong>{{ __('dashboard.trip_detail_date_label') }}:</strong> <span class="mono">{{ $tripDetail['date_label'] }}</span></p>
            </section>
        @endif

        <section class="card" style="margin-bottom: 16px;">
            <p style="margin: 0 0 8px;"><strong>{{ __('dashboard.school') }}:</strong> {{ $trip->school?->name_en ?? '—' }}</p>
            <p style="margin: 0 0 8px;"><strong>{{ __('dashboard.trip_field_driver') }}:</strong>
                @if($trip->driver)
                    {{ trim(($trip->driver->first_name ?? '').' '.($trip->driver->last_name ?? '')) }}
                @else
                    —
                @endif
            </p>
            <p style="margin: 0 0 8px;"><strong>{{ __('dashboard.trip_field_type') }}:</strong> {{ $trip->trip_type ?: '—' }}</p>
            <p style="margin: 0 0 8px;"><strong>{{ __('dashboard.bus_number') }}:</strong> {{ $trip->bus_number ?: '—' }}</p>
            <p style="margin: 0 0 8px;"><strong>{{ __('dashboard.route_title') }}:</strong> {{ $trip->route_title ?: '—' }}</p>
            <p style="margin: 0 0 8px;"><strong>{{ __('dashboard.trip_start_time') }}:</strong> {{ $trip->start_time }}</p>
            <p style="margin: 0 0 8px;"><strong>{{ __('dashboard.trip_end_time') }}:</strong> {{ $trip->end_time ?? '—' }}</p>
            <p style="margin: 0 0 8px;"><strong>{{ __('dashboard.trip_final_location') }}:</strong>
                @if($trip->final_lat !== null && $trip->final_lng !== null)
                    <span class="mono">{{ $trip->final_lat }}, {{ $trip->final_lng }}</span>
                @else
                    —
                @endif
            </p>
            <p style="margin: 0 0 8px;"><strong>{{ __('dashboard.trip_final_notes') }}:</strong> {{ $trip->note ?: '—' }}</p>
            <p style="margin: 0;"><strong>{{ __('dashboard.trip_status') }}:</strong> {{ $trip->status }}</p>
        </section>

        <section class="card">
            <h3 style="margin: 0 0 12px;">{{ __('dashboard.trip_students_select') }}</h3>
            <div style="overflow:auto;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('dashboard.table_col_student') }}</th>
                        <th>{{ __('dashboard.grade') }}</th>
                        <th>{{ __('dashboard.trip_roster_status') }}</th>
                        @if(! empty($tripDetail))
                            <th>{{ __('dashboard.trip_detail_pickup') }}</th>
                        @endif
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($trip->tripHistoryStudents as $idx => $ths)
                        <tr>
                            <td>{{ $ths->sort_order + 1 }}</td>
                            <td>{{ $ths->student?->full_name ?? '—' }}</td>
                            <td>{{ $ths->student?->grade ?? '—' }}</td>
                            <td><span class="mono">{{ $ths->status }}</span></td>
                            @if(! empty($tripDetail))
                                <td>{{ $tripDetail['students'][$idx]['pickup_point'] ?? '—' }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ ! empty($tripDetail) ? 5 : 4 }}">{{ __('dashboard.no_trips') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <p style="margin-top: 16px;">
            @if(auth()->user()?->is_admin)
                <a href="{{ route('dashboard.trips.edit', $trip) }}" class="btn-primary" style="width:auto;padding:10px 14px;text-decoration:none;">{{ __('dashboard.edit') }}</a>
            @endif
            <a href="{{ route('dashboard.trips.index') }}" class="link" style="margin-inline-start:12px;">{{ __('dashboard.cancel') }}</a>
        </p>
    @endcomponent
@endsection
