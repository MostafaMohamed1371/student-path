@extends('dashboard.layout')

@section('title', __('dashboard.menu_routes'))

@section('content')
    @php($title = __('dashboard.menu_routes'))
    @component('dashboard.partials.shell', ['title' => $title])
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
            <p style="color:var(--text-muted);margin:0;">{{ __('dashboard.routes_page_intro') }}</p>
            @if(auth()->user()?->canMutateSchoolRoster())
                <a href="{{ route('dashboard.routes.create') }}" class="btn-primary" style="width:auto;padding:10px 14px;text-decoration:none;">{{ __('dashboard.add_route') }}</a>
            @endif
        </div>

        @if(session('success'))
            <div class="alert" style="margin-bottom:16px;">{{ session('success') }}</div>
        @endif

        @include('dashboard.partials.school_driver_filter')

        @php($highlightRouteId = (int) session('highlight_route_id'))

        <section class="card">
            <h2 style="margin:0 0 12px;font-size:18px;">
                {{ __('dashboard.route_created_list_title', ['count' => $routes->total()]) }}
            </h2>
            <div style="overflow:auto;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>{{ __('dashboard.route_name') }}</th>
                        <th>{{ __('dashboard.school') }}</th>
                        <th>{{ __('dashboard.trip_field_type') }}</th>
                        <th>{{ __('dashboard.shift_period') }}</th>
                        <th>{{ __('dashboard.driver') }}</th>
                        <th>{{ __('dashboard.bus_number') }}</th>
                        <th>{{ __('dashboard.route_start_address') }}</th>
                        <th>{{ __('dashboard.route_end_address') }}</th>
                        <th>{{ __('dashboard.route_students_on_route') }}</th>
                        <th>{{ __('dashboard.route_created_at') }}</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($routes as $route)
                        @php($isHighlighted = $highlightRouteId > 0 && $highlightRouteId === (int) $route->id)
                        <tr @if($isHighlighted) style="background:#ecfdf5;" @endif>
                            <td>
                                <strong>{{ $route->name }}</strong>
                                @if($isHighlighted)
                                    <span style="display:inline-block;margin-left:6px;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;background:#16a34a;color:#fff;">{{ __('dashboard.route_just_created') }}</span>
                                @endif
                            </td>
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
                            <td>{{ trim(($route->driver?->first_name ?? '').' '.($route->driver?->last_name ?? '')) ?: '—' }}</td>
                            <td>{{ $route->driver?->bus?->number ?? '—' }}</td>
                            <td>{{ $route->start_address ?: '—' }}</td>
                            <td>{{ $route->school?->address ?: '—' }}</td>
                            <td>{{ $route->routeStudents->count() }}</td>
                            <td class="mono">{{ $route->created_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td style="white-space:nowrap;">
                                @if(auth()->user()?->canMutateSchoolRoster())
                                    <a href="{{ route('dashboard.routes.edit', $route) }}" class="btn-muted" style="text-decoration:none;">{{ __('dashboard.edit') }}</a>
                                    <form method="post" action="{{ route('dashboard.routes.destroy', $route) }}" style="display:inline;margin-inline-start:6px;" onsubmit="return confirm(@json(__('dashboard.confirm_delete')))">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="btn-muted">{{ __('dashboard.delete') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11">{{ __('dashboard.route_created_list_empty') }}</td>
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
