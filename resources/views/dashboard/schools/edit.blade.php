@extends('dashboard.layout')

@section('title', __('dashboard.edit_school'))

@section('content')
    @php($title = __('dashboard.edit_school'))
    @component('dashboard.partials.shell', ['title' => $title])
        <section class="card">
            <form method="post" action="{{ route('dashboard.schools.update', $school) }}" enctype="multipart/form-data">
                @include('dashboard.schools._form', [
                    'method' => 'put',
                    'submitLabel' => __('dashboard.update'),
                    'school' => $school,
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
