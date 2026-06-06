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
                    'driver' => null,
                    'schools' => $schools,
                    'governorates' => $governorates ?? collect(),
                    'filterAreas' => $filterAreas ?? collect(),
                    'filterNeighborhoods' => $filterNeighborhoods ?? collect(),
                    'filterDistrictId' => $filterDistrictId ?? 0,
                    'filterAreaId' => $filterAreaId ?? 0,
                    'filterNeighborhoodId' => $filterNeighborhoodId ?? 0,
                    'filterNeighborhoodIds' => $filterNeighborhoodIds ?? [],
                    'neighborhoodMultiple' => true,
                ])
            </form>
        </section>
    @endcomponent
    @include('dashboard.partials.iraq_location_cascade_script', [
        'iraqLocationPrefix' => 'form',
        'neighborhoodMultiple' => true,
    ])
@endsection
