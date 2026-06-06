@extends('dashboard.layout')

@section('title', __('dashboard.add_guardian'))

@section('content')
    @php($title = __('dashboard.add_guardian'))
    @php($guardian = null)
    @component('dashboard.partials.shell', ['title' => $title])
        <section class="card">
            <form method="post" action="{{ route('dashboard.guardians.store') }}">
                @include('dashboard.guardians._form', [
                    'method' => 'post',
                    'submitLabel' => __('dashboard.save_guardian'),
                    'guardian' => null,
                    'schools' => $schools,
                    'homeLocation' => $homeLocation ?? null,
                ])
            </form>
        </section>
    @endcomponent
    @include('dashboard.guardians._id_card_autofill_script', [
        'guardianLookupUrl' => $guardianLookupUrl ?? '',
        'guardian' => null,
    ])
    @include('dashboard.guardians._home_location_map_script')
@endsection
