@php
    $rowIndex = $index ?? 0;
    $rowLabelNumber = is_numeric($rowIndex) ? ((int) $rowIndex + 1) : 1;
    $row = $serviceAreaRow ?? [];
    $rowAreas = $row['areas'] ?? collect();
    $rowNeighborhoods = $row['neighborhoods'] ?? collect();
    $selectedNeighborhoodIds = collect($row['neighborhood_ids'] ?? [])
        ->map(fn ($id) => (string) $id)
        ->all();
@endphp

<div class="driver-service-area-row card" data-index="{{ $rowIndex }}" style="padding:14px;margin-bottom:12px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
        <strong class="driver-service-area-title" style="font-size:14px;">{{ __('dashboard.address_entry') }} #{{ $rowLabelNumber }}</strong>
        <button type="button" class="btn-muted driver-service-area-remove" style="padding:6px 10px;font-size:12px;" @if(($serviceAreaRowsCount ?? 1) <= 1) hidden @endif>
            {{ __('dashboard.remove_address') }}
        </button>
    </div>

    <div class="form-grid">
        <label>
            <span class="field-label">{{ __('dashboard.governorate') }}</span>
            <select class="input driver-service-area-district" name="service_areas[{{ $rowIndex }}][district_id]" data-index="{{ $rowIndex }}">
                <option value="">{{ __('dashboard.select_governorate') }}</option>
                @foreach($governorates ?? [] as $gov)
                    <option value="{{ $gov->id }}" @selected((string) ($row['district_id'] ?? '') === (string) $gov->id)>{{ $gov->name }}</option>
                @endforeach
            </select>
        </label>

        <label>
            <span class="field-label">{{ __('dashboard.iraq_district') }}</span>
            <select class="input driver-service-area-area" name="service_areas[{{ $rowIndex }}][area_id]" data-index="{{ $rowIndex }}" @disabled(empty($row['district_id']))>
                <option value="">{{ __('dashboard.select_district') }}</option>
                @foreach($rowAreas as $area)
                    <option value="{{ $area->id }}" @selected((string) ($row['area_id'] ?? '') === (string) $area->id)>{{ $area->name }}</option>
                @endforeach
            </select>
        </label>

        <label style="grid-column: 1 / -1;">
            <span class="field-label">{{ __('dashboard.iraq_sub_district') }}</span>
            <select
                class="input driver-service-area-neighborhood"
                name="service_areas[{{ $rowIndex }}][neighborhood_ids][]"
                data-index="{{ $rowIndex }}"
                multiple
                size="6"
                @disabled(empty($row['district_id']))
            >
                @foreach($rowNeighborhoods as $neighborhood)
                    <option
                        value="{{ $neighborhood->id }}"
                        @selected(in_array((string) $neighborhood->id, $selectedNeighborhoodIds, true))
                    >{{ $neighborhood->name }}</option>
                @endforeach
            </select>
            <p style="margin:6px 0 0;font-size:12px;color:#64748b;">{{ __('dashboard.select_sub_districts_help') }}</p>
        </label>

        <label>
            <span class="field-label">{{ __('dashboard.monthly_subscription_price') }}</span>
            <input
                class="input"
                type="number"
                min="0"
                step="1"
                name="service_areas[{{ $rowIndex }}][monthly_subscription_price]"
                value="{{ $row['monthly_subscription_price'] ?? '' }}"
                placeholder="65000"
            />
            <p style="margin:6px 0 0;font-size:12px;color:#64748b;">{{ __('dashboard.monthly_subscription_price_help') }}</p>
        </label>
    </div>
</div>
