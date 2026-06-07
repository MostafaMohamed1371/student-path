@extends('dashboard.layout')

@section('title', __('dashboard.edit_absence'))

@section('content')
    @php
        use App\Enums\AbsenceReason;

        $title = __('dashboard.edit_absence').' #'.$absence->id;
    @endphp
    @component('dashboard.partials.shell', ['title' => $title])
        <p style="margin:0 0 12px;"><strong>{{ __('dashboard.table_col_student') }}:</strong> {{ $absence->student?->full_name }} (#{{ $absence->student_id }})</p>
        <p style="margin:0 0 16px;color:var(--text-muted);"><strong>{{ __('dashboard.table_col_user') }}:</strong> {{ $absence->user?->name }} ({{ $absence->user?->phone }})</p>

        <section class="card">
            <form method="post" action="{{ route('dashboard.absences.update', $absence) }}">
                @csrf
                @method('PUT')

                <label class="field-label">{{ __('dashboard.absence_start') }}</label>
                <input type="date" name="start_date" class="field-like" value="{{ old('start_date', $absence->start_date?->toDateString()) }}" style="width:100%;max-width:280px;margin-bottom:12px;">
                @error('start_date')<p style="color:#c00;">{{ $message }}</p>@enderror

                <label class="field-label">{{ __('dashboard.absence_end') }}</label>
                <input type="date" name="end_date" class="field-like" value="{{ old('end_date', $absence->end_date?->toDateString()) }}" style="width:100%;max-width:280px;margin-bottom:12px;">
                @error('end_date')<p style="color:#c00;">{{ $message }}</p>@enderror

                <label class="field-label">{{ __('dashboard.absence_reason') }}</label>
                <select name="reason" class="field-like" style="width:100%;max-width:420px;margin-bottom:12px;">
                    @foreach (AbsenceReason::cases() as $reasonOption)
                        <option value="{{ $reasonOption->value }}" @selected(old('reason', $absence->reason) === $reasonOption->value)>{{ $reasonOption->labelEn() }}</option>
                    @endforeach
                </select>
                @error('reason')<p style="color:#c00;">{{ $message }}</p>@enderror

                <label class="field-label">{{ __('dashboard.table_col_notes') }}</label>
                <textarea name="notes" rows="3" class="field-like" style="width:100%;max-width:520px;">{{ old('notes', $absence->notes) }}</textarea>
                @error('notes')<p style="color:#c00;">{{ $message }}</p>@enderror

                <div style="margin-top:16px;">
                    <button type="submit" class="btn-primary" style="width:auto;padding:10px 14px;">{{ __('dashboard.update') }}</button>
                    <a href="{{ route('dashboard.absences.index') }}" class="link" style="margin-inline-start:12px;">{{ __('dashboard.cancel') }}</a>
                </div>
            </form>
        </section>
    @endcomponent
@endsection
