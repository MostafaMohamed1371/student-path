<?php

namespace App\Providers;

use App\Contracts\Sms\SmsSender;
use App\Services\Sms\FakeSmsSender;
use App\Services\Sms\StandingTechSmsSender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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

            return $app->make(StandingTechSmsSender::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('otp-send', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('otp-verify', function (Request $request) {
            $phone = (string) $request->input('phone', '');
            $key = sha1($request->ip().'|'.$phone);

            return Limit::perMinute(30)->by($key);
        });
    }
}
