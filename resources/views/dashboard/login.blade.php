@extends('dashboard.layout')

@section('title', __('dashboard.title_login'))

@section('content')
    <div class="page-login">
        <div class="login-card">
            <h1 class="login-title">{{ __('dashboard.welcome') }}</h1>
            <p class="login-subtitle">{{ config('dashboard.brand') }}</p>

            @if ($errors->any())
                <div class="alert" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="post" action="{{ route('login') }}" class="login-form">
                @csrf

                <div>
                    <label for="phone" class="field-label">{{ __('dashboard.phone') }}</label>
                    <div class="phone-row">
                        <div class="phone-prefix">
                            <span aria-hidden="true">🇮🇶</span>
                            <span>+964</span>
                        </div>
                        <input
                            id="phone"
                            name="phone"
                            type="text"
                            inputmode="numeric"
                            autocomplete="tel-national"
                            maxlength="10"
                            value="{{ old('phone') }}"
                            placeholder="XXXXXXXXXX"
                            class="input"
                            required
                        />
                    </div>
                    <p class="help">{{ __('dashboard.phone_hint') }}</p>
                </div>

                <div>
                    <label for="password" class="field-label">{{ __('dashboard.password') }}</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        class="input"
                        required
                    />
                </div>

                <button type="submit" class="btn-primary">
                    {{ __('dashboard.login') }}
                </button>
            </form>

            <div class="login-links">
                <a href="{{ route('locale.switch', ['locale' => app()->getLocale() === 'ar' ? 'en' : 'ar']) }}" class="link">
                    {{ __('dashboard.lang_switch') }}
                </a>
                <a href="{{ url('/') }}" class="link link-muted">{{ __('dashboard.back_home') }}</a>
            </div>
        </div>
    </div>
@endsection
