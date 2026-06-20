@php
    $routeModel = $route ?? null;
    $selectedSchoolId = (int) old('school_id', $routeModel?->school_id);
    $endpointSchool = $routeModel?->school ?? $schools->firstWhere('id', $selectedSchoolId);
@endphp

<label>
    <span class="field-label">{{ __('dashboard.school') }}</span>
    <select class="input" id="route_form_school_id" name="school_id" required @disabled(isset($routeModel) && !auth()->user()?->is_admin)>
        @foreach($schools as $school)
            <option
                value="{{ $school->id }}"
                data-address="{{ e($school->address ?? '') }}"
                data-latitude="{{ $school->latitude !== null ? $school->latitude : '' }}"
                data-longitude="{{ $school->longitude !== null ? $school->longitude : '' }}"
                @selected($selectedSchoolId === (int) $school->id)
            >{{ $school->name_en }}</option>
        @endforeach
    </select>
</label>

<label>
    <span class="field-label">{{ __('dashboard.trip_field_type') }}</span>
    <select class="input" id="route_form_trip_type" name="trip_type" required>
        @foreach(($tripTypes ?? []) as $tt)
            <option value="{{ $tt }}" @selected(old('trip_type', $routeModel?->trip_type) === $tt)>{{ $tt }}</option>
        @endforeach
    </select>
</label>

@include('dashboard.partials.iraq_location_fields', array_merge($locationForm ?? [], [
    'iraqLocationPrefix' => 'route',
    'fieldPrefix' => '',
    'neighborhoodMultiple' => false,
]))

<label>
    <span class="field-label">{{ __('dashboard.route_name') }}</span>
    <input class="input" name="name" value="{{ old('name', $routeModel?->name) }}" placeholder="{{ __('dashboard.route_name_optional') }}">
</label>

<label>
    <span class="field-label">{{ __('dashboard.monthly_subscription_price') }}</span>
    <input
        class="input"
        name="monthly_subscription_price"
        type="number"
        min="0"
        step="1"
        value="{{ old('monthly_subscription_price', $routeModel?->monthly_subscription_price) }}"
        placeholder="65000"
    >
    <p style="margin:6px 0 0;font-size:12px;color:#64748b;">{{ __('dashboard.route_monthly_subscription_price_help') }}</p>
</label>

<label style="grid-column:1 / -1;">
    <span class="field-label">{{ __('dashboard.route_end_address') }}</span>
    <input
        class="input"
        id="end_address"
        type="text"
        value="{{ old('end_address', $endpointSchool?->address) }}"
        readonly
        placeholder="{{ __('dashboard.route_end_address_placeholder') }}"
    >
</label>

<label>
    <span class="field-label">{{ __('dashboard.route_end_latitude') }}</span>
    <input
        class="input"
        id="end_latitude"
        type="number"
        step="0.0000001"
        value="{{ old('end_latitude', $endpointSchool?->latitude) }}"
        readonly
    >
</label>

<label>
    <span class="field-label">{{ __('dashboard.route_end_longitude') }}</span>
    <input
        class="input"
        id="end_longitude"
        type="number"
        step="0.0000001"
        value="{{ old('end_longitude', $endpointSchool?->longitude) }}"
        readonly
    >
</label>

<label style="grid-column:1 / -1;">
    <span class="field-label">{{ __('dashboard.route_start_address') }}</span>
    <input class="input" id="start_address" name="start_address" value="{{ old('start_address', $routeModel?->start_address) }}" required>
    <p style="margin:6px 0 0;font-size:12px;color:#64748b;">{{ __('dashboard.route_map_click_help') }}</p>
</label>

<div style="grid-column:1 / -1;">
    <div id="route_start_map" style="height:320px;border:1px solid #cbd5e1;border-radius:10px;"></div>
</div>

<label>
    <span class="field-label">{{ __('dashboard.latitude') }}</span>
    <input class="input" id="start_latitude" name="start_latitude" type="number" step="0.0000001" value="{{ old('start_latitude', $routeModel?->start_latitude) }}" required readonly>
</label>

<label>
    <span class="field-label">{{ __('dashboard.longitude') }}</span>
    <input class="input" id="start_longitude" name="start_longitude" type="number" step="0.0000001" value="{{ old('start_longitude', $routeModel?->start_longitude) }}" required readonly>
</label>

<label>
    <span class="field-label">{{ __('dashboard.status') }}</span>
    <select class="input" name="status" required>
        <option value="active" @selected(old('status', $routeModel?->status ?? 'active') === 'active')>{{ __('dashboard.active') }}</option>
        <option value="inactive" @selected(old('status', $routeModel?->status) === 'inactive')>{{ __('dashboard.inactive') }}</option>
    </select>
</label>
