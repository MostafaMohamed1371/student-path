@php($sel = $selectedStudentIds ?? [])
<label style="grid-column:1 / -1;">
    <span>{{ __('dashboard.trip_students_select') }}</span>
    <p style="margin:4px 0 8px;font-size:12px;color:#64748b;">{{ __('dashboard.trip_students_select_help') }}</p>
    <select id="trip_form_student_ids" name="student_ids[]" multiple size="8" style="width:100%;max-width:520px;">
        @forelse(($students ?? []) as $s)
            <option value="{{ $s->id }}" @selected(collect($sel)->contains($s->id))>
                {{ $s->full_name }} — {{ $s->grade }} (#{{ $s->id }})
            </option>
        @empty
            <option disabled>{{ $studentsPlaceholder ?? __('dashboard.trip_form_select_school_first') }}</option>
        @endforelse
    </select>
</label>
