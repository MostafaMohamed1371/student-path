@extends('dashboard.layout')

@section('title', __('dashboard.add_route'))

@section('content')
    @php($title = __('dashboard.add_route'))
    @component('dashboard.partials.shell', ['title' => $title])
        <p style="color:var(--text-muted);margin:0 0 20px;">{{ __('dashboard.route_form_intro') }}</p>

        <section class="card">
            <form method="post" action="{{ route('dashboard.routes.store') }}" class="form-grid">
                @csrf
                @include('dashboard.routes._form', ['schools' => $schools, 'tripTypes' => $tripTypes, 'drivers' => $drivers])

                <div style="grid-column:1 / -1;display:flex;gap:10px;flex-wrap:wrap;margin-top:8px;">
                    <button type="submit" class="btn-primary" style="width:auto;padding:10px 16px;">{{ __('dashboard.create') }}</button>
                    <a href="{{ route('dashboard.routes.index') }}" class="btn-muted" style="text-decoration:none;">{{ __('dashboard.cancel') }}</a>
                </div>
            </form>
        </section>
    @endcomponent

    @include('dashboard.routes._form_options_script', ['formOptionsUrl' => $formOptionsUrl])
    @include('dashboard.routes._start_map_script')
@endsection
