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
@endsection
