@csrf
@if(($method ?? 'post') !== 'post')
    @method($method)
@endif

@if ($errors->any())
    <div class="alert" style="margin-top: 0; margin-bottom: 16px;">{{ $errors->first() }}</div>
@endif

<div style="margin-top: 4px;">
    <h3 style="margin: 0 0 10px; font-size: 18px;">{{ __('dashboard.personal_information') }}</h3>
</div>

<div class="form-grid">
    <div>
        <label class="field-label" for="school_id">{{ __('dashboard.school') }}</label>
        @if(auth()->user()?->is_admin)
            <select class="input" id="school_id" name="school_id" required>
                <option value="">{{ __('dashboard.select_school') }}</option>
                @foreach(($schools ?? collect()) as $school)
                    <option value="{{ $school->id }}" @selected((string) old('school_id', $driver->school_id ?? '') === (string) $school->id)>
                        {{ $school->name_en }} @if($school->name_ar) ({{ $school->name_ar }}) @endif
                    </option>
                @endforeach
            </select>
        @else
            @php($fixedSchoolId = (string) old('school_id', $driver->school_id ?? auth()->user()?->scopingSchoolId()))
            @php($fixedSchool = ($schools ?? collect())->firstWhere('id', (int) $fixedSchoolId))
            <input type="hidden" name="school_id" value="{{ $fixedSchoolId }}">
            <input class="input" value="{{ $fixedSchool?->name_en ?: '—' }}" disabled />
        @endif
    </div>
    <div></div>

    <div>
        <label class="field-label" for="bus_id">{{ __('dashboard.bus') }}</label>
        <select class="input" id="bus_id" name="bus_id">
            <option value="">{{ __('dashboard.driver_select_bus') }}</option>
            @foreach(($availableBuses ?? collect()) as $busOption)
                <option value="{{ $busOption->id }}" @selected((string) old('bus_id', $driver?->bus?->id ?? '') === (string) $busOption->id)>
                    {{ $busOption->number }} — {{ $busOption->name }}
                </option>
            @endforeach
        </select>
        <p class="help">{{ __('dashboard.driver_bus_assignment_help') }}</p>
    </div>

    <div>
        <label class="field-label" for="shift_period">{{ __('dashboard.shift_period') }}</label>
        <select class="input" id="shift_period" name="shift_period" required>
            <option value="MORNING" @selected(old('shift_period', $driver?->shift_period ?? 'MORNING') === 'MORNING')>{{ __('dashboard.shift_period_morning') }}</option>
            <option value="EVENING" @selected(old('shift_period', $driver?->shift_period ?? 'MORNING') === 'EVENING')>{{ __('dashboard.shift_period_evening') }}</option>
            <option value="BOTH" @selected(old('shift_period', $driver?->shift_period ?? 'MORNING') === 'BOTH')>{{ __('dashboard.shift_period_both') }}</option>
        </select>
    </div>

    <div>
        <label class="field-label" for="first_name">{{ __('dashboard.first_name') }}</label>
        <input class="input" id="first_name" name="first_name" value="{{ old('first_name', $driver->first_name ?? '') }}" required />
    </div>
    <div>
        <label class="field-label" for="father_name">{{ __('dashboard.father_name') }}</label>
        <input class="input" id="father_name" name="father_name" value="{{ old('father_name', $driver->father_name ?? '') }}" required />
    </div>
    <div>
        <label class="field-label" for="grandfather_name">{{ __('dashboard.grandfather_name') }}</label>
        <input class="input" id="grandfather_name" name="grandfather_name" value="{{ old('grandfather_name', $driver->grandfather_name ?? '') }}" required />
    </div>
    <div>
        <label class="field-label" for="last_name">{{ __('dashboard.last_name') }}</label>
        <input class="input" id="last_name" name="last_name" value="{{ old('last_name', $driver->last_name ?? '') }}" required />
    </div>
    <div>
        <label class="field-label" for="age">{{ __('dashboard.age') }}</label>
        <input class="input" id="age" name="age" type="number" min="18" max="80" value="{{ old('age', $driver->age ?? '') }}" required />
    </div>
    <div style="grid-column: 1 / -1;">
        <label class="field-label" for="profile_image">{{ __('dashboard.driver_profile_image') }}</label>
        @if(($driver?->user?->image ?? null))
            <div style="margin: 0 0 10px;">
                <img src="{{ asset('storage/'.$driver->user->image) }}" alt="" width="120" height="120" style="object-fit: cover; border-radius: 10px; border: 1px solid #e2e8f0;">
            </div>
        @endif
        <input class="input" id="profile_image" name="profile_image" type="file" accept=".jpg,.jpeg,.png,.webp" />
        <p style="margin: 6px 0 0; font-size: 12px; color: #64748b;">{{ __('dashboard.driver_profile_image_help') }}</p>
    </div>
</div>

<hr style="margin: 20px 0; border: 0; border-top: 1px solid #e2e8f0;">
<div style="margin-top: 4px;">
    <h3 style="margin: 0 0 10px; font-size: 18px;">{{ __('dashboard.address_information') }}</h3>
</div>

@include('dashboard.drivers._service_areas_fields', [
    'serviceAreaRows' => $serviceAreaRows ?? [],
    'governorates' => $governorates ?? collect(),
])

<hr style="margin: 20px 0; border: 0; border-top: 1px solid #e2e8f0;">
<div style="margin-top: 4px;">
    <h3 style="margin: 0 0 10px; font-size: 18px;">{{ __('dashboard.official_documents') }}</h3>
</div>

<div class="form-grid">
    <div>
        <label class="field-label" for="id_card_number">{{ __('dashboard.id_card_number') }}</label>
        <input class="input" id="id_card_number" name="id_card_number" value="{{ old('id_card_number', $driver->id_card_number ?? '') }}" required />
    </div>
    <div>
        <label class="field-label" for="license_number">{{ __('dashboard.license_number') }}</label>
        <input class="input" id="license_number" name="license_number" value="{{ old('license_number', $driver->license_number ?? '') }}" required />
    </div>
</div>

<hr style="margin: 20px 0; border: 0; border-top: 1px solid #e2e8f0;">
<div style="margin-top: 4px;">
    <h3 style="margin: 0 0 10px; font-size: 18px;">{{ __('dashboard.contact_information') }}</h3>
</div>

<div class="form-grid">
    <div>
        <label class="field-label" for="primary_phone">{{ __('dashboard.primary_phone') }}</label>
        <input class="input" id="primary_phone" name="primary_phone" value="{{ old('primary_phone', $driver->primary_phone ?? '') }}" required />
    </div>
    <div>
        <label class="field-label" for="emergency_phone">{{ __('dashboard.emergency_phone') }}</label>
        <input class="input" id="emergency_phone" name="emergency_phone" value="{{ old('emergency_phone', $driver->emergency_phone ?? '') }}" required />
    </div>
    <div>
        <label class="field-label" for="residential_address">{{ __('dashboard.residential_address') }}</label>
        <input class="input" id="residential_address" name="residential_address" value="{{ old('residential_address', $driver->residential_address ?? '') }}" required />
    </div>
    <div>
        <label class="field-label" for="status">{{ __('dashboard.status') }}</label>
        <select class="input" id="status" name="status" required>
            <option value="active" @selected(old('status', $driver->status ?? 'active') === 'active')>{{ __('dashboard.active') }}</option>
            <option value="inactive" @selected(old('status', $driver->status ?? 'active') === 'inactive')>{{ __('dashboard.inactive') }}</option>
        </select>
    </div>
</div>

<hr style="margin: 20px 0; border: 0; border-top: 1px solid #e2e8f0;">
<div style="margin-top: 4px;">
    <h3 style="margin: 0 0 10px; font-size: 18px;">{{ __('dashboard.driver_account_ratings') }}</h3>
    <p style="margin: 0 0 10px; font-size: 13px; color: #64748b;">{{ __('dashboard.driver_ratings_help') }}</p>
</div>
<div class="form-grid">
    <div>
        <label class="field-label" for="rating_avg">{{ __('dashboard.driver_rating_avg') }}</label>
        <input class="input" id="rating_avg" name="rating_avg" type="number" min="0" max="5" step="0.1"
            value="{{ old('rating_avg', $driver?->user !== null ? $driver->user->rate : '') }}" placeholder="0–5" />
    </div>
    <div>
        <label class="field-label" for="rating_count">{{ __('dashboard.driver_rating_count') }}</label>
        <input class="input" id="rating_count" name="rating_count" type="number" min="0" step="1"
            value="{{ old('rating_count', $driver?->user !== null ? $driver->user->votes : '') }}" placeholder="0" />
    </div>
</div>

<hr style="margin: 20px 0; border: 0; border-top: 1px solid #e2e8f0;">
<div style="margin-top: 4px;">
    <h3 style="margin: 0 0 10px; font-size: 18px;">{{ __('dashboard.documents_upload') }}</h3>
</div>

<div class="form-grid">
    <div>
        <label class="field-label" for="id_card_image">{{ __('dashboard.id_card_image') }}</label>
        <input class="input" id="id_card_image" name="id_card_image" type="file" accept=".jpg,.jpeg,.png,.webp" />
    </div>
    <div>
        <label class="field-label" for="license_image">{{ __('dashboard.license_image') }}</label>
        <input class="input" id="license_image" name="license_image" type="file" accept=".jpg,.jpeg,.png,.webp" />
    </div>
</div>

<div style="margin-top: 12px;">
    <label class="field-label" for="non_conviction_certificate">{{ __('dashboard.non_conviction_certificate') }}</label>
    <input class="input" id="non_conviction_certificate" name="non_conviction_certificate" type="file" />
</div>

<div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;">
    <a class="btn-muted" href="{{ route('dashboard.drivers.index') }}">{{ __('dashboard.cancel') }}</a>
    <button class="btn-primary" type="submit" style="width:auto;padding-inline:16px;">{{ $submitLabel ?? __('dashboard.save') }}</button>
</div>
