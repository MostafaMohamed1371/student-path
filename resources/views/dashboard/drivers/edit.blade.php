@extends('dashboard.layout')

@section('title', __('dashboard.edit_driver'))

@section('content')
    @php($title = __('dashboard.edit_driver'))
    @component('dashboard.partials.shell', ['title' => $title])
        <section class="card">
            <form method="post" action="{{ route('dashboard.drivers.update', $driver) }}" enctype="multipart/form-data">
                @include('dashboard.drivers._form', [
                    'method' => 'put',
                    'submitLabel' => __('dashboard.save_driver'),
                    'driver' => $driver,
                    'schools' => $schools,
                    'governorates' => $governorates ?? collect(),
                    'serviceAreaRows' => $serviceAreaRows ?? [],
                ])
            </form>
        </section>
    @endcomponent
@endsection
