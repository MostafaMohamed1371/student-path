@extends('dashboard.layout')

@section('title', __('dashboard.title_login'))

@section('content')
    <div class="page-login">
        <div class="login-card">
            <h1 class="login-title">{{ __('dashboard.welcome') }}</h1>
            <p class="login-subtitle">{{ config('dashboard.brand') }}</p>

            <div id="login-alert" class="alert @if (! $errors->any()) hidden @endif" role="alert">
                @if ($errors->any())
                    {{ $errors->first() }}
                @endif
            </div>

            <div id="step-phone" class="login-form">
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

                <button type="button" id="btn-continue" class="btn-primary">
                    {{ __('dashboard.continue') }}
                </button>
            </div>

            <form
                id="step-password"
                method="post"
                action="{{ route('login.authenticate') }}"
                class="login-form hidden"
            >
                @csrf
                <input type="hidden" name="phone" id="password-phone" value="{{ old('phone') }}" />

                <p class="help" id="password-phone-display"></p>

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

                <button type="button" id="btn-back-password" class="btn-link">
                    {{ __('dashboard.change_phone') }}
                </button>
            </form>

            <form
                id="step-otp"
                method="post"
                action="{{ route('login.verify_otp') }}"
                class="login-form hidden"
            >
                @csrf
                <input type="hidden" name="phone" id="otp-phone" value="{{ old('phone') }}" />

                <p class="help" id="otp-phone-display"></p>

                <div>
                    <label for="otp_code" class="field-label">{{ __('dashboard.otp_code') }}</label>
                    <input
                        id="otp_code"
                        name="code"
                        type="text"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="4"
                        pattern="\d{4}"
                        class="input"
                        required
                    />
                    <p class="help">{{ __('dashboard.otp_hint') }}</p>
                </div>

                <button type="button" id="btn-resend-otp" class="btn-secondary">
                    {{ __('dashboard.send_otp') }}
                </button>

                <button type="submit" class="btn-primary">
                    {{ __('dashboard.verify_otp') }}
                </button>

                <button type="button" id="btn-back-otp" class="btn-link">
                    {{ __('dashboard.change_phone') }}
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

    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const initialMode = @json(session('login_mode'));
            const messages = {
                lookupFailed: @json(__('dashboard.lookup_failed')),
                otpSendFailed: @json(__('dashboard.otp_send_failed')),
                otpSent: @json(__('dashboard.otp_sent')),
            };

            const stepPhone = document.getElementById('step-phone');
            const stepPassword = document.getElementById('step-password');
            const stepOtp = document.getElementById('step-otp');
            const phoneInput = document.getElementById('phone');
            const passwordPhone = document.getElementById('password-phone');
            const otpPhone = document.getElementById('otp-phone');
            const passwordPhoneDisplay = document.getElementById('password-phone-display');
            const otpPhoneDisplay = document.getElementById('otp-phone-display');
            const alertBox = document.getElementById('login-alert');
            const btnContinue = document.getElementById('btn-continue');
            const btnResendOtp = document.getElementById('btn-resend-otp');

            function showAlert(text) {
                alertBox.textContent = text;
                alertBox.classList.remove('hidden');
            }

            function hideAlert() {
                alertBox.classList.add('hidden');
                alertBox.textContent = '';
            }

            function nationalPhone() {
                return (phoneInput.value || '').replace(/\D+/g, '');
            }

            function syncHiddenPhones() {
                const value = nationalPhone();
                passwordPhone.value = value;
                otpPhone.value = value;
                const label = '+964 ' + value;
                passwordPhoneDisplay.textContent = label;
                otpPhoneDisplay.textContent = label;
            }

            function showStep(mode) {
                stepPhone.classList.add('hidden');
                stepPassword.classList.add('hidden');
                stepOtp.classList.add('hidden');

                if (mode === 'password') {
                    stepPassword.classList.remove('hidden');
                    document.getElementById('password')?.focus();
                } else if (mode === 'otp') {
                    stepOtp.classList.remove('hidden');
                    document.getElementById('otp_code')?.focus();
                } else {
                    stepPhone.classList.remove('hidden');
                    phoneInput?.focus();
                }
            }

            function resetToPhone() {
                hideAlert();
                showStep(null);
            }

            async function lookupPhone() {
                const phone = nationalPhone();
                if (phone.length !== 10) {
                    phoneInput.reportValidity();
                    return;
                }

                hideAlert();
                btnContinue.disabled = true;

                try {
                    const response = await fetch(@json(route('login.lookup')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({ phone }),
                    });

                    const data = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        showAlert(data.message || messages.lookupFailed);
                        return;
                    }

                    syncHiddenPhones();
                    showStep(data.login_mode);

                    if (data.login_mode === 'otp') {
                        await sendOtp();
                    }
                } catch {
                    showAlert(messages.lookupFailed);
                } finally {
                    btnContinue.disabled = false;
                }
            }

            async function sendOtp() {
                const phone = nationalPhone();
                hideAlert();
                btnResendOtp.disabled = true;

                try {
                    const response = await fetch(@json(route('login.send_otp')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({ phone }),
                    });

                    const data = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        showAlert(data.message || messages.otpSendFailed);
                        return;
                    }

                    showAlert(data.message || messages.otpSent);
                } catch {
                    showAlert(messages.otpSendFailed);
                } finally {
                    btnResendOtp.disabled = false;
                }
            }

            btnContinue.addEventListener('click', lookupPhone);
            btnResendOtp.addEventListener('click', sendOtp);
            document.getElementById('btn-back-password')?.addEventListener('click', resetToPhone);
            document.getElementById('btn-back-otp')?.addEventListener('click', resetToPhone);

            if (initialMode === 'password' || initialMode === 'otp') {
                syncHiddenPhones();
                showStep(initialMode);
                if (initialMode === 'otp') {
                    sendOtp();
                }
            }
        })();
    </script>
@endsection
