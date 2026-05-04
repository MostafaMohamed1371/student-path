@extends('dashboard.layout')

@section('title', __('dashboard.add_absence'))

@section('content')
    @php($title = __('dashboard.add_absence'))
    @component('dashboard.partials.shell', ['title' => $title])
        <p style="color: var(--text-muted); margin: 0 0 16px;">{{ __('dashboard.absence_create_hint') }}</p>
        <section class="card">
            <form method="post" action="{{ route('dashboard.absences.store') }}">
                @csrf
                <label class="field-label">{{ __('dashboard.table_col_student') }}</label>
                <select name="student_id" class="field-like" style="width:100%;max-width:420px;margin-bottom:12px;" required>
                    <option value="">—</option>
                    @foreach ($students as $s)
                        <option value="{{ $s->id }}" @selected(old('student_id') == $s->id)>{{ $s->full_name }} (#{{ $s->id }})</option>
                    @endforeach
                </select>
                @error('student_id')<p style="color:#c00;">{{ $message }}</p>@enderror

                <label class="field-label">{{ __('dashboard.absence_start') }}</label>
                <input type="date" name="start_date" class="field-like" value="{{ old('start_date') }}" required style="width:100%;max-width:280px;margin-bottom:12px;">
                @error('start_date')<p style="color:#c00;">{{ $message }}</p>@enderror

                <label class="field-label">{{ __('dashboard.absence_end') }}</label>
                <input type="date" name="end_date" class="field-like" value="{{ old('end_date') }}" required style="width:100%;max-width:280px;margin-bottom:12px;">
                @error('end_date')<p style="color:#c00;">{{ $message }}</p>@enderror

                <label class="field-label">{{ __('dashboard.absence_reason') }}</label>
                <input type="text" name="reason" class="field-like" value="{{ old('reason') }}" required maxlength="255" style="width:100%;max-width:520px;margin-bottom:12px;">
                @error('reason')<p style="color:#c00;">{{ $message }}</p>@enderror

                <label class="field-label">{{ __('dashboard.table_col_notes') }}</label>
                <textarea name="notes" rows="3" class="field-like" style="width:100%;max-width:520px;">{{ old('notes') }}</textarea>
                @error('notes')<p style="color:#c00;">{{ $message }}</p>@enderror

                <div style="margin-top:16px;">
                    <button type="submit" class="btn-primary" style="width:auto;padding:10px 14px;">{{ __('dashboard.create') }}</button>
                    <a href="{{ route('dashboard.absences.index') }}" class="link" style="margin-inline-start:12px;">{{ __('dashboard.menu_absences') }}</a>
                </div>
            </form>
        </section>
    @endcomponent
@endsection
