@extends('dashboard.layout')

@section('title', __('dashboard.menu_trip_requests'))

@section('content')
    @php($title = __('dashboard.menu_trip_requests'))
    @php($canManageTripRequests = auth()->user()?->canMutateSchoolRoster() ?? false)
    @component('dashboard.partials.shell', ['title' => $title])
        <p style="color: var(--text-muted); margin: 0 0 20px;">{{ __('dashboard.trip_requests_page_intro') }}</p>

        @if($canManageTripRequests)
            <div style="display:flex;justify-content:flex-end;margin-bottom:14px;">
                <a href="{{ route('dashboard.trip_requests.create') }}" class="btn-primary" style="width:auto;padding:10px 14px;text-decoration:none;">{{ __('dashboard.add_trip_request') }}</a>
            </div>
        @endif

        <section class="card" style="margin-bottom:16px;">
            <form method="get" action="{{ route('dashboard.trip_requests.index') }}" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
                <label>
                    <span class="field-label">{{ __('dashboard.pagination_per_page') }}</span>
                    <select class="input" name="per_page" style="min-width:100px;">
                        @foreach([10, 25, 50, 100] as $size)
                            <option value="{{ $size }}" @selected($tripRequests->perPage() === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="btn-primary" style="width:auto;padding:10px 16px;">{{ __('dashboard.filter') }}</button>
            </form>
        </section>

        <section class="card">
            <div style="overflow:auto;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{ __('dashboard.table_col_user') }}</th>
                        <th>{{ __('dashboard.table_col_phone') }}</th>
                        <th>{{ __('dashboard.table_col_student') }}</th>
                        <th>{{ __('dashboard.driver') }}</th>
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
                        @php($d = $r->driver)
                        <tr>
                            <td>{{ $r->id }}</td>
                            <td>{{ $u?->name ?? '—' }}</td>
                            <td class="mono">{{ $u?->phone ?? '—' }}</td>
                            <td>{{ $s?->full_name ?? '—' }}</td>
                            <td>{{ trim(($d?->first_name ?? '').' '.($d?->last_name ?? '')) ?: '—' }}</td>
                            <td>{{ $r->status }}</td>
                            <td>{{ $r->trip_history_id ?? '—' }}</td>
                            <td style="max-width: 200px;">{{ \Illuminate\Support\Str::limit($r->notes ?? '', 80) }}</td>
                            <td>{{ $r->created_at?->toDateTimeString() ?? '—' }}</td>
                            <td>
                                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                                    <a href="{{ route('dashboard.trip_requests.show', $r) }}" class="btn-muted" style="text-decoration:none;">{{ __('dashboard.action_view') }}</a>
                                    @if($canManageTripRequests && $r->status === 'pending')
                                        <a href="{{ route('dashboard.trip_requests.edit', $r) }}" class="btn-muted" style="text-decoration:none;">{{ __('dashboard.edit') }}</a>
                                        <form method="post" action="{{ route('dashboard.trip_requests.destroy', $r) }}" style="display:inline;margin:0;" onsubmit="return confirm(@json(__('dashboard.confirm_delete')))">
                                            @csrf
                                            @method('delete')
                                            <button type="submit" class="btn-muted">{{ __('dashboard.delete') }}</button>
                                        </form>
                                    @endif
                                </div>
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

            {{ $tripRequests->withQueryString()->links('dashboard.partials.pagination') }}
        </section>
    @endcomponent
@endsection
