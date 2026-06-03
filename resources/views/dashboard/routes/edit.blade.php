@extends('dashboard.layout')

@section('title', __('dashboard.edit_route'))

@section('content')
    @php($title = __('dashboard.edit_route'))
    @component('dashboard.partials.shell', ['title' => $title])
        <section class="card">
            <form method="post" action="{{ route('dashboard.routes.update', $route) }}" class="form-grid">
                @csrf
                @method('put')
                @include('dashboard.routes._form', [
                    'route' => $route,
                    'schools' => $schools,
                    'tripTypes' => $tripTypes,
                ])

                <div style="grid-column:1 / -1;display:flex;gap:10px;flex-wrap:wrap;margin-top:8px;">
                    <button type="submit" class="btn-primary" style="width:auto;padding:10px 16px;">{{ __('dashboard.update') }}</button>
                    <a href="{{ route('dashboard.routes.index', ['school_id' => $route->school_id, 'trip_type' => $route->trip_type]) }}" class="btn-muted" style="text-decoration:none;">{{ __('dashboard.cancel') }}</a>
                </div>
            </form>

            @if(auth()->user()?->canMutateSchoolRoster())
                <form method="post" action="{{ route('dashboard.routes.destroy', $route) }}" style="margin-top:20px;padding-top:16px;border-top:1px solid var(--card-border);" onsubmit="return confirm(@json(__('dashboard.confirm_delete')))">
                    @csrf
                    @method('delete')
                    <button type="submit" class="btn-muted" style="color:var(--danger-text);border-color:var(--danger-border);">{{ __('dashboard.delete') }}</button>
                </form>
            @endif
        </section>
    @endcomponent

    @include('dashboard.partials.iraq_location_cascade_script', ['iraqLocationPrefix' => 'form'])
    @include('dashboard.routes._start_map_script')
@endsection
