@extends('dashboard.layout')

@section('title', __('dashboard.add_bus'))

@section('content')
    @php($title = __('dashboard.add_bus'))
    @php($submitLabel = __('dashboard.create'))
    @php($bus = null)
    @component('dashboard.partials.shell', ['title' => $title])
        <section class="card">
            <form method="post" action="{{ route('dashboard.buses.store') }}">
                @csrf
                @include('dashboard.buses._form')
            </form>
        </section>
    @endcomponent
@endsection
