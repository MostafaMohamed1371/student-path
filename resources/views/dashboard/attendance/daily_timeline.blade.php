@extends('dashboard.layout')

@section('title', __('dashboard.daily_timeline_title'))

@section('content')
    @php($title = __('dashboard.daily_timeline_title'))
    @component('dashboard.partials.shell', ['title' => $title])
        <p style="color: var(--text-muted); margin: 0 0 16px;">{{ __('dashboard.daily_timeline_intro') }}</p>

        <section class="card" style="margin-bottom:16px;">
            <form method="get" action="{{ route('dashboard.students.daily_timeline', $student) }}" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
                <div>
                    <label class="field-label">{{ __('dashboard.table_col_student') }}</label>
                    <select class="field-like" style="min-width:220px;" onchange="if(this.value){ window.location=this.value; }">
                        @foreach ($students as $s)
                            <option value="{{ route('dashboard.students.daily_timeline', ['student' => $s->id, 'date' => $date]) }}" @selected($s->id === $student->id)>
                                {{ $s->full_name }} @if($s->grade)({{ $s->grade }})@endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">{{ __('dashboard.attendance_date') }}</label>
                    <input type="date" name="date" class="field-like" value="{{ $date }}">
                </div>
                <button type="submit" class="btn-primary" style="width:auto;padding:10px 14px;">{{ __('dashboard.filter') }}</button>
                <a href="{{ route('dashboard.students.attendance_schedule', $student) }}" class="link">{{ __('dashboard.view_attendance_schedule') }}</a>
            </form>
        </section>

        <section class="card" style="margin-bottom:16px;">
            <h3 style="margin:0 0 4px;">{{ $timeline['student']['full_name'] }}</h3>
            <p style="margin:0 0 4px;color:var(--text-muted);">{{ $timeline['student']['school_name_en'] ?? $timeline['student']['school_name_ar'] }}</p>
            <p style="margin:0;color:var(--text-muted);">{{ $timeline['date_label_en'] }}</p>
            @if ($timeline['is_absent_today'])
                <p style="margin:12px 0 0;color:#D32F2F;font-weight:600;">{{ __('dashboard.daily_timeline_absent_today') }}</p>
            @endif
        </section>

        <section class="card">
            <h3 style="margin:0 0 16px;">{{ __('dashboard.daily_timeline_milestones') }}</h3>
            @foreach ($timeline['milestones'] as $milestone)
                <div style="display:flex;gap:12px;padding:14px 0;border-bottom:1px solid var(--border);">
                    <div style="flex:1;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                            <span style="display:inline-block;padding:4px 10px;border-radius:999px;font-size:0.85rem;background:{{ $milestone['status_background_color'] }};color:{{ $milestone['status_color'] }};">
                                {{ $milestone['status_label_en'] }}
                            </span>
                            <strong>{{ $milestone['title_en'] }}</strong>
                        </div>
                        <div style="color:var(--text-muted);margin-bottom:4px;">{{ $milestone['description_en'] }}</div>
                        <div class="mono">
                            @if ($milestone['scheduled_time'])
                                {{ $milestone['scheduled_time'] }}
                                @if ($milestone['actual_time']) → {{ $milestone['actual_time'] }} @endif
                            @elseif ($milestone['actual_time'])
                                {{ $milestone['actual_time'] }}
                            @else
                                —
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </section>
    @endcomponent
@endsection
