@extends('dashboard.layout')

@section('title', __('dashboard.add_school'))

@section('content')
    @php($title = __('dashboard.add_school'))
    @php($school = null)
    @component('dashboard.partials.shell', ['title' => $title])
        <section class="card">
            <form method="post" action="{{ route('dashboard.schools.store') }}" enctype="multipart/form-data">
                @include('dashboard.schools._form', [
                    'method' => 'post',
                    'submitLabel' => __('dashboard.create'),
                ])
            </form>
        </section>
    @endcomponent
    @include('dashboard.schools._location_map_script')
    @include('dashboard.partials.iraq_location_cascade_script', [
        'iraqLocationPrefix' => 'school',
        'neighborhoodMultiple' => false,
    ])
    @include('dashboard.partials.iraq_location_map_sync_script', [
        'iraqLocationPrefix' => 'school',
        'mapRegistryKey' => 'school',
        'mapElementId' => 'school-map',
    ])
@endsection
