@extends('dashboard.layout')

@section('title', __('dashboard.edit_guardian'))

@section('content')
    @php($title = __('dashboard.edit_guardian'))
    @component('dashboard.partials.shell', ['title' => $title])
        <section class="card">
            <form method="post" action="{{ route('dashboard.guardians.update', $guardian) }}">
                @include('dashboard.guardians._form', [
                    'method' => 'put',
                    'submitLabel' => __('dashboard.save_guardian'),
                    'guardian' => $guardian,
                    'schools' => $schools,
                    'homeLocation' => $homeLocation ?? null,
                ])
            </form>
        </section>
    @endcomponent
    @include('dashboard.guardians._home_location_map_script')
@endsection
