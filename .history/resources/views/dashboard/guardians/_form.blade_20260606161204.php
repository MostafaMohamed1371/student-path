@csrf
@if(($method ?? 'post') !== 'post')
    @method($method)
@endif

@if ($errors->any())
    <div class="alert" style="margin-top: 0; margin-bottom: 16px;">{{ $errors->first() }}</div>
@endif

@php($isCreate = empty($guardian))
@php($homeLocation = $homeLocation ?? null)

<div class="form-grid">
    <div>
        <label class="field-label" for="school_id">{{ __('dashboard.school') }}</label>
        @if(auth()->user()?->is_admin)
            <select class="input" id="guardian_form_school_id" name="school_id" required>
                <option value="">{{ __('dashboard.select_school') }}</option>
                @foreach(($schools ?? collect()) as $school)
                    <option value="{{ $school->id }}" @selected((string) old('school_id', $guardian->school_id ?? '') === (string) $school->id)>
                        {{ $school->name_en }} @if($school->name_ar) ({{ $school->name_ar }}) @endif
                    </option>
                @endforeach
            </select>
        @else
            @php($fixedSchoolId = (string) old('school_id', $guardian->school_id ?? auth()->user()?->scopingSchoolId()))
            @php($fixedSchool = ($schools ?? collect())->firstWhere('id', (int) $fixedSchoolId))
            <input type="hidden" id="guardian_form_school_id_hidden" name="school_id" value="{{ $fixedSchoolId }}">
            <input class="input" value="{{ $fixedSchool?->name_en ?: '—' }}" disabled />
        @endif
    </div>
    <div>
        <label class="field-label" for="guardian_form_id_card_number">{{ __('dashboard.id_card_number') }}</label>
        <input
            class="input"
            id="guardian_form_id_card_number"
            name="id_card_number"
            value="{{ old('id_card_number', $guardian->id_card_number ?? '') }}"
            autocomplete="off"
            maxlength="64"
        />
        @if($isCreate)
            <p class="field-help">{{ __('dashboard.guardian_form_id_card_help') }}</p>
            <p id="guardian_form_id_card_status" class="field-help" style="display:none;" role="status"></p>
        @endif
    </div>

    <div>
        <label class="field-label" for="guardian_form_full_name">{{ __('dashboard.guardian_name') }}</label>
        <input class="input" id="guardian_form_full_name" name="full_name" value="{{ old('full_name', $guardian->full_name ?? '') }}" required />
    </div>
    <div>
        <label class="field-label" for="guardian_form_phone">{{ __('dashboard.phone') }}</label>
        <input class="input" id="guardian_form_phone" name="phone" value="{{ old('phone', $guardian->phone ?? '') }}" required />
    </div>
    <div>
        <label class="field-label" for="guardian_form_backup_phone">{{ __('dashboard.guardian_backup_phone') }}</label>
        <input class="input" id="guardian_form_backup_phone" name="backup_phone" value="{{ old('backup_phone', $guardian->backup_phone ?? '') }}" />
    </div>
    <div>
        <label class="field-label" for="guardian_form_status">{{ __('dashboard.status') }}</label>
        <select class="input" id="guardian_form_status" name="status" required>
            <option value="active" @selected(old('status', $guardian->status ?? 'active') === 'active')>{{ __('dashboard.active') }}</option>
            <option value="inactive" @selected(old('status', $guardian->status ?? 'active') === 'inactive')>{{ __('dashboard.inactive') }}</option>
        </select>
    </div>
</div>

<section class="form-section" style="margin-top: 20px;">
    <h3 class="form-section-title">{{ __('dashboard.address_information') }}</h3>
    <p class="field-help" style="margin: 0 0 12px;">{{ __('dashboard.guardian_parent_home_location_help') }}</p>
    <div class="form-grid">
        @include('dashboard.partials.iraq_location_fields', ['neighborhoodMultiple' => true])
    </div>
    <input type="hidden" id="guardian_home_district_area" name="home_district_area" value="{{ old('home_district_area', optional($homeLocation)->district_area) }}" />
    <input type="hidden" id="guardian_home_nearest_landmark" name="home_nearest_landmark" value="{{ old('home_nearest_landmark', optional($homeLocation)->nearest_landmark ?: optional($homeLocation)->formatted_address) }}" />
    <input type="hidden" id="guardian_home_formatted_address" name="home_formatted_address" value="{{ old('home_formatted_address', optional($homeLocation)->nearest_landmark ?: optional($homeLocation)->formatted_address) }}" />
</section>

<section class="form-section">
    <h3 class="form-section-title">{{ __('dashboard.location_on_map') }}</h3>
    <p class="field-help" style="margin: 0 0 12px;">{{ __('dashboard.guardian_parent_home_location_help') }}</p>
    <div id="guardian-home-map" style="height: 260px; border: 1px solid #cbd5e1; border-radius: 10px; margin-bottom: 16px;"></div>
    <div class="form-grid">
        <div>
            <label class="field-label" for="guardian_home_latitude">{{ __('dashboard.latitude') }}</label>
            <input
                class="input"
                id="guardian_home_latitude"
                name="home_latitude"
                type="number"
                step="0.0000001"
                min="-90"
                max="90"
                value="{{ old('home_latitude', $homeLocation?->latitude ?? '') }}"
            />
        </div>
        <div>
            <label class="field-label" for="guardian_home_longitude">{{ __('dashboard.longitude') }}</label>
            <input
                class="input"
                id="guardian_home_longitude"
                name="home_longitude"
                type="number"
                step="0.0000001"
                min="-180"
                max="180"
                value="{{ old('home_longitude', $homeLocation?->longitude ?? '') }}"
            />
        </div>
    </div>
</section>

<div class="form-actions">
    <a class="btn-muted" href="{{ route('dashboard.guardians.index') }}">{{ __('dashboard.cancel') }}</a>
    <button class="btn-primary" type="submit" style="width:auto;padding-inline:16px;">{{ $submitLabel ?? __('dashboard.save') }}</button>
</div>
