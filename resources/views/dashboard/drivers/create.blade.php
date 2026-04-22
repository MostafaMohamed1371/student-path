@extends('dashboard.layout')

@section('title', __('dashboard.add_driver'))

@section('content')
    @php($title = __('dashboard.add_driver'))
    @php($driver = null)
    @component('dashboard.partials.shell', ['title' => $title])
        <section class="card">
            <form method="post" action="{{ route('dashboard.drivers.store') }}" enctype="multipart/form-data">
                @include('dashboard.drivers._form', [
                    'method' => 'post',
                    'submitLabel' => __('dashboard.save_driver'),
                ])
            </form>
        </section>
    @endcomponent
@endsection
