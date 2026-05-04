@extends('dashboard.layout')

@section('title', __('dashboard.add_trip_request'))

@section('content')
    @php($title = __('dashboard.add_trip_request'))
    @component('dashboard.partials.shell', ['title' => $title])
        <section class="card">
            <form method="post" action="{{ route('dashboard.trip_requests.store') }}">
                @csrf
                <label class="field-label">{{ __('dashboard.trip_request_field_user') }}</label>
                <select name="user_id" class="field-like" style="width:100%;max-width:420px;margin-bottom:12px;" required>
                    <option value="">—</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected(old('user_id') == $u->id)>{{ $u->name }} ({{ $u->phone }})</option>
                    @endforeach
                </select>
                @error('user_id')<p style="color:#c00;margin:0 0 8px;">{{ $message }}</p>@enderror

                <label class="field-label">{{ __('dashboard.table_col_student') }}</label>
                <select name="student_id" class="field-like" style="width:100%;max-width:420px;margin-bottom:12px;" required>
                    <option value="">—</option>
                    @foreach ($students as $s)
                        <option value="{{ $s->id }}" @selected(old('student_id') == $s->id)>{{ $s->full_name }} (#{{ $s->id }})</option>
                    @endforeach
                </select>
                @error('student_id')<p style="color:#c00;margin:0 0 8px;">{{ $message }}</p>@enderror

                <label class="field-label">{{ __('dashboard.table_col_trip') }} ({{ __('dashboard.optional') }})</label>
                <select name="trip_history_id" class="field-like" style="width:100%;max-width:420px;margin-bottom:12px;">
                    <option value="">—</option>
                    @foreach ($trips as $t)
                        <option value="{{ $t->id }}" @selected(old('trip_history_id') == $t->id)>#{{ $t->id }} — {{ $t->bus_number }} — {{ $t->start_time?->toDateTimeString() }}</option>
                    @endforeach
                </select>
                @error('trip_history_id')<p style="color:#c00;margin:0 0 8px;">{{ $message }}</p>@enderror

                <label class="field-label">{{ __('dashboard.table_col_notes') }}</label>
                <textarea name="notes" rows="4" class="field-like" style="width:100%;max-width:520px;">{{ old('notes') }}</textarea>
                @error('notes')<p style="color:#c00;margin-top:8px;">{{ $message }}</p>@enderror

                <div style="margin-top:16px;">
                    <button type="submit" class="btn-primary" style="width:auto;padding:10px 14px;">{{ __('dashboard.create') }}</button>
                    <a href="{{ route('dashboard.trip_requests.index') }}" class="link" style="margin-inline-start:12px;">{{ __('dashboard.trip_requests_back') }}</a>
                </div>
            </form>
        </section>
    @endcomponent
@endsection
