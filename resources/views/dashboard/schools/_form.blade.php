@csrf
@if(($method ?? 'post') !== 'post')
    @method($method)
@endif

@if ($errors->any())
    <div class="alert" style="margin-top: 0; margin-bottom: 16px;">{{ $errors->first() }}</div>
@endif

<div class="form-grid">
    <div>
        <label class="field-label" for="name_ar">{{ __('dashboard.school_name_ar') }}</label>
        <input class="input" id="name_ar" name="name_ar" value="{{ old('name_ar', $school->name_ar ?? '') }}" required />
    </div>

    <div>
        <label class="field-label" for="name_en">{{ __('dashboard.school_name_en') }}</label>
        <input class="input" id="name_en" name="name_en" value="{{ old('name_en', $school->name_en ?? '') }}" required />
    </div>

    @include('dashboard.partials.iraq_location_fields', array_merge($locationForm ?? [], [
        'iraqLocationPrefix' => 'school',
        'fieldPrefix' => '',
        'neighborhoodMultiple' => false,
    ]))

    <div class="form-span-full">
        <label class="field-label" for="address">{{ __('dashboard.address') }}</label>
        <input class="input" id="address" name="address" value="{{ old('address', $school->address ?? '') }}" required />
        <p class="help" style="margin:6px 0 0;">{{ __('dashboard.school_map_click_help') }}</p>
    </div>
</div>

<div style="margin-top: 8px;">
    <div id="school-map" style="height: 260px; border: 1px solid #cbd5e1; border-radius: 10px;"></div>
</div>

<div class="form-grid" style="margin-top: 16px;">
    <div>
        <label class="field-label" for="latitude">{{ __('dashboard.latitude') }}</label>
        <input class="input" id="latitude" name="latitude" type="number" step="0.0000001" min="-90" max="90" value="{{ old('latitude', $school->latitude ?? '') }}" />
    </div>

    <div>
        <label class="field-label" for="longitude">{{ __('dashboard.longitude') }}</label>
        <input class="input" id="longitude" name="longitude" type="number" step="0.0000001" min="-180" max="180" value="{{ old('longitude', $school->longitude ?? '') }}" />
    </div>

    <div>
        <label class="field-label" for="status">{{ __('dashboard.status') }}</label>
        <select class="input" id="status" name="status" required>
            <option value="active" @selected(old('status', $school->status ?? 'active') === 'active')>{{ __('dashboard.active') }}</option>
            <option value="inactive" @selected(old('status', $school->status ?? 'active') === 'inactive')>{{ __('dashboard.inactive') }}</option>
        </select>
    </div>
</div>

<div style="margin-top: 12px;">
    <h3 style="margin: 0 0 10px; font-size: 18px;">{{ __('dashboard.contact_information') }}</h3>
</div>

<div class="form-grid">
    <div>
        <label class="field-label" for="principal_name">{{ __('dashboard.principal_name') }}</label>
        <input class="input" id="principal_name" name="principal_name" value="{{ old('principal_name', $school->principal_name ?? '') }}" />
    </div>

    <div>
        <label class="field-label" for="admin_phone">{{ __('dashboard.admin_phone') }}</label>
        <input class="input" id="admin_phone" name="admin_phone" value="{{ old('admin_phone', $school->admin_phone ?? '') }}" />
    </div>

    <div>
        <label class="field-label" for="authorized_person_name">{{ __('dashboard.authorized_person_name') }}</label>
        <input class="input" id="authorized_person_name" name="authorized_person_name" value="{{ old('authorized_person_name', $school->authorized_person_name ?? '') }}" />
    </div>

    <div>
        <label class="field-label" for="authorized_person_phone">{{ __('dashboard.authorized_person_phone') }}</label>
        <input class="input" id="authorized_person_phone" name="authorized_person_phone" value="{{ old('authorized_person_phone', $school->authorized_person_phone ?? '') }}" />
    </div>
</div>

<div style="margin-top: 12px;">
    <h3 style="margin: 0 0 10px; font-size: 18px;">{{ __('dashboard.complaints_support_contact') }}</h3>
    <p class="help" style="margin: 0 0 10px;">{{ __('dashboard.complaints_support_contact_help') }}</p>
</div>

<div class="form-grid">
    <div>
        <label class="field-label" for="complaints_support_phone">{{ __('dashboard.complaints_support_phone') }}</label>
        <input class="input" id="complaints_support_phone" name="complaints_support_phone" value="{{ old('complaints_support_phone', $school->complaints_support_phone ?? '') }}" placeholder="6677" />
    </div>

    <div>
        <label class="field-label" for="complaints_support_whatsapp">{{ __('dashboard.complaints_support_whatsapp') }}</label>
        <input class="input" id="complaints_support_whatsapp" name="complaints_support_whatsapp" value="{{ old('complaints_support_whatsapp', $school->complaints_support_whatsapp ?? '') }}" placeholder="+9647701234567" />
    </div>

    <div style="grid-column: 1 / -1;">
        <label class="field-label" for="complaints_support_hours">{{ __('dashboard.complaints_support_hours') }}</label>
        <input class="input" id="complaints_support_hours" name="complaints_support_hours" value="{{ old('complaints_support_hours', $school->complaints_support_hours ?? '') }}" />
    </div>
</div>

<div style="margin-top: 12px;">
    <label class="field-label" for="notes">{{ __('dashboard.notes') }}</label>
    <textarea class="input" id="notes" name="notes" rows="4">{{ old('notes', $school->notes ?? '') }}</textarea>
</div>

<div style="margin-top: 12px;">
    <label class="field-label" for="attachment">{{ __('dashboard.attachment') }}</label>
    <input class="input" id="attachment" name="attachment" type="file" />
</div>

<div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;">
    <a class="btn-muted" href="{{ route('dashboard.schools.index') }}">{{ __('dashboard.cancel') }}</a>
    <button class="btn-primary" type="submit" style="width:auto;padding-inline:16px;">{{ $submitLabel ?? __('dashboard.save') }}</button>
</div>
