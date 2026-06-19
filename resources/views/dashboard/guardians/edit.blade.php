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
                    'guardianSchoolRecords' => $guardianSchoolRecords ?? collect(),
                    'homeLocation' => $homeLocation ?? null,
                ])
            </form>
        </section>
    @endcomponent
    @include('dashboard.guardians._home_location_map_script')
    @include('dashboard.partials.iraq_location_cascade_script', [
        'iraqLocationPrefix' => 'guardian_home',
        'neighborhoodMultiple' => false,
    ])
    @include('dashboard.partials.iraq_location_map_sync_script', [
        'iraqLocationPrefix' => 'guardian_home',
        'mapRegistryKey' => 'guardian_home',
        'mapElementId' => 'guardian-home-map',
    ])
    @if(!empty($guardianSchoolRecords) && $guardianSchoolRecords->count() > 1)
        <script>
        (function () {
            const switcher = document.querySelector('[data-guardian-school-switcher]');
            if (!switcher) {
                return;
            }
            switcher.addEventListener('change', function () {
                const url = String(switcher.value || '');
                if (url && url.startsWith('http')) {
                    window.location.href = url;
                }
            });
        })();
        </script>
    @endif
@endsection
