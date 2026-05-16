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
        <label class="field-label" for="full_name">{{ __('dashboard.full_name') }}</label>
        <input class="input" id="full_name" name="full_name" value="{{ old('full_name', $student->full_name ?? '') }}" required />
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
    </div>
</div>

<div style="margin-top: 12px;">
    <label class="field-label" for="profile_photo">{{ __('dashboard.profile_photo') }}</label>
    <input class="input" id="profile_photo" name="profile_photo" type="file" accept=".jpg,.jpeg,.png,.webp" />
</div>

<hr style="margin: 20px 0; border: 0; border-top: 1px solid #e2e8f0;">
<div style="margin-top: 4px;">
    <h3 style="margin: 0 0 10px; font-size: 18px;">{{ __('dashboard.school_information') }}</h3>
</div>

<div class="form-grid">
    <div>
        <label class="field-label" for="school_id">{{ __('dashboard.school') }}</label>
        @if(auth()->user()?->is_admin)
            <select class="input" id="school_id" name="school_id" required>
                <option value="">{{ __('dashboard.select_school') }}</option>
                @foreach(($schools ?? collect()) as $school)
                    <option value="{{ $school->id }}" @selected((string) old('school_id', $student->school_id ?? '') === (string) $school->id)>
                        {{ $school->name_en }} @if($school->name_ar) ({{ $school->name_ar }}) @endif
                    </option>
                @endforeach
            </select>
        @else
            @php($fixedSchoolId = (string) old('school_id', $student->school_id ?? auth()->user()?->school_id))
            @php($fixedSchool = ($schools ?? collect())->firstWhere('id', (int) $fixedSchoolId))
            <input type="hidden" name="school_id" value="{{ $fixedSchoolId }}">
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

<hr style="margin: 20px 0; border: 0; border-top: 1px solid #e2e8f0;">
<div style="margin-top: 4px;">
    <h3 style="margin: 0 0 10px; font-size: 18px;">{{ __('dashboard.guardian_information') }}</h3>
</div>

<div class="form-grid">
    <div>
        <label class="field-label" for="guardian_id">{{ __('dashboard.guardian') }}</label>
        <select class="input" id="guardian_id" name="guardian_id" required>
            <option value="">{{ __('dashboard.select_guardian') }}</option>
            @foreach(($guardians ?? collect()) as $guardian)
                <option value="{{ $guardian->id }}" @selected((string) old('guardian_id', $student->guardian_id ?? '') === (string) $guardian->id)>
                    {{ $guardian->full_name }} ({{ $guardian->phone }})
                </option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="field-label" for="relationship">{{ __('dashboard.relationship') }}</label>
        <input class="input" id="relationship" name="relationship" value="{{ old('relationship', $student->relationship ?? '') }}" required />
    </div>
</div>

<hr style="margin: 20px 0; border: 0; border-top: 1px solid #e2e8f0;">
<div style="margin-top: 4px;">
    <h3 style="margin: 0 0 10px; font-size: 18px;">{{ __('dashboard.address_information') }}</h3>
</div>

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

<div style="margin-top: 16px;">
    <h3 style="margin: 0 0 10px; font-size: 18px;">{{ __('dashboard.location_on_map') }}</h3>
</div>

<div class="form-grid">
    <div>
        <label class="field-label" for="latitude">{{ __('dashboard.latitude') }}</label>
        <input class="input" id="latitude" name="latitude" value="{{ old('latitude', $student->latitude ?? '') }}" />
    </div>
    <div>
        <label class="field-label" for="longitude">{{ __('dashboard.longitude') }}</label>
        <input class="input" id="longitude" name="longitude" value="{{ old('longitude', $student->longitude ?? '') }}" />
    </div>
    <div>
        <label class="field-label" for="shift_period">{{ __('dashboard.student_shift_period') }}</label>
        <select class="input" id="shift_period" name="shift_period">
            <option value="">{{ __('dashboard.student_shift_period_unspecified') }}</option>
            <option value="MORNING" @selected(old('shift_period', $student->shift_period ?? '') === 'MORNING')>{{ __('dashboard.student_shift_period_morning') }}</option>
            <option value="EVENING" @selected(old('shift_period', $student->shift_period ?? '') === 'EVENING')>{{ __('dashboard.student_shift_period_evening') }}</option>
            <option value="BOTH" @selected(old('shift_period', $student->shift_period ?? '') === 'BOTH')>{{ __('dashboard.student_shift_period_both') }}</option>
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

<div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;">
    <a class="btn-muted" href="{{ route('dashboard.students.index') }}">{{ __('dashboard.cancel') }}</a>
    <button class="btn-primary" type="submit" style="width:auto;padding-inline:16px;">{{ $submitLabel ?? __('dashboard.save') }}</button>
</div>
