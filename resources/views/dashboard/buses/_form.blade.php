@if ($errors->any())
    <div class="alert" style="margin-top: 0; margin-bottom: 16px;">{{ $errors->first() }}</div>
@endif

@php
    $selectedSchoolId = (int) old('school_id', $bus?->driver?->school_id ?? auth()->user()?->scopingSchoolId() ?? ($schools ?? collect())->first()?->id ?? 0);
@endphp

<div class="form-grid">
    <div>
        <label class="field-label" for="bus_form_school_id">{{ __('dashboard.school') }}</label>
        @if(auth()->user()?->is_admin)
            <select class="input" id="bus_form_school_id" name="school_id" required>
                <option value="">{{ __('dashboard.select_school') }}</option>
                @foreach(($schools ?? collect()) as $school)
                    <option value="{{ $school->id }}" @selected($selectedSchoolId === (int) $school->id)>
                        {{ $school->name_en }}
                    </option>
                @endforeach
            </select>
        @else
            @php($fixedSchool = ($schools ?? collect())->firstWhere('id', $selectedSchoolId))
            <input type="hidden" id="bus_form_school_id" name="school_id" value="{{ $selectedSchoolId }}">
            <input class="input" value="{{ $fixedSchool?->name_en ?: '—' }}" disabled>
        @endif
    </div>

    <div>
        <label class="field-label" for="driver_id">{{ __('dashboard.driver') }}</label>
        <select class="input" id="driver_id" name="driver_id" required>
            <option value="">{{ __('dashboard.bus_select_driver_after_school') }}</option>
            @foreach(($drivers ?? collect()) as $driver)
                <option value="{{ $driver->id }}" @selected((string) old('driver_id', $bus?->driver_id ?? '') === (string) $driver->id)>
                    {{ $driver->first_name }} {{ $driver->father_name }} {{ $driver->last_name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="field-label" for="name">{{ __('dashboard.bus_name') }}</label>
        <input class="input" id="name" name="name" value="{{ old('name', $bus?->name) }}" required>
    </div>

    <div>
        <label class="field-label" for="type">{{ __('dashboard.bus_type') }}</label>
        <input class="input" id="type" name="type" value="{{ old('type', $bus?->type) }}" required>
    </div>

    <div>
        <label class="field-label" for="vehicle_model_year">{{ __('dashboard.vehicle_model_year') }}</label>
        <input class="input" id="vehicle_model_year" name="vehicle_model_year" type="number" min="1980" max="{{ date('Y') + 1 }}" value="{{ old('vehicle_model_year', $bus?->vehicle_model_year) }}" placeholder="e.g. 2022">
    </div>

    <div>
        <label class="field-label" for="ac_status">{{ __('dashboard.ac_status') }}</label>
        <select class="input" id="ac_status" name="ac_status">
            <option value="">{{ __('dashboard.ac_status_unspecified') }}</option>
            <option value="yes" @selected(old('ac_status', $bus?->ac_status) === 'yes')>{{ __('dashboard.ac_status_yes') }}</option>
            <option value="no" @selected(old('ac_status', $bus?->ac_status) === 'no')>{{ __('dashboard.ac_status_no') }}</option>
        </select>
    </div>

    <div>
        <label class="field-label" for="city">{{ __('dashboard.bus_city') }}</label>
        <input class="input" id="city" name="city" value="{{ old('city', $bus?->city) }}" required>
    </div>

    <div>
        <label class="field-label" for="number">{{ __('dashboard.bus_number') }}</label>
        <input class="input" id="number" name="number" value="{{ old('number', $bus?->number) }}" required>
    </div>

    <div>
        <label class="field-label" for="color">{{ __('dashboard.bus_color') }}</label>
        @php($savedColor = old('color', $bus?->color ?: 'Yellow'))
        @php($isHexColor = preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $savedColor) === 1)

        <div style="display:flex;gap:14px;margin-bottom:8px;">
            <label style="display:inline-flex;align-items:center;gap:6px;">
                <input type="radio" name="color_mode" value="pick" {{ $isHexColor ? 'checked' : '' }}>
                <span>Pick color</span>
            </label>
            <label style="display:inline-flex;align-items:center;gap:6px;">
                <input type="radio" name="color_mode" value="type" {{ $isHexColor ? '' : 'checked' }}>
                <span>Type color</span>
            </label>
        </div>

        <input type="hidden" name="color" id="color-hidden" value="{{ $savedColor }}">

        <input
            class="input"
            id="color-picker"
            type="color"
            value="{{ $isHexColor ? $savedColor : '#facc15' }}"
            style="{{ $isHexColor ? '' : 'display:none;' }}"
        >

        <input
            class="input"
            id="color-text"
            type="text"
            value="{{ $savedColor }}"
            placeholder="#facc15 or Yellow"
            list="bus-color-options"
            style="{{ $isHexColor ? 'display:none;' : '' }}"
        >
    </div>

    <div>
        <label class="field-label" for="capacity">{{ __('dashboard.bus_capacity') }}</label>
        <input class="input" id="capacity" name="capacity" type="number" min="1" value="{{ old('capacity', $bus?->capacity) }}" required>
    </div>

    <div>
        <label class="field-label" for="fuel_type">{{ __('dashboard.fuel_type') }}</label>
        <input class="input" id="fuel_type" name="fuel_type" value="{{ old('fuel_type', $bus?->fuel_type) }}" required>
    </div>

    <div>
        <label class="field-label" for="status">{{ __('dashboard.bus_status') }}</label>
        <input class="input" id="status" name="status" value="{{ old('status', $bus?->status) }}" required>
    </div>
</div>

<div style="display:flex;gap:20px;margin-top:14px;">
    <label style="display:inline-flex;align-items:center;gap:8px;">
        <input type="checkbox" name="annual_status" value="1" @checked(old('annual_status', $bus?->annual_status ?? true))>
        <span>{{ __('dashboard.annual_status') }}</span>
    </label>

    <label style="display:inline-flex;align-items:center;gap:8px;">
        <input type="checkbox" name="insurance" value="1" @checked(old('insurance', $bus?->insurance ?? true))>
        <span>{{ __('dashboard.insurance') }}</span>
    </label>
</div>

<div style="display:flex;justify-content:flex-end;margin-top:16px;">
    <button class="btn-primary" type="submit" style="width:auto;padding-inline:16px;">{{ $submitLabel }}</button>
</div>

<script>
    (function () {
        const picker = document.getElementById('color-picker');
        const text = document.getElementById('color-text');
        const hidden = document.getElementById('color-hidden');
        const radios = document.querySelectorAll('input[name="color_mode"]');

        if (!picker || !text || !hidden || radios.length === 0) {
            return;
        }

        const syncFromPicker = () => { hidden.value = picker.value; };
        const syncFromText = () => { hidden.value = text.value; };

        picker.addEventListener('input', syncFromPicker);
        text.addEventListener('input', syncFromText);

        radios.forEach((radio) => {
            radio.addEventListener('change', () => {
                const mode = document.querySelector('input[name="color_mode"]:checked')?.value;
                if (mode === 'pick') {
                    picker.style.display = '';
                    text.style.display = 'none';
                    hidden.value = picker.value;
                } else {
                    picker.style.display = 'none';
                    text.style.display = '';
                    hidden.value = text.value;
                }
            });
        });
    })();
</script>
