@extends('dashboard.layout')

@section('title', __('dashboard.add_trip_request'))

@section('content')
    @php($title = __('dashboard.add_trip_request'))
    @component('dashboard.partials.shell', ['title' => $title])
        <p style="color: var(--text-muted); margin: 0 0 20px;">{{ __('dashboard.trip_request_create_intro') }}</p>

        <section class="card">
            <form id="trip_request_create_form" method="post" action="{{ route('dashboard.trip_requests.store') }}">
                @csrf

                @if ($errors->any())
                    <div class="alert" style="margin-bottom: 16px;">
                        <ul style="margin: 0; padding-left: 18px;">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <label class="field-label" for="trip_request_school_id">{{ __('dashboard.school') }}</label>
                @if(auth()->user()?->is_admin)
                    <select id="trip_request_school_id" name="school_id" class="field-like" style="width:100%;max-width:420px;margin-bottom:12px;" required>
                        <option value="">{{ __('dashboard.select_school') }}</option>
                        @foreach ($schools as $school)
                            <option value="{{ $school->id }}" @selected((string) old('school_id') === (string) $school->id)>
                                {{ $school->name_en }} @if($school->name_ar) ({{ $school->name_ar }}) @endif
                            </option>
                        @endforeach
                    </select>
                @else
                    @php($fixedSchool = $schools->first())
                    <input type="hidden" id="trip_request_school_id" name="school_id" value="{{ old('school_id', $fixedSchool?->id) }}">
                    <input class="field-like" style="width:100%;max-width:420px;margin-bottom:12px;" value="{{ $fixedSchool?->name_en ?: '—' }}" disabled />
                @endif
                @error('school_id')<p style="color:#c00;margin:0 0 12px;">{{ $message }}</p>@enderror

                <label class="field-label" for="trip_request_trip_history_id">{{ __('dashboard.table_col_trip') }}</label>
                <select id="trip_request_trip_history_id" name="trip_history_id" class="field-like" style="width:100%;max-width:420px;margin-bottom:12px;" required>
                    <option value="">{{ __('dashboard.trip_request_select_school_first') }}</option>
                </select>
                @error('trip_history_id')<p style="color:#c00;margin:0 0 12px;">{{ $message }}</p>@enderror

                <label class="field-label" for="trip_request_user_id">{{ __('dashboard.trip_request_field_user') }}</label>
                <select id="trip_request_user_id" name="user_id" class="field-like" style="width:100%;max-width:420px;margin-bottom:12px;" required>
                    <option value="">{{ __('dashboard.trip_request_select_school_first') }}</option>
                </select>
                @error('user_id')<p style="color:#c00;margin:0 0 12px;">{{ $message }}</p>@enderror

                <label class="field-label" for="trip_request_student_id">{{ __('dashboard.table_col_student') }}</label>
                <select id="trip_request_student_id" name="student_id" class="field-like" style="width:100%;max-width:420px;margin-bottom:12px;" required>
                    <option value="">{{ __('dashboard.trip_request_select_parent_first') }}</option>
                </select>
                <p id="trip_request_student_help" class="field-help" style="margin:0 0 12px;">{{ __('dashboard.trip_request_student_filter_help') }}</p>
                @error('student_id')<p style="color:#c00;margin:0 0 12px;">{{ $message }}</p>@enderror

                <label class="field-label" for="trip_request_notes">{{ __('dashboard.table_col_notes') }} ({{ __('dashboard.optional') }})</label>
                <textarea id="trip_request_notes" name="notes" rows="4" class="field-like" style="width:100%;max-width:520px;margin-bottom:12px;">{{ old('notes') }}</textarea>
                @error('notes')<p style="color:#c00;margin:0 0 12px;">{{ $message }}</p>@enderror

                <div style="margin-top:8px;">
                    <button type="submit" class="btn-primary" style="width:auto;padding:10px 14px;">{{ __('dashboard.save') }}</button>
                    <a href="{{ route('dashboard.trip_requests.index') }}" class="link" style="margin-inline-start:12px;">{{ __('dashboard.cancel') }}</a>
                </div>
            </form>
        </section>
    @endcomponent

    @include('dashboard.trip-requests._create_form_script', [
        'formOptionsUrl' => $formOptionsUrl ?? '',
        'formStudentsUrl' => $formStudentsUrl ?? '',
    ])
@endsection
