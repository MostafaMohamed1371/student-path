@extends('dashboard.layout')

@section('title', __('dashboard.add_trip'))

@section('content')
    @php($title = __('dashboard.add_trip'))
    @component('dashboard.partials.shell', ['title' => $title])
        <section class="card">
            <form method="post" action="{{ route('dashboard.trips.store') }}" class="form-grid" id="trip_form">
                @csrf

                <label>
                    <span>{{ __('dashboard.school') }}</span>
                    <select id="trip_form_school_id" name="school_id" required>
                        <option value="">{{ __('dashboard.select_school') }}</option>
                        @foreach($schools as $school)
                            <option
                                value="{{ $school->id }}"
                                data-address="{{ e($school->address ?? '') }}"
                                data-latitude="{{ $school->latitude !== null ? $school->latitude : '' }}"
                                data-longitude="{{ $school->longitude !== null ? $school->longitude : '' }}"
                                @selected(old('school_id') == $school->id)
                            >{{ $school->name_en }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>{{ __('dashboard.trip_field_type') }}</span>
                    <select id="trip_form_trip_type" name="trip_type" required>
                        <option value="">—</option>
                        @foreach(($tripTypes ?? []) as $tt)
                            <option value="{{ $tt }}" @selected(old('trip_type') === $tt)>{{ $tt }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>{{ __('dashboard.trip_field_driver') }}</span>
                    <select id="trip_form_driver_id" name="driver_id" required>
                        <option value="">—</option>
                        @foreach(($drivers ?? []) as $d)
                            <option value="{{ $d->id }}" @selected((string) old('driver_id') === (string) $d->id)>
                                {{ trim(($d->first_name ?? '').' '.($d->last_name ?? '')) }} (#{{ $d->id }})
                            </option>
                        @endforeach
                    </select>
                </label>

                <label style="grid-column:1 / -1;">
                    <span>{{ __('dashboard.trip_field_driver_service_areas') }}</span>
                    <select id="trip_form_driver_service_area_ids" name="driver_service_area_ids[]" multiple size="5" disabled>
                    </select>
                    <p class="help" style="margin:6px 0 0;">{{ __('dashboard.trip_service_areas_help') }}</p>
                </label>

                <label><span>{{ __('dashboard.bus_number') }}</span><input id="trip_form_bus_number" name="bus_number" value="{{ old('bus_number') }}" required></label>
                <label><span>{{ __('dashboard.route_title') }}</span><input id="trip_form_route_title" name="route_title" value="{{ old('route_title') }}"></label>

                <label style="grid-column:1 / -1;">
                    <span>{{ __('dashboard.trip_start_on_map') }}</span>
                    <input id="trip_form_start_address" name="start_address" value="{{ old('start_address') }}" readonly placeholder="{{ __('dashboard.trip_start_address_placeholder') }}">
                </label>
                <div style="grid-column:1 / -1;">
                    <div id="trip_form_start_map" style="height:320px;border:1px solid #cbd5e1;border-radius:10px;"></div>
                </div>
                <input type="hidden" id="trip_form_start_latitude" name="start_latitude" value="{{ old('start_latitude') }}">
                <input type="hidden" id="trip_form_start_longitude" name="start_longitude" value="{{ old('start_longitude') }}">

                <label style="grid-column:1 / -1;">
                    <span>{{ __('dashboard.trip_route_path') }}</span>
                    <input id="trip_form_location" name="location" value="{{ old('location') }}" readonly placeholder="{{ __('dashboard.trip_route_path_placeholder') }}">
                </label>
                <p id="trip_form_route_hint" class="help" style="grid-column:1 / -1;margin:0;display:none;"></p>
                <label><span>{{ __('dashboard.students_count') }}</span><input id="trip_form_students_count" type="number" min="0" name="students_count" value="{{ old('students_count', 0) }}" required></label>
                <label>
                    <span>{{ __('dashboard.distance_km') }}</span>
                    <input id="trip_form_distance_km" type="number" step="0.01" min="0" name="distance_km" value="{{ old('distance_km', 0) }}" required readonly>
                </label>
                <label><span>{{ __('dashboard.trip_start_time') }}</span><input type="datetime-local" name="start_time" value="{{ old('start_time') }}" required></label>
                <label><span>{{ __('dashboard.trip_end_time') }}</span><input type="datetime-local" name="end_time" value="{{ old('end_time') }}"></label>
                <label>
                    <span>{{ __('dashboard.trip_status') }}</span>
                    <select name="status" required>
                        <option value="PRESENT" @selected(old('status', 'PRESENT') === 'PRESENT')>PRESENT</option>
                        <option value="ACTIVE" @selected(old('status') === 'ACTIVE')>ACTIVE</option>
                    </select>
                </label>

                <p class="help" style="grid-column:1 / -1;margin:0;">{{ __('dashboard.trip_create_students_later_help') }}</p>

                <label style="grid-column:1 / -1;"><span>{{ __('dashboard.notes') }}</span><textarea name="note" rows="3">{{ old('note') }}</textarea></label>

                <div style="grid-column:1 / -1;display:flex;gap:10px;flex-wrap:wrap;">
                    <button type="submit" class="btn-primary">{{ __('dashboard.create') }}</button>
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
    @include('dashboard.trips._trip_start_map_script')
@endsection
