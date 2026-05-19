@csrf
@if(($method ?? 'post') !== 'post')
    @method($method)
@endif

@if ($errors->any())
    <div class="alert" style="margin-top: 0; margin-bottom: 16px;">{{ $errors->first() }}</div>
@endif

<div class="form-grid">
    <div>
        <label class="field-label" for="school_id">{{ __('dashboard.school') }}</label>
        @if(auth()->user()?->is_admin)
            <select class="input" id="school_id" name="school_id" required>
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
            <input type="hidden" name="school_id" value="{{ $fixedSchoolId }}">
            <input class="input" value="{{ $fixedSchool?->name_en ?: '—' }}" disabled />
        @endif
    </div>
    <div></div>

    <div>
        <label class="field-label" for="full_name">{{ __('dashboard.guardian_name') }}</label>
        <input class="input" id="full_name" name="full_name" value="{{ old('full_name', $guardian->full_name ?? '') }}" required />
    </div>
    <div>
        <label class="field-label" for="id_card_number">{{ __('dashboard.id_card_number') }}</label>
        <input class="input" id="id_card_number" name="id_card_number" value="{{ old('id_card_number', $guardian->id_card_number ?? '') }}" />
    </div>
    <div>
        <label class="field-label" for="phone">{{ __('dashboard.phone') }}</label>
        <input class="input" id="phone" name="phone" value="{{ old('phone', $guardian->phone ?? '') }}" required />
    </div>
    <div>
        <label class="field-label" for="backup_phone">{{ __('dashboard.guardian_backup_phone') }}</label>
        <input class="input" id="backup_phone" name="backup_phone" value="{{ old('backup_phone', $guardian->backup_phone ?? '') }}" />
    </div>
    <div>
        <label class="field-label" for="status">{{ __('dashboard.status') }}</label>
        <select class="input" id="status" name="status" required>
            <option value="active" @selected(old('status', $guardian->status ?? 'active') === 'active')>{{ __('dashboard.active') }}</option>
            <option value="inactive" @selected(old('status', $guardian->status ?? 'active') === 'inactive')>{{ __('dashboard.inactive') }}</option>
        </select>
    </div>
</div>

<div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;">
    <a class="btn-muted" href="{{ route('dashboard.guardians.index') }}">{{ __('dashboard.cancel') }}</a>
    <button class="btn-primary" type="submit" style="width:auto;padding-inline:16px;">{{ $submitLabel ?? __('dashboard.save') }}</button>
</div>
