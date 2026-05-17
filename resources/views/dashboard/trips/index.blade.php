@extends('dashboard.layout')

@section('title', __('dashboard.menu_trips'))

@section('content')
    @php($title = __('dashboard.menu_trips'))
    @component('dashboard.partials.shell', ['title' => $title])
        @if(auth()->user()?->canMutateSchoolRoster())
        <div style="display:flex;justify-content:flex-end;margin-bottom:14px;">
            <a href="{{ route('dashboard.trips.create') }}" class="btn-primary" style="width:auto;padding:10px 14px;text-decoration:none;">{{ __('dashboard.add_trip') }}</a>
        </div>
        @endif

        <section class="card">
            <div style="overflow:auto;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{ __('dashboard.school') }}</th>
                        <th>{{ __('dashboard.bus_number') }}</th>
                        <th>{{ __('dashboard.route_title') }}</th>
                        <th>{{ __('dashboard.trip_start_time') }}</th>
                        <th>{{ __('dashboard.trip_status') }}</th>
                        <th>{{ __('dashboard.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($trips as $trip)
                        <tr>
                            <td>{{ $trip->id }}</td>
                            <td>{{ $trip->school?->name_en ?: '—' }}</td>
                            <td>{{ $trip->bus_number }}</td>
                            <td>{{ $trip->route_title ?: '—' }}</td>
                            <td>{{ $trip->start_time }}</td>
                            <td>{{ $trip->status }}</td>
                            <td style="display:flex;gap:8px;flex-wrap:wrap;">
                                <a href="{{ route('dashboard.trips.show', $trip) }}" class="btn-muted" style="text-decoration:none;">{{ __('dashboard.action_view') }}</a>
                                @if(auth()->user()?->canMutateSchoolRoster())
                                    <a href="{{ route('dashboard.trips.edit', $trip) }}" class="btn-muted" style="text-decoration:none;">{{ __('dashboard.edit') }}</a>
                                    <form method="post" action="{{ route('dashboard.trips.destroy', $trip) }}" onsubmit="return confirm('{{ __('dashboard.confirm_delete') }}')" style="display:inline;">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="btn-muted">{{ __('dashboard.delete') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">{{ __('dashboard.no_trips') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top:16px;">{{ $trips->links() }}</div>
        </section>
    @endcomponent
@endsection

