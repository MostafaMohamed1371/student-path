@extends('dashboard.layout')

@section('title', __('dashboard.menu_assign_trip_students'))

@section('content')
    @php($title = __('dashboard.menu_assign_trip_students'))
    @component('dashboard.partials.shell', ['title' => $title])
        <p style="color:var(--text-muted);margin:0 0 20px;">{{ __('dashboard.trip_assign_students_intro') }}</p>

        <section class="card">
            <form method="post" action="{{ route('dashboard.trips.assign_students.store') }}" class="form-grid" id="trip_assign_form">
                @csrf

                <label>
                    <span>{{ __('dashboard.school') }}</span>
                    @if(auth()->user()?->is_admin)
                        <select class="input" id="trip_assign_school_id" name="school_id_filter" required>
                            <option value="">{{ __('dashboard.select_school') }}</option>
                            @foreach($schools as $school)
                                <option value="{{ $school->id }}" @selected((int) $schoolId === (int) $school->id)>{{ $school->name_en }}</option>
                            @endforeach
                        </select>
                    @else
                        @php($fixedSchool = $schools->firstWhere('id', (int) $schoolId))
                        <input type="hidden" id="trip_assign_school_id" value="{{ $schoolId }}">
                        <input class="input" value="{{ $fixedSchool?->name_en ?: '—' }}" disabled>
                    @endif
                </label>

                <label>
                    <span>{{ __('dashboard.trip_field_driver') }} ({{ __('dashboard.optional') }})</span>
                    <select class="input" id="trip_assign_driver_id">
                        <option value="">{{ __('dashboard.trip_assign_filter_all_drivers') }}</option>
                        @foreach($drivers as $d)
                            <option value="{{ $d->id }}" @selected((int) $driverId === (int) $d->id)>
                                {{ trim(($d->first_name ?? '').' '.($d->last_name ?? '')) }} (#{{ $d->id }})
                            </option>
                        @endforeach
                    </select>
                </label>

                <label style="grid-column:1 / -1;">
                    <span>{{ __('dashboard.trip_assign_select_trips') }}</span>
                    <p style="margin:4px 0 8px;font-size:12px;color:#64748b;">{{ __('dashboard.trip_assign_select_trips_help') }}</p>
                    <select class="input" id="trip_assign_trip_ids" name="trip_ids[]" multiple size="8" required style="width:100%;max-width:640px;">
                        @foreach($trips as $t)
                            <option value="{{ $t->id }}" @selected(in_array((int) $t->id, $tripIds, true))>
                                @php($start = $t->start_time?->format('Y-m-d H:i'))
                                #{{ $t->id }} — {{ $t->route_title ?: __('dashboard.trip') }}@if($start) — {{ $start }}@endif
                            </option>
                        @endforeach
                    </select>
                </label>

                <div id="trip_assign_trip_summary" style="grid-column:1 / -1;display:{{ $selectedTrips->isNotEmpty() ? 'block' : 'none' }};padding:12px;background:#f8fafc;border-radius:8px;">
                    @foreach($selectedTrips as $trip)
                        <div style="margin-bottom:{{ $loop->last ? 0 : 12 }}px;padding-bottom:{{ $loop->last ? 0 : 12 }}px;{{ $loop->last ? '' : 'border-bottom:1px solid #e2e8f0;' }}">
                            <p style="margin:0 0 6px;"><strong>#{{ $trip->id }}</strong> — {{ $trip->route_title ?: __('dashboard.trip') }}</p>
                            <p style="margin:0 0 6px;"><strong>{{ __('dashboard.trip_field_type') }}:</strong> {{ $trip->trip_type ?: '—' }}</p>
                            <p style="margin:0 0 6px;"><strong>{{ __('dashboard.trip_start_time') }}:</strong> {{ $trip->start_time }}</p>
                            <p style="margin:0;"><strong>{{ __('dashboard.students_count') }}:</strong> {{ $trip->students_count }}</p>
                        </div>
                    @endforeach
                </div>

                <label style="grid-column:1 / -1;">
                    <span>{{ __('dashboard.trip_assign_select_students') }}</span>
                    <p id="trip_assign_students_filter_hint" class="help" style="margin:4px 0 8px;display:none;"></p>
                    <p style="margin:0 0 8px;font-size:12px;color:#64748b;">{{ __('dashboard.trip_assign_select_students_help') }}</p>
                    <select class="input" id="trip_assign_student_ids" name="student_ids[]" multiple size="12" style="width:100%;max-width:640px;">
                        @foreach($students as $student)
                            <option value="{{ $student->id }}" @selected(in_array((int) $student->id, $selectedStudentIds, true))>
                                {{ $student->full_name }} — {{ $student->grade }} (#{{ $student->id }})
                            </option>
                        @endforeach
                    </select>
                </label>

                <div style="grid-column:1 / -1;display:flex;gap:10px;flex-wrap:wrap;">
                    <button type="submit" class="btn-primary" style="width:auto;padding:10px 16px;">{{ __('dashboard.trip_assign_save') }}</button>
                    <a href="{{ route('dashboard.trips.index') }}" class="btn-muted" style="text-decoration:none;">{{ __('dashboard.cancel') }}</a>
                </div>
            </form>
        </section>
    @endcomponent

    @include('dashboard.trips._assign_students_script', ['formOptionsUrl' => $formOptionsUrl])
@endsection
