@extends('dashboard.layout')

@section('title', __('dashboard.menu_bus'))

@section('content')
    @php($title = __('dashboard.menu_bus'))
    @php($submitLabel = __('dashboard.update'))
    @component('dashboard.partials.shell', ['title' => $title])
        <section class="card">
            <form method="post" action="{{ route('dashboard.buses.update', $bus) }}">
                @csrf
                @method('put')
                @include('dashboard.buses._form')
            </form>
        </section>
    @endcomponent
@endsection
