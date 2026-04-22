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
@endsection
