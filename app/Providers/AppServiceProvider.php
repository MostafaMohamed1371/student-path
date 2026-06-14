<?php

namespace App\Providers;

use App\Contracts\Push\FcmTopicSubscriber;
use App\Contracts\Push\PushNotifier;
use App\Contracts\Sms\SmsSender;
use App\Contracts\Trips\TripLocationRepository;
use App\Services\Trips\CacheTripLocationRepository;
use App\Services\Trips\FirebaseTripLocationRepository;
use App\Models\Driver;
use App\Models\Guardian;
use App\Models\InAppNotification;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Observers\InAppNotificationObserver;
use App\Observers\ValidatesUniqueIdCardsObserver;
use App\Observers\ValidatesUniquePhonesObserver;
use App\Services\Push\FakeFcmTopicSubscriber;
use App\Services\Push\FakePushNotifier;
use App\Services\Push\FcmPushNotifier;
use App\Services\Push\FcmTopicSubscriber as FirebaseFcmTopicSubscriber;
use App\Services\Sms\FakeSmsSender;
use App\Services\Sms\StandingTechSmsSender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Contract\Messaging;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (
            filled(config('broadcasting.connections.pusher.key'))
            && ! $this->app->environment('testing')
        ) {
            config(['broadcasting.default' => 'pusher']);
        }

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

        $this->app->bind(PushNotifier::class, function ($app) {
            if (! config('fcm.enabled', false) || config('fcm.mock', true)) {
                return $app->make(FakePushNotifier::class);
            }

            if (trim((string) config('fcm.credentials')) === '') {
                Log::warning('FCM_ENABLED is true but FIREBASE_CREDENTIALS is empty; using FakePushNotifier.');

                return $app->make(FakePushNotifier::class);
            }

            return new FcmPushNotifier($app->make(Messaging::class));
        });

        $this->app->singleton(TripLocationRepository::class, function () {
            $store = strtolower((string) config('trips.location_store', 'auto'));

            if ($store === 'cache') {
                return new CacheTripLocationRepository;
            }

            if ($store === 'firebase' || ($store === 'auto' && self::firebaseLocationStoreAvailable())) {
                return new FirebaseTripLocationRepository;
            }

            return new CacheTripLocationRepository;
        });

        $this->app->bind(FcmTopicSubscriber::class, function ($app) {
            if (! config('fcm.enabled', false) || config('fcm.mock', true)) {
                return $app->make(FakeFcmTopicSubscriber::class);
            }

            if (trim((string) config('fcm.credentials')) === '') {
                return $app->make(FakeFcmTopicSubscriber::class);
            }

            return new FirebaseFcmTopicSubscriber($app->make(Messaging::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('dashboard.partials.pagination');

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

        // Single route: Bearer token (mobile) + session cookie (dashboard) via Sanctum.
        Broadcast::routes(['middleware' => ['web', 'auth:sanctum']]);

        InAppNotification::observe(InAppNotificationObserver::class);

        $phoneObserver = ValidatesUniquePhonesObserver::class;
        School::observe($phoneObserver);
        Driver::observe($phoneObserver);
        Student::observe($phoneObserver);
        Guardian::observe($phoneObserver);
        User::observe($phoneObserver);

        $idCardObserver = ValidatesUniqueIdCardsObserver::class;
        Driver::observe($idCardObserver);
        Guardian::observe($idCardObserver);

        require base_path('routes/channels.php');
    }

    private static function firebaseLocationStoreAvailable(): bool
    {
        if (trim((string) config('realtime.firebase.database_url')) === '') {
            return false;
        }

        $credentials = trim((string) config('fcm.credentials', ''));

        return $credentials !== '' && is_readable($credentials);
    }
}
