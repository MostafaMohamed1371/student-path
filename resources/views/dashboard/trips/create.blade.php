@extends('dashboard.layout')

@section('title', __('dashboard.add_trip'))

@section('content')
    @php($title = __('dashboard.add_trip'))
    @component('dashboard.partials.shell', ['title' => $title])
        <section class="card">
            <form method="post" action="{{ route('dashboard.trips.store') }}" class="form-grid">
                @csrf

                <label>
                    <span>{{ __('dashboard.school') }}</span>
                    <select name="school_id" required>
                        <option value="">{{ __('dashboard.select_school') }}</option>
                        @foreach($schools as $school)
                            <option value="{{ $school->id }}" @selected(old('school_id') == $school->id)>{{ $school->name_en }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>{{ __('dashboard.trip_field_driver') }}</span>
                    <select name="driver_id">
                        <option value="">—</option>
                        @foreach(($drivers ?? []) as $d)
                            <option value="{{ $d->id }}" @selected((string) old('driver_id') === (string) $d->id)>
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
                            <option value="{{ $tt }}" @selected(old('trip_type') === $tt)>{{ $tt }}</option>
                        @endforeach
                    </select>
                </label>

                <label><span>{{ __('dashboard.bus_number') }}</span><input name="bus_number" value="{{ old('bus_number') }}" required></label>
                <label><span>{{ __('dashboard.route_title') }}</span><input name="route_title" value="{{ old('route_title') }}"></label>
                <label><span>{{ __('dashboard.location') }}</span><input name="location" value="{{ old('location') }}"></label>
                <label><span>{{ __('dashboard.students_count') }}</span><input type="number" min="0" name="students_count" value="{{ old('students_count', 0) }}" required></label>
                <label><span>{{ __('dashboard.distance_km') }}</span><input type="number" step="0.01" min="0" name="distance_km" value="{{ old('distance_km', 0) }}" required></label>
                <label><span>{{ __('dashboard.trip_start_time') }}</span><input type="datetime-local" name="start_time" value="{{ old('start_time') }}" required></label>
                <label><span>{{ __('dashboard.trip_end_time') }}</span><input type="datetime-local" name="end_time" value="{{ old('end_time') }}"></label>
                <label>
                    <span>{{ __('dashboard.trip_status') }}</span>
                    <select name="status" required>
                        <option value="ACTIVE" @selected(old('status', 'ACTIVE')==='ACTIVE')>ACTIVE</option>
                        <option value="PRESENT" @selected(old('status')==='PRESENT')>PRESENT</option>
                        <option value="ABSENT" @selected(old('status')==='ABSENT')>ABSENT</option>
                        <option value="CANCELLED" @selected(old('status')==='CANCELLED')>CANCELLED</option>
                        <option value="COMPLETED" @selected(old('status')==='COMPLETED')>COMPLETED</option>
                    </select>
                </label>
                <label style="grid-column:1 / -1;">
                    <span>{{ __('dashboard.trip_students_select') }}</span>
                    <select name="student_ids[]" multiple size="8" style="width:100%;max-width:520px;">
                        @foreach(($students ?? []) as $s)
                            <option value="{{ $s->id }}" @selected(collect(old('student_ids', []))->contains($s->id))>
                                {{ $s->full_name }} — {{ $s->grade }} (#{{ $s->id }})
                            </option>
                        @endforeach
                    </select>
                </label>
                <label style="grid-column:1 / -1;"><span>{{ __('dashboard.notes') }}</span><textarea name="note" rows="3">{{ old('note') }}</textarea></label>

                <div style="grid-column:1 / -1;display:flex;gap:10px;flex-wrap:wrap;">
                    <button type="submit" class="btn-primary">{{ __('dashboard.create') }}</button>
                    <a href="{{ route('dashboard.trips.index') }}" class="btn-muted" style="text-decoration:none;">{{ __('dashboard.cancel') }}</a>
                </div>
            </form>
        </section>
    @endcomponent
@endsection

