<div id="driver_service_areas_container">
    @foreach($serviceAreaRows ?? [] as $index => $serviceAreaRow)
        @include('dashboard.drivers._service_area_row', [
            'index' => $index,
            'serviceAreaRow' => $serviceAreaRow,
            'governorates' => $governorates ?? collect(),
            'serviceAreaRowsCount' => count($serviceAreaRows ?? []),
        ])
    @endforeach
</div>

<div style="margin-top:16px;">
    <h4 style="margin:0 0 6px;font-size:15px;">{{ __('dashboard.driver_service_area_map_title') }}</h4>
    <p style="margin:0 0 8px;font-size:12px;color:#64748b;">{{ __('dashboard.driver_service_area_map_help') }}</p>
    <div id="driver_service_area_map" style="height:320px;border:1px solid #cbd5e1;border-radius:10px;"></div>
    <p id="driver_service_area_map_notice" style="display:none;margin:8px 0 0;font-size:12px;color:#b45309;"></p>
</div>

<div style="display:flex;justify-content:flex-start;margin:12px 0 8px;">
    <button type="button" class="btn-primary" id="driver_service_area_add" style="width:auto;padding:10px 14px;">
        {{ __('dashboard.add_address') }}
    </button>
</div>
<p style="margin:0;font-size:12px;color:#64748b;">{{ __('dashboard.driver_service_areas_help') }}</p>

<template id="driver_service_area_template">
    @include('dashboard.drivers._service_area_row', [
        'index' => '__INDEX__',
        'serviceAreaRow' => [
            'district_id' => '',
            'area_id' => '',
            'neighborhood_ids' => [],
            'monthly_subscription_price' => '',
            'areas' => collect(),
            'neighborhoods' => collect(),
        ],
        'governorates' => $governorates ?? collect(),
        'serviceAreaRowsCount' => 2,
    ])
</template>

@include('dashboard.drivers._service_areas_script')
@include('dashboard.drivers._service_area_map_script')
