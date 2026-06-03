@extends('dashboard.layout')

@section('title', __('dashboard.menu_assigned_driver_in_route'))

@section('content')
    @php($title = __('dashboard.menu_assigned_driver_in_route'))
    @php($canAssign = auth()->user()?->canMutateSchoolRoster())
    @component('dashboard.partials.shell', ['title' => $title])
        <p style="color:var(--text-muted);margin:0 0 20px;">{{ __('dashboard.assigned_driver_in_route_page_intro') }}</p>

        @if(session('success'))
            <div class="alert" style="margin-bottom:16px;">{{ session('success') }}</div>
        @endif

        @include('dashboard.partials.school_driver_filter')

        <section class="card">
            <h2 style="margin:0 0 12px;font-size:18px;">
                {{ __('dashboard.assigned_driver_in_route_list_title', ['count' => $routes->total()]) }}
            </h2>
            <div style="overflow:auto;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>{{ __('dashboard.route_name') }}</th>
                        <th>{{ __('dashboard.school') }}</th>
                        <th>{{ __('dashboard.trip_field_type') }}</th>
                        <th>{{ __('dashboard.shift_period') }}</th>
                        <th>{{ __('dashboard.monthly_subscription_price') }}</th>
                        <th>{{ __('dashboard.trip_field_driver') }}</th>
                        <th>{{ __('dashboard.bus_number') }}</th>
                        <th>{{ __('dashboard.driver_route_description') }}</th>
                        <th>{{ __('dashboard.route_students_on_route') }}</th>
                        @if($canAssign)
                            <th></th>
                        @endif
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($routes as $route)
                        @php($eligibleDrivers = $driverOptionsForRoute[$route->id] ?? collect())
                        <tr>
                            <td><strong>{{ $route->name }}</strong></td>
                            <td>{{ $route->school?->name_en ?: '—' }}</td>
                            <td>{{ $route->trip_type ?: '—' }}</td>
                            <td>
                                @if($route->shift_period === 'MORNING')
                                    {{ __('dashboard.shift_period_morning') }}
                                @elseif($route->shift_period === 'EVENING')
                                    {{ __('dashboard.shift_period_evening') }}
                                @elseif($route->shift_period === 'BOTH')
                                    {{ __('dashboard.shift_period_both') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if($route->monthly_subscription_price !== null)
                                    {{ number_format((int) $route->monthly_subscription_price) }}
                                @else
                                    —
                                @endif
                            </td>
                            @if($canAssign)
                                <td colspan="2" style="min-width:280px;">
                                    <form id="assign-driver-{{ $route->id }}" method="post" action="{{ route('dashboard.routes.assign_driver', $route) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                                        @csrf
                                        <select class="input" name="driver_id" required style="min-width:200px;margin:0;flex:1;">
                                            <option value="">{{ __('dashboard.route_select_driver_after_filters') }}</option>
                                            @foreach($eligibleDrivers as $d)
                                                <option value="{{ $d->id }}" @selected((int) $route->driver_id === (int) $d->id)>
                                                    {{ trim($d->first_name.' '.$d->last_name) }} @if($d->bus) ({{ $d->bus->number }}) @endif
                                                </option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                                <td>{{ $route->driver?->route_description ?: '—' }}</td>
                                <td>{{ $route->routeStudents->count() }}</td>
                                <td style="white-space:nowrap;">
                                    <button type="submit" form="assign-driver-{{ $route->id }}" class="btn-primary" style="width:auto;padding:8px 12px;">{{ __('dashboard.assign_driver') }}</button>
                                </td>
                            @else
                                <td>{{ trim(($route->driver?->first_name ?? '').' '.($route->driver?->last_name ?? '')) ?: '—' }}</td>
                                <td>{{ $route->driver?->bus?->number ?? '—' }}</td>
                                <td>{{ $route->driver?->route_description ?: '—' }}</td>
                                <td>{{ $route->routeStudents->count() }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canAssign ? 10 : 9 }}">{{ __('dashboard.assigned_driver_in_route_list_empty') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($routes->total() > 0)
                <div style="margin-top:16px;">{{ $routes->links() }}</div>
            @endif
        </section>
    @endcomponent
@endsection
