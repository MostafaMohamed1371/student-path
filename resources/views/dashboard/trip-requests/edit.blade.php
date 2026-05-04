@extends('dashboard.layout')

@section('title', __('dashboard.edit_trip_request'))

@section('content')
    @php($title = __('dashboard.edit_trip_request').' #'.$trip_request->id)
    @component('dashboard.partials.shell', ['title' => $title])
        @if($trip_request->status !== 'pending')
            <p style="color: var(--text-muted);">{{ __('dashboard.trip_request_edit_locked') }}</p>
            <p><a href="{{ route('dashboard.trip_requests.show', $trip_request) }}" class="link">{{ __('dashboard.action_view') }}</a></p>
        @else
            <section class="card">
                <form method="post" action="{{ route('dashboard.trip_requests.update', $trip_request) }}">
                    @csrf
                    @method('PUT')

                    <p style="margin:0 0 12px;color:var(--text-muted);">{{ __('dashboard.trip_request_field_user') }}: <strong>{{ $trip_request->user?->name }}</strong> ({{ $trip_request->user?->phone }})</p>

                    <label class="field-label">{{ __('dashboard.table_col_student') }}</label>
                    <select name="student_id" class="field-like" style="width:100%;max-width:420px;margin-bottom:12px;" required>
                        @foreach ($students as $s)
                            <option value="{{ $s->id }}" @selected(old('student_id', $trip_request->student_id) == $s->id)>{{ $s->full_name }} (#{{ $s->id }})</option>
                        @endforeach
                    </select>
                    @error('student_id')<p style="color:#c00;">{{ $message }}</p>@enderror

                    <label class="field-label">{{ __('dashboard.table_col_trip') }} ({{ __('dashboard.optional') }})</label>
                    <select name="trip_history_id" class="field-like" style="width:100%;max-width:420px;margin-bottom:12px;">
                        <option value="">—</option>
                        @foreach ($trips as $t)
                            <option value="{{ $t->id }}" @selected(old('trip_history_id', $trip_request->trip_history_id) == $t->id)>#{{ $t->id }} — {{ $t->bus_number }}</option>
                        @endforeach
                    </select>
                    @error('trip_history_id')<p style="color:#c00;">{{ $message }}</p>@enderror

                    <label class="field-label">{{ __('dashboard.table_col_notes') }}</label>
                    <textarea name="notes" rows="4" class="field-like" style="width:100%;max-width:520px;">{{ old('notes', $trip_request->notes) }}</textarea>
                    @error('notes')<p style="color:#c00;">{{ $message }}</p>@enderror

                    <div style="margin-top:16px;">
                        <button type="submit" class="btn-primary" style="width:auto;padding:10px 14px;">{{ __('dashboard.update') }}</button>
                        <a href="{{ route('dashboard.trip_requests.show', $trip_request) }}" class="link" style="margin-inline-start:12px;">{{ __('dashboard.cancel') }}</a>
                    </div>
                </form>
            </section>
        @endif
    @endcomponent
@endsection
