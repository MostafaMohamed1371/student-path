@extends('dashboard.layout')

@section('title', __('dashboard.menu_profile'))

@section('content')
    @php($title = __('dashboard.menu_profile'))
    @component('dashboard.partials.shell', ['title' => $title])
        @if ($errors->any())
            <div class="alert" style="margin-top: 0; margin-bottom: 16px;">{{ $errors->first() }}</div>
        @endif

        <section class="card">
            <form method="post" action="{{ route('dashboard.profile.update') }}" enctype="multipart/form-data">
                @csrf
                @method('put')

                <div class="form-grid">
                    <div>
                        <label class="field-label" for="name">{{ __('dashboard.name') }}</label>
                        <input class="input" id="name" name="name" value="{{ old('name', $user->name) }}" />
                    </div>

                    <div>
                        <label class="field-label" for="image">{{ __('dashboard.image') }}</label>
                        <input class="input" id="image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp" />
                        <p class="help" id="image-help">Max size: 1.5 MB (jpg, jpeg, png, webp).</p>
                        @if($user->image)
                            <p class="help"><a class="link" target="_blank" href="{{ \Illuminate\Support\Facades\student-pathstorage/app/public::disk('public')->url($user->image) }}">Current image</a></p>
                        @endif
                    </div>

                    <div>
                        <label class="field-label" for="city">{{ __('dashboard.city') }}</label>
                        <input class="input" id="city" name="city" value="{{ old('city', $user->city) }}" />
                    </div>

                    <div>
                        <label class="field-label" for="licence_number">{{ __('dashboard.licence_number') }}</label>
                        <input class="input" id="licence_number" name="licence_number" value="{{ old('licence_number', $user->licence_number) }}" />
                    </div>

                    <div>
                        <label class="field-label" for="votes">{{ __('dashboard.votes') }}</label>
                        <input class="input" id="votes" name="votes" type="number" min="0" value="{{ old('votes', $user->votes) }}" required />
                    </div>

                    <div>
                        <label class="field-label" for="rate">{{ __('dashboard.rate') }}</label>
                        <input class="input" id="rate" name="rate" type="number" min="0" max="5" step="0.1" value="{{ old('rate', $user->rate) }}" required />
                    </div>
                </div>

                <div style="margin-top: 14px;">
                    <label style="display:inline-flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="is_verified" value="1" @checked(old('is_verified', $user->is_verified))>
                        <span>{{ __('dashboard.verified') }}</span>
                    </label>
                </div>

                <div style="display:flex;justify-content:flex-end;margin-top:16px;">
                    <button class="btn-primary" type="submit" style="width:auto;padding-inline:16px;">{{ __('dashboard.update') }}</button>
                </div>
            </form>
        </section>

        <script>
            (function () {
                const maxBytes = 1536 * 1024;
                const fileInput = document.getElementById('image');
                const help = document.getElementById('image-help');

                if (!fileInput || !help) {
                    return;
                }

                fileInput.addEventListener('change', function () {
                    const file = fileInput.files && fileInput.files[0];
                    if (!file) {
                        help.textContent = 'Max size: 1.5 MB (jpg, jpeg, png, webp).';
                        return;
                    }

                    if (file.size > maxBytes) {
                        fileInput.value = '';
                        help.textContent = 'Selected file is too large. Please choose an image smaller than 1.5 MB.';
                        help.style.color = '#dc2626';
                        return;
                    }

                    help.textContent = 'File selected: ' + file.name;
                    help.style.color = '';
                });
            })();
        </script>
    @endcomponent
@endsection
