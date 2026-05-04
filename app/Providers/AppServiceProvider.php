<?php

namespace App\Providers;

use App\Contracts\Sms\SmsSender;
use App\Services\Sms\FakeSmsSender;
use App\Services\Sms\StandingTechSmsSender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsSender::class, function ($app) {
            if (config('standingtech.mock')) {
                return $app->make(FakeSmsSender::class);
            }

            if (trim((string) config('standingtech.bearer_token')) === '') {
                Log::warning('STANDINGTECH_BEARER_TOKEN is empty; using FakeSmsSender (OTP still works; configure token and STANDINGTECH_MOCK=false to send real SMS).');

                return $app->make(FakeSmsSender::class);
            }

            return $app->make(StandingTechSmsSender::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('otp-send', function (Request $request) {
            $perPhone = (int) config('otp.send_throttle_per_phone_per_minute', 120);
            $perIp = (int) config('otp.send_throttle_per_ip_per_minute', 300);
            $digits = preg_replace('/\D+/', '', (string) $request->input('phone', '')) ?? '';
            $phoneKey = $digits !== '' ? sha1($digits) : 'no-phone';

            return [
                Limit::perMinute(max(1, $perPhone))->by('otp-send|phone|'.$phoneKey),
                Limit::perMinute(max(1, $perIp))->by('otp-send|ip|'.$request->ip()),
            ];
        });

        RateLimiter::for('otp-verify', function (Request $request) {
            $phone = (string) $request->input('phone', '');
            $key = sha1($request->ip().'|'.$phone);

            return Limit::perMinute(30)->by($key);
        });

        RateLimiter::for('google-places', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        Broadcast::routes(['middleware' => ['auth:sanctum']]);

        require base_path('routes/channels.php');
    }
}
