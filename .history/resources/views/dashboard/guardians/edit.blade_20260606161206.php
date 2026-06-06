@extends('dashboard.layout')

@section('title', __('dashboard.edit_guardian'))

@section('content')
    @php($title = __('dashboard.edit_guardian'))
    @component('dashboard.partials.shell', ['title' => $title])
        <section class="card">
            <form method="post" action="{{ route('dashboard.guardians.update', $guardian) }}">
                @include('dashboard.guardians._form', [
                    'method' => 'put',
                    'submitLabel' => __('dashboard.save_guardian'),
                    'guardian' => $guardian,
                    'schools' => $schools,
                    'homeLocation' => $homeLocation ?? null,
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
    @include('dashboard.guardians._home_location_map_script')
@endsection
