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
                    'drivers' => $drivers,
                ])

                <div style="grid-column:1 / -1;display:flex;gap:10px;flex-wrap:wrap;margin-top:8px;">
                    <button type="submit" class="btn-primary" style="width:auto;padding:10px 16px;">{{ __('dashboard.update') }}</button>
                    <a href="{{ route('dashboard.routes.index', ['school_id' => $route->school_id, 'trip_type' => $route->trip_type]) }}" class="btn-muted" style="text-decoration:none;">{{ __('dashboard.cancel') }}</a>
                </div>
            </form>
        </section>
    @endcomponent

    @include('dashboard.routes._form_options_script', ['formOptionsUrl' => $formOptionsUrl])
    @include('dashboard.routes._start_map_script')
@endsection
