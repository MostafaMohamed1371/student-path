@extends('dashboard.layout')

@section('title', __('dashboard.edit_trip'))

@section('content')
    @php
        $title = __('dashboard.edit_trip');
    @endphp
    @component('dashboard.partials.shell', ['title' => $title])
        <section class="card">
            <form method="post" action="{{ route('dashboard.trips.update', $trip) }}" class="form-grid" id="trip_form">
                @csrf
                @method('put')

                <label>
                    <span>{{ __('dashboard.school') }}</span>
                    <select id="trip_form_school_id" name="school_id" required>
                        @foreach($schools as $school)
                            <option value="{{ $school->id }}" @selected(old('school_id', $trip->school_id) == $school->id)>{{ $school->name_en }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>{{ __('dashboard.trip_field_type') }}</span>
                    <select id="trip_form_trip_type" name="trip_type">
                        <option value="">—</option>
                        @foreach(($tripTypes ?? []) as $tt)
                            <option value="{{ $tt }}" @selected(old('trip_type', $trip->trip_type) === $tt)>{{ $tt }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>{{ __('dashboard.trip_field_driver') }}</span>
                    <select id="trip_form_driver_id" name="driver_id">
                        <option value="">—</option>
                        @foreach(($drivers ?? []) as $d)
                            <option value="{{ $d->id }}" @selected((string) old('driver_id', $trip->driver_id) === (string) $d->id)>
                                {{ trim(($d->first_name ?? '').' '.($d->last_name ?? '')) }} (#{{ $d->id }})
                            </option>
                        @endforeach
                    </select>
                </label>

                <label><span>{{ __('dashboard.bus_number') }}</span><input id="trip_form_bus_number" name="bus_number" value="{{ old('bus_number', $trip->bus_number) }}" required></label>
                <label><span>{{ __('dashboard.route_title') }}</span><input id="trip_form_route_title" name="route_title" value="{{ old('route_title', $trip->route_title) }}"></label>
                <label style="grid-column:1 / -1;">
                    <span>{{ __('dashboard.trip_route_path') }}</span>
                    <input id="trip_form_location" name="location" value="{{ old('location', $trip->location) }}" readonly placeholder="{{ __('dashboard.trip_route_path_placeholder') }}">
                </label>
                <p id="trip_form_route_hint" class="help" style="grid-column:1 / -1;margin:0;display:none;"></p>
                <label><span>{{ __('dashboard.students_count') }}</span><input id="trip_form_students_count" type="number" min="0" name="students_count" value="{{ old('students_count', $trip->students_count) }}" required></label>
                <label>
                    <span>{{ __('dashboard.distance_km') }}</span>
                    <input id="trip_form_distance_km" type="number" step="0.01" min="0" name="distance_km" value="{{ old('distance_km', $trip->distance_km) }}" required readonly>
                </label>
                <label><span>{{ __('dashboard.trip_start_time') }}</span><input type="datetime-local" name="start_time" value="{{ old('start_time', optional($trip->start_time)->format('Y-m-d\TH:i')) }}" required></label>
                <label><span>{{ __('dashboard.trip_end_time') }}</span><input type="datetime-local" name="end_time" value="{{ old('end_time', optional($trip->end_time)->format('Y-m-d\TH:i')) }}"></label>
                @php
                    $selectableStatus = $selectableStatus ?? 'PRESENT';
                @endphp
                <label>
                    <span>{{ __('dashboard.trip_status') }}</span>
                    <select name="status" required>
                        <option value="PRESENT" @selected($selectableStatus === 'PRESENT')>PRESENT</option>
                        <option value="ACTIVE" @selected($selectableStatus === 'ACTIVE')>ACTIVE</option>
                    </select>
                </label>

                <p class="help" style="grid-column:1 / -1;margin:0;">
                    {{ __('dashboard.trip_edit_students_on_assign_page') }}
                    <a href="{{ route('dashboard.trips.assign_students', ['school_id' => $trip->school_id, 'trip_id' => $trip->id]) }}">{{ __('dashboard.menu_assign_trip_students') }}</a>
                </p>

                <label style="grid-column:1 / -1;"><span>{{ __('dashboard.notes') }}</span><textarea name="note" rows="3">{{ old('note', $trip->note) }}</textarea></label>

                <div style="grid-column:1 / -1;display:flex;gap:10px;flex-wrap:wrap;">
                    <button type="submit" class="btn-primary">{{ __('dashboard.update') }}</button>
                    <a href="{{ route('dashboard.trips.index') }}" class="btn-muted" style="text-decoration:none;">{{ __('dashboard.cancel') }}</a>
                </div>
            </form>
        </section>
    @endcomponent
    @include('dashboard.trips._form_options_script', [
        'formOptionsUrl' => $formOptionsUrl ?? '',
        'driverAutoFillUrl' => $driverAutoFillUrl ?? '',
        'exceptTripId' => $exceptTripId ?? null,
    ])
@endsection
