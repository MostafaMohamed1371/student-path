@php
    $iraqLocationPrefix = $iraqLocationPrefix ?? 'form';
    $fieldPrefix = $fieldPrefix ?? '';
    $neighborhoodMultiple = $neighborhoodMultiple ?? false;
    $districtField = $fieldPrefix.'district_id';
    $areaField = $fieldPrefix.'area_id';
    $neighborhoodField = $neighborhoodMultiple
        ? $fieldPrefix.'neighborhood_ids'
        : $fieldPrefix.'neighborhood_id';
    $selectedNeighborhoodIds = collect(
        old(
            $neighborhoodMultiple ? $neighborhoodField : $neighborhoodField,
            $filterNeighborhoodIds ?? (
                ! empty($filterNeighborhoodId)
                    ? [(int) $filterNeighborhoodId]
                    : []
            ),
        ),
    )
        ->map(fn ($id) => (string) $id)
        ->all();
@endphp

<label>
    <span class="field-label">{{ __('dashboard.governorate') }}</span>
    <select class="input" id="iraq_{{ $iraqLocationPrefix }}_district_id" name="{{ $districtField }}">
        <option value="">{{ __('dashboard.select_governorate') }}</option>
        @foreach($governorates ?? [] as $gov)
            <option value="{{ $gov->id }}" @selected((string) old($districtField, $filterDistrictId ?? '') === (string) $gov->id)>{{ $gov->name }}</option>
        @endforeach
    </select>
</label>

<label>
    <span class="field-label">{{ __('dashboard.iraq_district') }}</span>
    <select class="input" id="iraq_{{ $iraqLocationPrefix }}_area_id" name="{{ $areaField }}" @disabled(empty($filterDistrictId))>
        <option value="">{{ __('dashboard.select_district') }}</option>
        @foreach($filterAreas ?? [] as $area)
            <option value="{{ $area->id }}" @selected((string) old($areaField, $filterAreaId ?? '') === (string) $area->id)>{{ $area->name }}</option>
        @endforeach
    </select>
</label>

<label>
    <span class="field-label">{{ __('dashboard.iraq_sub_district') }}</span>
    <select
        class="input"
        id="iraq_{{ $iraqLocationPrefix }}_neighborhood_id"
        name="{{ $neighborhoodMultiple ? $neighborhoodField.'[]' : $neighborhoodField }}"
        @disabled(empty($filterDistrictId))
        @if($neighborhoodMultiple) multiple size="6" @endif
    >
        @unless($neighborhoodMultiple)
            <option value="">{{ __('dashboard.select_sub_district') }}</option>
        @endunless
        @foreach($filterNeighborhoods ?? [] as $neighborhood)
            <option
                value="{{ $neighborhood->id }}"
                @selected(in_array((string) $neighborhood->id, $selectedNeighborhoodIds, true))
            >{{ $neighborhood->name }}</option>
        @endforeach
    </select>
    @if($neighborhoodMultiple)
        <p style="margin:6px 0 0;font-size:12px;color:#64748b;">{{ __('dashboard.select_sub_districts_help') }}</p>
    @endif
</label>
