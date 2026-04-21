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
        <label class="field-label" for="image">{{ __('dashboard.image') }}</label>
        <input class="input" id="image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp" />
        @if(isset($user) && $user->image)
            <p class="help"><a class="link" target="_blank" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($user->image) }}">Current image</a></p>
        @endif
    </div>

    <div>
        <label class="field-label" for="password">{{ __('dashboard.password') }}</label>
        <input class="input" id="password" name="password" type="password" {{ ($passwordRequired ?? false) ? 'required' : '' }} />
        @unless($passwordRequired ?? false)
            <p class="help">{{ __('dashboard.password_optional_on_edit') }}</p>
        @endunless
    </div>

    <div>
        <label class="field-label" for="city">{{ __('dashboard.city') }}</label>
        <input class="input" id="city" name="city" value="{{ old('city', $user->city ?? '') }}" />
    </div>

    <div>
        <label class="field-label" for="licence_number">{{ __('dashboard.licence_number') }}</label>
        <input class="input" id="licence_number" name="licence_number" value="{{ old('licence_number', $user->licence_number ?? '') }}" />
    </div>

    <div>
        <label class="field-label" for="votes">{{ __('dashboard.votes') }}</label>
        <input class="input" id="votes" name="votes" type="number" min="0" value="{{ old('votes', $user->votes ?? 0) }}" required />
    </div>

    <div>
        <label class="field-label" for="rate">{{ __('dashboard.rate') }}</label>
        <input class="input" id="rate" name="rate" type="number" min="0" max="5" step="0.1" value="{{ old('rate', $user->rate ?? 0) }}" required />
    </div>

    <div>
        <label class="field-label" for="is_verified">{{ __('dashboard.verified') }}</label>
        <select class="input" id="is_verified" name="is_verified">
            <option value="1" @selected((string) old('is_verified', (isset($user) ? (int) $user->is_verified : 0)) === '1')>{{ __('dashboard.verified') }}</option>
            <option value="0" @selected((string) old('is_verified', (isset($user) ? (int) $user->is_verified : 0)) === '0')>{{ __('dashboard.not_verified') }}</option>
        </select>
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
