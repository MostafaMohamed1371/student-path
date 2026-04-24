@extends('dashboard.layout')

@section('title', __('dashboard.add_student'))

@section('content')
    @php($title = __('dashboard.add_student'))
    @php($student = null)
    @component('dashboard.partials.shell', ['title' => $title])
        <section class="card">
            <form method="post" action="{{ route('dashboard.students.store') }}" enctype="multipart/form-data">
                @include('dashboard.students._form', [
                    'method' => 'post',
                    'submitLabel' => __('dashboard.save_student'),
                ])
            </form>
        </section>
    @endcomponent
@endsection
