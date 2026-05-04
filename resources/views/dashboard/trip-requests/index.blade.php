@extends('dashboard.layout')

@section('title', __('dashboard.menu_trip_requests'))

@section('content')
    @php($title = __('dashboard.menu_trip_requests'))
    @component('dashboard.partials.shell', ['title' => $title])
        <p style="color: var(--text-muted); margin: 0 0 20px;">{{ __('dashboard.trip_requests_page_intro') }}</p>

        <div style="display:flex;justify-content:flex-end;margin-bottom:14px;">
            <a href="{{ route('dashboard.trip_requests.create') }}" class="btn-primary" style="width:auto;padding:10px 14px;text-decoration:none;">{{ __('dashboard.add_trip_request') }}</a>
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
                        <th>{{ __('dashboard.table_col_status') }}</th>
                        <th>{{ __('dashboard.table_col_trip') }}</th>
                        <th>{{ __('dashboard.table_col_notes') }}</th>
                        <th>{{ __('dashboard.table_col_created') }}</th>
                        <th>{{ __('dashboard.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($tripRequests as $r)
                        @php($u = $r->user)
                        @php($s = $r->student)
                        <tr>
                            <td>{{ $r->id }}</td>
                            <td>{{ $u?->name ?? '—' }}</td>
                            <td class="mono">{{ $u?->phone ?? '—' }}</td>
                            <td>{{ $s?->full_name ?? '—' }}</td>
                            <td>{{ $r->status }}</td>
                            <td>{{ $r->trip_history_id ?? '—' }}</td>
                            <td style="max-width: 200px;">{{ \Illuminate\Support\Str::limit($r->notes ?? '', 80) }}</td>
                            <td>{{ $r->created_at?->toDateTimeString() ?? '—' }}</td>
                            <td style="white-space:nowrap;">
                                <a href="{{ route('dashboard.trip_requests.show', $r) }}" class="btn-muted" style="text-decoration:none;">{{ __('dashboard.action_view') }}</a>
                                <a href="{{ route('dashboard.trip_requests.edit', $r) }}" class="btn-muted" style="text-decoration:none;margin-inline-start:6px;">{{ __('dashboard.edit') }}</a>
                                @if($r->status === 'pending')
                                    <form method="post" action="{{ route('dashboard.trip_requests.destroy', $r) }}" style="display:inline;" onsubmit="return confirm('{{ __('dashboard.confirm_delete') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-muted" style="margin-inline-start:6px;">{{ __('dashboard.delete') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">{{ __('dashboard.table_empty') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($tripRequests->hasPages())
                <div style="margin-top: 12px;">{{ $tripRequests->withQueryString()->links() }}</div>
            @endif
        </section>
    @endcomponent
@endsection
