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

        <section class="card" style="margin-bottom:20px;">
            <form method="get" action="{{ route('dashboard.routes.index') }}" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
                <label>
                    <span class="field-label">{{ __('dashboard.school') }}</span>
                    <select class="input" name="school_id" required style="min-width:200px;">
                        @foreach($schools as $school)
                            <option value="{{ $school->id }}" @selected($schoolId === (int) $school->id)>{{ $school->name_en }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="field-label">{{ __('dashboard.trip_field_type') }}</span>
                    <select class="input" name="trip_type" style="min-width:180px;">
                        @foreach($tripTypes as $tt)
                            <option value="{{ $tt }}" @selected($tripType === $tt)>{{ $tt }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="btn-primary" style="width:auto;padding:10px 16px;">{{ __('dashboard.filter') }}</button>
            </form>
        </section>

        @if($schoolId > 0)
            @php($highlightRouteId = (int) session('highlight_route_id'))

            <section class="card">
                <h2 style="margin:0 0 12px;font-size:18px;">
                    {{ __('dashboard.route_created_list_title', ['count' => $routes->count()]) }}
                </h2>
                <div style="overflow:auto;">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>{{ __('dashboard.route_name') }}</th>
                            <th>{{ __('dashboard.trip_field_type') }}</th>
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
                                <td>{{ $route->trip_type }}</td>
                                <td>{{ trim(($route->driver?->first_name ?? '').' '.($route->driver?->last_name ?? '')) ?: '—' }}</td>
                                <td>{{ $route->driver?->bus?->number ?? '—' }}</td>
                                <td>{{ $route->start_address ?: '—' }}</td>
                                <td>{{ $route->school?->address ?: '—' }}</td>
                                <td>{{ $route->routeStudents->count() }}</td>
                                <td class="mono">{{ $route->created_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td style="white-space:nowrap;">
                                    @if(auth()->user()?->canMutateSchoolRoster())
                                        <a href="{{ route('dashboard.routes.edit', $route) }}" class="btn-muted" style="text-decoration:none;">{{ __('dashboard.edit') }}</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">{{ __('dashboard.route_created_list_empty') }}</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endcomponent
@endsection
