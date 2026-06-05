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
    @include('dashboard.students._guardian_filter_script', [
        'formGuardiansUrl' => $formGuardiansUrl ?? '',
        'guardianLookupUrl' => $guardianLookupUrl ?? '',
        'studentNameSingleWordMode' => false,
    ])
@endsection
