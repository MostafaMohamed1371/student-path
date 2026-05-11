@extends('dashboard.layout')

@section('title', __('dashboard.edit_trip'))

@section('content')
    @php($title = __('dashboard.edit_trip'))
    @component('dashboard.partials.shell', ['title' => $title])
        <section class="card">
            <form method="post" action="{{ route('dashboard.trips.update', $trip) }}" class="form-grid">
                @csrf
                @method('put')

                <label>
                    <span>{{ __('dashboard.school') }}</span>
                    <select name="school_id" required>
                        @foreach($schools as $school)
                            <option value="{{ $school->id }}" @selected(old('school_id', $trip->school_id) == $school->id)>{{ $school->name_en }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>{{ __('dashboard.trip_field_driver') }}</span>
                    <select name="driver_id">
                        <option value="">—</option>
                        @foreach(($drivers ?? []) as $d)
                            <option value="{{ $d->id }}" @selected((string) old('driver_id', $trip->driver_id) === (string) $d->id)>
                                {{ trim(($d->first_name ?? '').' '.($d->last_name ?? '')) }} (#{{ $d->id }})
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>{{ __('dashboard.trip_field_type') }}</span>
                    <select name="trip_type">
                        <option value="">—</option>
                        @foreach(($tripTypes ?? []) as $tt)
                            <option value="{{ $tt }}" @selected(old('trip_type', $trip->trip_type) === $tt)>{{ $tt }}</option>
                        @endforeach
                    </select>
                </label>

                <label><span>{{ __('dashboard.bus_number') }}</span><input name="bus_number" value="{{ old('bus_number', $trip->bus_number) }}" required></label>
                <label><span>{{ __('dashboard.route_title') }}</span><input name="route_title" value="{{ old('route_title', $trip->route_title) }}"></label>
                <label><span>{{ __('dashboard.location') }}</span><input name="location" value="{{ old('location', $trip->location) }}"></label>
                <label><span>{{ __('dashboard.students_count') }}</span><input type="number" min="0" name="students_count" value="{{ old('students_count', $trip->students_count) }}" required></label>
                <label><span>{{ __('dashboard.distance_km') }}</span><input type="number" step="0.01" min="0" name="distance_km" value="{{ old('distance_km', $trip->distance_km) }}" required></label>
                <label><span>{{ __('dashboard.trip_start_time') }}</span><input type="datetime-local" name="start_time" value="{{ old('start_time', optional($trip->start_time)->format('Y-m-d\TH:i')) }}" required></label>
                <label><span>{{ __('dashboard.trip_end_time') }}</span><input type="datetime-local" name="end_time" value="{{ old('end_time', optional($trip->end_time)->format('Y-m-d\TH:i')) }}"></label>
                <label>
                    <span>{{ __('dashboard.trip_status') }}</span>
                    <select name="status" required>
                        <option value="ACTIVE" @selected(old('status', $trip->status)==='ACTIVE')>ACTIVE</option>
                        <option value="PRESENT" @selected(old('status', $trip->status)==='PRESENT')>PRESENT</option>
                        <option value="ABSENT" @selected(old('status', $trip->status)==='ABSENT')>ABSENT</option>
                        <option value="CANCELLED" @selected(old('status', $trip->status)==='CANCELLED')>CANCELLED</option>
                        <option value="COMPLETED" @selected(old('status', $trip->status)==='COMPLETED')>COMPLETED</option>
                    </select>
                </label>
                @php($sel = old('student_ids', $selectedStudentIds ?? []))
                <label style="grid-column:1 / -1;">
                    <span>{{ __('dashboard.trip_students_select') }}</span>
                    <select name="student_ids[]" multiple size="8" style="width:100%;max-width:520px;">
                        @foreach(($students ?? []) as $s)
                            <option value="{{ $s->id }}" @selected(collect($sel)->contains($s->id))>
                                {{ $s->full_name }} — {{ $s->grade }} (#{{ $s->id }})
                            </option>
                        @endforeach
                    </select>
                </label>
                <label style="grid-column:1 / -1;"><span>{{ __('dashboard.notes') }}</span><textarea name="note" rows="3">{{ old('note', $trip->note) }}</textarea></label>

                <div style="grid-column:1 / -1;display:flex;gap:10px;flex-wrap:wrap;">
                    <button type="submit" class="btn-primary">{{ __('dashboard.update') }}</button>
                    <a href="{{ route('dashboard.trips.index') }}" class="btn-muted" style="text-decoration:none;">{{ __('dashboard.cancel') }}</a>
                </div>
            </form>
        </section>
    @endcomponent
@endsection

