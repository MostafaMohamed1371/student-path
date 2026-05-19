@extends('dashboard.layout')

@section('title', __('dashboard.menu_trip_requests'))

@section('content')
    @php($title = __('dashboard.menu_trip_requests'))
    @php($canManageTripRequests = auth()->user()?->canMutateSchoolRoster() ?? false)
    @component('dashboard.partials.shell', ['title' => $title])
        <p style="color: var(--text-muted); margin: 0 0 20px;">{{ __('dashboard.trip_requests_page_intro') }}</p>

        @if($showSchoolFilter || $showDriverFilter)
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
                    @elseif($schools->isNotEmpty())
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
                    <button type="submit" class="btn-primary" style="width:auto;padding:10px 16px;">{{ __('dashboard.filter') }}</button>
                </form>
            </section>
        @endif

        <section class="card">
            <div style="overflow:auto;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        @if($showSchoolColumn)
                            <th>{{ __('dashboard.school') }}</th>
                        @endif
                        <th>{{ __('dashboard.table_col_parent') }}</th>
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
                        @php($s = $r->student)
                        <tr>
                            <td>{{ $r->id }}</td>
                            @if($showSchoolColumn)
                                <td>{{ $s?->school?->name_en ?? $r->driver?->school?->name_en ?? '—' }}</td>
                            @endif
                            <td>{{ $r->parentDisplayName() }}</td>
                            <td class="mono">{{ $r->parentDisplayPhone() }}</td>
                            <td>{{ $s?->full_name ?? '—' }}</td>
                            <td>{{ $r->driverDisplayName() }}</td>
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
                            <td colspan="{{ $showSchoolColumn ? 11 : 10 }}">{{ __('dashboard.table_empty') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{ $tripRequests->links('dashboard.partials.pagination') }}
        </section>
    @endcomponent
@endsection
