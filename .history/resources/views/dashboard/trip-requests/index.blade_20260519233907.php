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
                @if($showSchoolFilter)
                    <label>
                        <span class="field-label">{{ __('dashboard.school') }}</span>
                        <select class="input" name="school_id" style="min-width:200px;">
                            <option value="0" @selected($filterSchoolId === 0)>{{ __('dashboard.trip_requests_filter_all_schools') }}</option>
                            @foreach($schools as $school)
                                <option value="{{ $school->id }}" @selected($filterSchoolId === (int) $school->id)>{{ $school->name_en }}</option>
                            @endforeach
                        </select>
                    </label>
                @elseif($showDriverFilter && $schools->isNotEmpty())
                    <input type="hidden" name="school_id" value="{{ $filterSchoolId }}">
                    <label>
                        <span class="field-label">{{ __('dashboard.school') }}</span>
                        <select class="input" disabled style="min-width:200px;">
                            <option>{{ $schools->first()->name_en }}</option>
                        </select>
                    </label>
                @endif
                @if($showDriverFilter)
                    <label>
                        <span class="field-label">{{ __('dashboard.driver') }}</span>
                        <select class="input" name="driver_id" style="min-width:200px;">
                            <option value="0" @selected($filterDriverId === 0)>{{ __('dashboard.trip_requests_filter_all_drivers') }}</option>
                            @foreach($drivers as $driver)
                                @php($driverName = trim(($driver->first_name ?? '').' '.($driver->last_name ?? '')) ?: '#'.$driver->id)
                                <option value="{{ $driver->id }}" @selected($filterDriverId === (int) $driver->id)>{{ $driverName }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
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
