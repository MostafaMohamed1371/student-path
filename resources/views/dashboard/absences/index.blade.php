@extends('dashboard.layout')

@section('title', __('dashboard.menu_absences'))

@section('content')
    @php($title = __('dashboard.menu_absences'))
    @component('dashboard.partials.shell', ['title' => $title])
        <p style="color: var(--text-muted); margin: 0 0 20px;">{{ __('dashboard.absences_page_intro') }}</p>

        @include('dashboard.partials.school_driver_filter')

        <div style="display:flex;justify-content:flex-end;margin-bottom:14px;">
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
                        <th>{{ __('dashboard.table_col_dates') }}</th>
                        <th>{{ __('dashboard.table_col_notes') }}</th>
                        <th>{{ __('dashboard.table_col_created') }}</th>
                        <th>{{ __('dashboard.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($absences as $a)
                        @php($u = $a->user)
                        @php($s = $a->student)
                        <tr>
                            <td>{{ $a->id }}</td>
                            <td>{{ $u?->name ?? '—' }}</td>
                            <td class="mono">{{ $u?->phone ?? '—' }}</td>
                            <td>{{ $s?->full_name ?? '—' }}</td>
                            <td>{{ $a->start_date?->toDateString() ?? '—' }} → {{ $a->end_date?->toDateString() ?? '—' }}</td>
                            <td style="max-width: 220px;">{{ \Illuminate\Support\Str::limit(($a->reason ?? '').($a->notes ? ' — '.$a->notes : ''), 100) }}</td>
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
                            <td colspan="8">{{ __('dashboard.table_empty') }}</td>
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
