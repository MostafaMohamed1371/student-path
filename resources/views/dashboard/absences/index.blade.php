@extends('dashboard.layout')

@section('title', __('dashboard.menu_absences'))

@section('content')
    @php($title = __('dashboard.menu_absences'))
    @php
        use App\Enums\AbsenceReason;
    @endphp
    @component('dashboard.partials.shell', ['title' => $title])
        <p style="color: var(--text-muted); margin: 0 0 20px;">{{ __('dashboard.absences_page_intro') }}</p>

        @include('dashboard.partials.school_driver_filter')

        <div style="display:flex;justify-content:flex-end;gap:10px;margin-bottom:14px;">
            <a href="{{ route('dashboard.absences.create') }}" class="btn-primary" style="width:auto;padding:10px 14px;text-decoration:none;">{{ __('dashboard.add_absence') }}</a>
        </div>

        <section class="card">
            <div style="overflow:auto;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{ __('dashboard.table_col_user') }}</th>
                        <th>{{ __('dashboard.table_col_phone') }}</th>
                        <th>{{ __('dashboard.table_col_student') }}</th>
                        <th>{{ __('dashboard.table_col_driver') }}</th>
                        <th>{{ __('dashboard.table_col_dates') }}</th>
                        <th>{{ __('dashboard.absence_reason') }}</th>
                        <th>{{ __('dashboard.table_col_notes') }}</th>
                        <th>{{ __('dashboard.table_col_created') }}</th>
                        <th>{{ __('dashboard.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($absences as $a)
                        @php($u = $a->user)
                        @php($s = $a->student)
                        @php($d = $a->driver)
                        @php($reason = AbsenceReason::tryFrom((string) $a->reason))
                        <tr>
                            <td>{{ $a->id }}</td>
                            <td>{{ $u?->name ?? '—' }}</td>
                            <td class="mono">{{ $u?->phone ?? '—' }}</td>
                            <td>{{ $s?->full_name ?? '—' }}
                                @if ($s)
                                    <br><a href="{{ route('dashboard.students.attendance_schedule', $s) }}" class="link" style="font-size:0.85rem;">{{ __('dashboard.view_attendance_schedule') }}</a>
                                    <br><a href="{{ route('dashboard.students.daily_timeline', $s) }}" class="link" style="font-size:0.85rem;">{{ __('dashboard.view_daily_timeline') }}</a>
                                @endif
                            </td>
                            <td>
                                @if ($d)
                                    {{ trim(implode(' ', array_filter([$d->first_name, $d->father_name, $d->last_name]))) }}
                                    @if ($a->driver_notified_at)
                                        <br><small style="color:var(--text-muted);">{{ __('dashboard.absence_driver_notified') }}</small>
                                    @else
                                        <br><small style="color:var(--text-muted);">{{ __('dashboard.absence_not_notified') }}</small>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $a->start_date?->toDateString() ?? '—' }} → {{ $a->end_date?->toDateString() ?? '—' }}</td>
                            <td>{{ $reason?->labelEn() ?? $a->reason }}</td>
                            <td style="max-width: 220px;">{{ \Illuminate\Support\Str::limit($a->notes ?? '', 100) }}</td>
                            <td>{{ $a->created_at?->toDateTimeString() ?? '—' }}</td>
                            <td style="white-space:nowrap;">
                                <a href="{{ route('dashboard.absences.edit', $a) }}" class="btn-muted" style="text-decoration:none;">{{ __('dashboard.edit') }}</a>
                                <form method="post" action="{{ route('dashboard.absences.destroy', $a) }}" style="display:inline;" onsubmit="return confirm('{{ __('dashboard.confirm_delete') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-muted" style="margin-inline-start:6px;">{{ __('dashboard.delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">{{ __('dashboard.table_empty') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($absences->total() > 0)
                <div style="margin-top:16px;">{{ $absences->links() }}</div>
            @endif
        </section>
    @endcomponent
@endsection
