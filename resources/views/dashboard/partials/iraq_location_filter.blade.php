@if($showIraqLocationFilter ?? false)
    <label>
        <span class="field-label">{{ __('dashboard.governorate') }}</span>
        <select class="input" id="iraq_filter_district_id" name="district_id" style="min-width:200px;">
            <option value="0" @selected(($filterDistrictId ?? 0) === 0)>{{ __('dashboard.report_filter_all_governorates') }}</option>
            @foreach($governorates ?? [] as $gov)
                <option value="{{ $gov->id }}" @selected(($filterDistrictId ?? 0) === (int) $gov->id)>{{ $gov->name }}</option>
            @endforeach
        </select>
    </label>
    <label>
        <span class="field-label">{{ __('dashboard.iraq_district') }}</span>
        <select class="input" id="iraq_filter_area_id" name="area_id" style="min-width:200px;" @disabled(($filterDistrictId ?? 0) === 0)>
            <option value="0" @selected(($filterAreaId ?? 0) === 0)>{{ __('dashboard.report_filter_all_districts') }}</option>
            @foreach($filterAreas ?? [] as $area)
                <option value="{{ $area->id }}" @selected(($filterAreaId ?? 0) === (int) $area->id)>{{ $area->name }}</option>
            @endforeach
        </select>
    </label>
    <label>
        <span class="field-label">{{ __('dashboard.iraq_sub_district') }}</span>
        <select class="input" id="iraq_filter_neighborhood_id" name="neighborhood_id" style="min-width:220px;" @disabled(($filterDistrictId ?? 0) === 0)>
            <option value="0" @selected(($filterNeighborhoodId ?? 0) === 0)>{{ __('dashboard.report_filter_all_sub_districts') }}</option>
            @foreach($filterNeighborhoods ?? [] as $neighborhood)
                <option value="{{ $neighborhood->id }}" @selected(($filterNeighborhoodId ?? 0) === (int) $neighborhood->id)>{{ $neighborhood->name }}</option>
            @endforeach
        </select>
    </label>
@endif
