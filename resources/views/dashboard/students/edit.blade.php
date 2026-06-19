@extends('dashboard.layout')

@section('title', __('dashboard.edit_student'))

@section('content')
    @php($title = __('dashboard.edit_student'))
    @component('dashboard.partials.shell', ['title' => $title])
        <section class="card">
            <form method="post" action="{{ route('dashboard.students.update', $student) }}" enctype="multipart/form-data">
                @include('dashboard.students._form', [
                    'method' => 'put',
                    'submitLabel' => __('dashboard.save_student'),
                    'student' => $student,
                ])
            </form>
        </section>
    @endcomponent
    @include('dashboard.students._location_map_script')
    @include('dashboard.partials.iraq_location_cascade_script', [
        'iraqLocationPrefix' => 'student',
        'neighborhoodMultiple' => false,
    ])
    @include('dashboard.partials.iraq_location_map_sync_script', [
        'iraqLocationPrefix' => 'student',
        'mapRegistryKey' => 'student',
        'mapElementId' => 'student-location-map',
    ])
    @include('dashboard.students._guardian_filter_script', [
        'formGuardiansUrl' => $formGuardiansUrl ?? '',
        'guardianLookupUrl' => $guardianLookupUrl ?? '',
        'studentNameSingleWordMode' => false,
    ])
@endsection
