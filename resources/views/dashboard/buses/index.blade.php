@extends('dashboard.layout')

@section('title', __('dashboard.menu_bus'))

@section('content')
    @php($title = __('dashboard.menu_bus'))
    @component('dashboard.partials.shell', ['title' => $title])
        @if(auth()->user()?->canMutateSchoolRoster())
        <div style="display:flex;justify-content:flex-end;margin-bottom:14px;">
            <a href="{{ route('dashboard.buses.create') }}" class="btn-primary" style="width:auto;padding:10px 14px;text-decoration:none;">{{ __('dashboard.add_bus') }}</a>
        </div>
        @endif

        @include('dashboard.partials.school_driver_filter')

        <section class="card">
            <div style="overflow:auto;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{ __('dashboard.bus_name') }}</th>
                        <th>{{ __('dashboard.bus_number') }}</th>
                        <th>{{ __('dashboard.driver') }}</th>
                        <th>{{ __('dashboard.school') }}</th>
                        <th>{{ __('dashboard.bus_city') }}</th>
                        @if(auth()->user()?->canMutateSchoolRoster())
                            <th>{{ __('dashboard.actions') }}</th>
                        @endif
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($buses as $bus)
                        <tr>
                            <td>{{ $bus->id }}</td>
                            <td>{{ $bus->name }}</td>
                            <td>{{ $bus->number }}</td>
                            <td>{{ $bus->driver ? $bus->driver->first_name.' '.$bus->driver->last_name : '—' }}</td>
                            <td>{{ $bus->school?->name_en ?: $bus->driver?->school?->name_en ?: '—' }}</td>
                            <td>{{ $bus->city }}</td>
                            @if(auth()->user()?->canMutateSchoolRoster())
                            <td style="display:flex;gap:8px;">
                                <a href="{{ route('dashboard.buses.edit', $bus) }}" class="btn-muted" style="text-decoration:none;">{{ __('dashboard.edit') }}</a>
                                <form method="post" action="{{ route('dashboard.buses.destroy', $bus) }}" onsubmit="return confirm('{{ __('dashboard.confirm_delete') }}')">
                                    @csrf
                                    @method('delete')
                                    <button type="submit" class="btn-muted">{{ __('dashboard.delete') }}</button>
                                </form>
                            </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()?->canMutateSchoolRoster() ? 7 : 6 }}">{{ __('dashboard.no_buses') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($buses->total() > 0)
                <div style="margin-top:16px;">{{ $buses->links() }}</div>
            @endif
        </section>
    @endcomponent
@endsection
