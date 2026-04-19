@csrf
@if(($method ?? 'post') !== 'post')
    @method($method)
@endif

<div class="form-grid">
    <div>
        <label class="field-label" for="name">{{ __('dashboard.name') }}</label>
        <input class="input" id="name" name="name" value="{{ old('name', $user->name ?? '') }}" />
    </div>

    <div>
        <label class="field-label" for="phone">{{ __('dashboard.phone') }}</label>
        <input class="input" id="phone" name="phone" inputmode="numeric" maxlength="10" value="{{ old('phone', isset($user) ? substr($user->phone, 3) : '') }}" required />
        <p class="help">{{ __('dashboard.phone_hint') }}</p>
    </div>

    <div>
        <label class="field-label" for="password">{{ __('dashboard.password') }}</label>
        <input class="input" id="password" name="password" type="password" {{ ($passwordRequired ?? false) ? 'required' : '' }} />
        @unless($passwordRequired ?? false)
            <p class="help">{{ __('dashboard.password_optional_on_edit') }}</p>
        @endunless
    </div>

    <div>
        <label class="field-label" for="is_active">{{ __('dashboard.status') }}</label>
        <select class="input" id="is_active" name="is_active">
            <option value="1" @selected((string) old('is_active', (isset($user) ? (int) $user->is_active : 1)) === '1')>{{ __('dashboard.active') }}</option>
            <option value="0" @selected((string) old('is_active', (isset($user) ? (int) $user->is_active : 1)) === '0')>{{ __('dashboard.inactive') }}</option>
        </select>
    </div>
</div>

<div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;">
    <a class="btn-muted" href="{{ route('dashboard.users.index') }}">{{ __('dashboard.cancel') }}</a>
    <button class="btn-primary" type="submit" style="width:auto;padding-inline:16px;">{{ $submitLabel ?? __('dashboard.save') }}</button>
</div>
