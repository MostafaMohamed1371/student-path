@extends('dashboard.layout')

@section('title', __('dashboard.attendance_schedule_title'))

@section('content')
    @php($title = __('dashboard.attendance_schedule_title'))
    @component('dashboard.partials.shell', ['title' => $title])
        <p style="color: var(--text-muted); margin: 0 0 16px;">{{ __('dashboard.attendance_schedule_intro') }}</p>

        <section class="card" style="margin-bottom:16px;">
            <form method="get" action="{{ route('dashboard.students.attendance_schedule', $student) }}" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
                <div>
                    <label class="field-label">{{ __('dashboard.table_col_student') }}</label>
                    <select name="student_redirect" class="field-like" style="min-width:220px;" onchange="if(this.value){ window.location=this.value; }">
                        @foreach ($students as $s)
                            <option value="{{ route('dashboard.students.attendance_schedule', ['student' => $s->id, 'year' => $year, 'month' => $month]) }}" @selected($s->id === $student->id)>
                                {{ $s->full_name }} @if($s->grade)({{ $s->grade }})@endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">{{ __('dashboard.attendance_month') }}</label>
                    <input type="number" name="month" min="1" max="12" class="field-like" value="{{ $month }}" style="width:80px;">
                </div>
                <div>
                    <label class="field-label">{{ __('dashboard.attendance_year') }}</label>
                    <input type="number" name="year" min="2000" max="2100" class="field-like" value="{{ $year }}" style="width:100px;">
                </div>
                <button type="submit" class="btn-primary" style="width:auto;padding:10px 14px;">{{ __('dashboard.filter') }}</button>
                <a href="{{ route('dashboard.absences.index', ['student_id' => $student->id]) }}" class="link">{{ __('dashboard.menu_absences') }}</a>
            </form>
        </section>

        <section class="card" style="margin-bottom:16px;">
            <h3 style="margin:0 0 12px;">{{ $schedule['month_label_en'] }} — {{ $student->full_name }}</h3>
            <div style="display:flex;flex-wrap:wrap;gap:16px;margin-bottom:16px;">
                <div><strong style="color:{{ $schedule['summary']['present_color'] }};">{{ __('dashboard.attendance_present_days') }}:</strong> {{ $schedule['summary']['present_days'] }}</div>
                <div><strong style="color:{{ $schedule['summary']['absence_color'] }};">{{ __('dashboard.attendance_absence_days') }}:</strong> {{ $schedule['summary']['absence_days'] }}</div>
                <div><strong style="color:{{ $schedule['summary']['late_color'] }};">{{ __('dashboard.attendance_late_count') }}:</strong> {{ $schedule['summary']['late_count'] }}</div>
            </div>

            <div style="overflow:auto;">
                <table class="table" style="min-width:640px;">
                    <thead>
                    <tr>
                        <th>{{ __('dashboard.attendance_date') }}</th>
                        <th>{{ __('dashboard.attendance_status') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($schedule['calendar'] as $day)
                        @if ($day['status'])
                            <tr>
                                <td>{{ $day['date'] }}</td>
                                <td>
                                    @if ($day['status_color'])
                                        <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:{{ $day['status_color'] }};margin-inline-end:6px;"></span>
                                    @endif
                                    @if ($day['status'] === 'present')
                                        <span style="color:{{ $day['status_color'] }};">{{ $day['status_label_en'] }}</span>
                                    @elseif ($day['status'] === 'absent')
                                        <span style="color:{{ $day['status_color'] }};">{{ $day['status_label_en'] }}</span>
                                    @elseif ($day['status'] === 'late')
                                        <span style="color:{{ $day['status_color'] }};">{{ $day['status_label_en'] }}</span>
                                    @else
                                        {{ $day['status_label_en'] ?? '—' }}
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h3 style="margin:0 0 12px;">{{ __('dashboard.attendance_recent_events') }}</h3>
            @forelse ($schedule['recent_events'] as $event)
                <div style="padding:12px 0;border-bottom:1px solid var(--border);">
                    <strong>{{ $event['date'] }}</strong>
                    — {{ $event['day_name_en'] ?? '' }}
                    @if ($event['type'] === 'absence')
                        <span style="color:{{ $event['color'] ?? '#D32F2F' }};">({{ __('dashboard.attendance_event_absence') }})</span>
                        <div style="color:var(--text-muted);margin-top:4px;">{{ __('dashboard.absence_reason') }}: {{ $event['reason_text'] ?? $event['reason_label_en'] ?? '—' }}</div>
                    @else
                        <span style="color:{{ $event['color'] ?? '#5D4037' }};">({{ __('dashboard.attendance_event_late') }})</span>
                        <div style="color:var(--text-muted);margin-top:4px;">{{ __('dashboard.attendance_delay_minutes', ['minutes' => $event['delay_minutes'] ?? 0]) }}</div>
                    @endif
                </div>
            @empty
                <p style="color:var(--text-muted);margin:0;">{{ __('dashboard.table_empty') }}</p>
            @endforelse
        </section>
    @endcomponent
@endsection
