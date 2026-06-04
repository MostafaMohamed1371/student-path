@csrf
@if(($method ?? 'post') !== 'post')
    @method($method)
@endif

@if ($errors->any())
    <div class="alert" style="margin-top: 0; margin-bottom: 16px;">{{ $errors->first() }}</div>
@endif

@php
    $selectedGuardianId = (int) old('guardian_id', $student->guardian_id ?? 0);
    $selectedGuardian = ($guardians ?? collect())->firstWhere('id', $selectedGuardianId);
    $guardianIdCardDefault = old(
        'guardian_id_card_number',
        $selectedGuardian?->id_card_number ?? '',
    );
@endphp

<section class="form-section">
    <h3 class="form-section-title">{{ __('dashboard.personal_information') }}</h3>
    <div class="form-grid">
        <div>
            <label class="field-label" for="full_name">{{ __('dashboard.full_name') }}</label>
            <input class="input" id="full_name" name="full_name" value="{{ old('full_name', $student->full_name ?? '') }}" required autocomplete="name" />
            <p class="field-help">{{ __('dashboard.student_full_name_help') }}</p>
        </div>
        <div>
            <label class="field-label" for="gender">{{ __('dashboard.gender') }}</label>
            <select class="input" id="gender" name="gender" required>
                <option value="male" @selected(old('gender', $student->gender ?? 'male') === 'male')>{{ __('dashboard.male') }}</option>
                <option value="female" @selected(old('gender', $student->gender ?? 'male') === 'female')>{{ __('dashboard.female') }}</option>
            </select>
        </div>
        <div>
            <label class="field-label" for="date_of_birth">{{ __('dashboard.date_of_birth') }}</label>
            <input class="input" id="date_of_birth" name="date_of_birth" type="date" value="{{ old('date_of_birth', isset($student?->date_of_birth) ? $student->date_of_birth?->format('Y-m-d') : '') }}" />
        </div>
        <div>
            <label class="field-label" for="age">{{ __('dashboard.age') }}</label>
            <input class="input" id="age" name="age" type="number" min="3" max="30" value="{{ old('age', $student->age ?? '') }}" />
            <p class="field-help">{{ __('dashboard.age_from_dob_help') }}</p>
        </div>
        <div class="form-span-full">
            <label class="field-label" for="profile_photo">{{ __('dashboard.profile_photo') }}</label>
            <input class="input" id="profile_photo" name="profile_photo" type="file" accept=".jpg,.jpeg,.png,.webp" />
        </div>
    </div>
</section>

@include('dashboard.partials.age_from_dob_script')

<section class="form-section">
    <h3 class="form-section-title">{{ __('dashboard.school_information') }}</h3>
    <div class="form-grid">
        <div>
            <label class="field-label" for="school_id">{{ __('dashboard.school') }}</label>
            @if(auth()->user()?->is_admin)
                <select class="input" id="student_form_school_id" name="school_id" required>
                    <option value="">{{ __('dashboard.select_school') }}</option>
                    @foreach(($schools ?? collect()) as $school)
                        <option value="{{ $school->id }}" @selected((string) old('school_id', $student->school_id ?? '') === (string) $school->id)>
                            {{ $school->name_en }} @if($school->name_ar) ({{ $school->name_ar }}) @endif
                        </option>
                    @endforeach
                </select>
            @else
                @php($fixedSchoolId = (string) old('school_id', $student->school_id ?? auth()->user()?->scopingSchoolId()))
                @php($fixedSchool = ($schools ?? collect())->firstWhere('id', (int) $fixedSchoolId))
                <input type="hidden" id="student_form_school_id_hidden" name="school_id" value="{{ $fixedSchoolId }}">
                <input class="input" value="{{ $fixedSchool?->name_en ?: '—' }}" disabled />
            @endif
        </div>
        <div>
            <label class="field-label" for="grade">{{ __('dashboard.grade') }}</label>
            <input class="input" id="grade" name="grade" value="{{ old('grade', $student->grade ?? '') }}" required />
        </div>
        <div>
            <label class="field-label" for="student_phone">{{ __('dashboard.student_phone') }}</label>
            <input class="input" id="student_phone" name="student_phone" value="{{ old('student_phone', $student->student_phone ?? '') }}" required />
        </div>
    </div>
</section>

<section class="form-section">
    <h3 class="form-section-title">{{ __('dashboard.guardian_information') }}</h3>
    <div class="form-grid">
        <div>
            <label class="field-label" for="student_form_guardian_id_card">{{ __('dashboard.id_card_number') }}</label>
            <input
                class="input"
                id="student_form_guardian_id_card"
                name="guardian_id_card_number"
                value="{{ $guardianIdCardDefault }}"
                autocomplete="off"
                maxlength="64"
            />
            <p class="field-help">{{ __('dashboard.student_guardian_id_card_help') }}</p>
            <p id="student_form_guardian_id_card_status" class="field-help" style="display:none;" role="status"></p>
        </div>
        <div>
            <label class="field-label" for="relationship">{{ __('dashboard.relationship') }}</label>
            <input class="input" id="relationship" name="relationship" value="{{ old('relationship', $student->relationship ?? '') }}" required />
        </div>
        <div class="form-span-full">
            <label class="field-label" for="student_form_guardian_id">{{ __('dashboard.guardian') }}</label>
            <p class="field-help">{{ __('dashboard.student_guardian_filter_help') }}</p>
            <select class="input" id="student_form_guardian_id" name="guardian_id" required>
                <option value="">{{ __('dashboard.select_guardian') }}</option>
                @foreach(($guardians ?? collect()) as $guardian)
                    <option
                        value="{{ $guardian->id }}"
                        data-id-card="{{ $guardian->id_card_number ?? '' }}"
                        @selected((string) old('guardian_id', $student->guardian_id ?? '') === (string) $guardian->id)
                    >
                        {{ $guardian->full_name }} ({{ $guardian->phone }})
                    </option>
                @endforeach
            </select>
        </div>
    </div>
</section>

<section class="form-section">
    <h3 class="form-section-title">{{ __('dashboard.address_information') }}</h3>
    <div class="form-grid">
        <div>
            <label class="field-label" for="district_area">{{ __('dashboard.district_area') }}</label>
            <input class="input" id="district_area" name="district_area" value="{{ old('district_area', $student->district_area ?? '') }}" required />
        </div>
        <div>
            <label class="field-label" for="nearest_landmark">{{ __('dashboard.nearest_landmark') }}</label>
            <input class="input" id="nearest_landmark" name="nearest_landmark" value="{{ old('nearest_landmark', $student->nearest_landmark ?? '') }}" required />
        </div>
    </div>
</section>

<section class="form-section">
    <h3 class="form-section-title">{{ __('dashboard.location_on_map') }}</h3>
    <div class="form-grid">
        <div>
            <label class="field-label" for="latitude">{{ __('dashboard.latitude') }}</label>
            <input class="input" id="latitude" name="latitude" value="{{ old('latitude', $student->latitude ?? '') }}" />
        </div>
        <div>
            <label class="field-label" for="longitude">{{ __('dashboard.longitude') }}</label>
            <input class="input" id="longitude" name="longitude" value="{{ old('longitude', $student->longitude ?? '') }}" />
        </div>
    </div>
</section>

<section class="form-section">
    <h3 class="form-section-title">{{ __('dashboard.student_transport_settings') }}</h3>
    <div class="form-grid">
        <div>
            <label class="field-label" for="shift_period">{{ __('dashboard.student_shift_period') }}</label>
            <select class="input" id="shift_period" name="shift_period">
                <option value="">{{ __('dashboard.student_shift_period_unspecified') }}</option>
                <option value="MORNING" @selected(old('shift_period', $student->shift_period ?? '') === 'MORNING')>{{ __('dashboard.student_shift_period_morning') }}</option>
                <option value="EVENING" @selected(old('shift_period', $student->shift_period ?? '') === 'EVENING')>{{ __('dashboard.student_shift_period_evening') }}</option>
            </select>
        </div>
        <div>
            <label class="field-label" for="status">{{ __('dashboard.status') }}</label>
            <select class="input" id="status" name="status" required>
                <option value="active" @selected(old('status', $student->status ?? 'active') === 'active')>{{ __('dashboard.active') }}</option>
                <option value="inactive" @selected(old('status', $student->status ?? 'active') === 'inactive')>{{ __('dashboard.inactive') }}</option>
            </select>
        </div>
    </div>
</section>

<div class="form-actions">
    <a class="btn-muted" href="{{ route('dashboard.students.index') }}">{{ __('dashboard.cancel') }}</a>
    <button class="btn-primary" type="submit" style="width:auto;padding-inline:16px;">{{ $submitLabel ?? __('dashboard.save') }}</button>
</div>
