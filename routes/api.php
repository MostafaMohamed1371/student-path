<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\GuardianController;
use App\Http\Controllers\Api\Legacy\LegacyNotificationsController;
use App\Http\Controllers\Api\Legacy\LegacySupportController;
use App\Http\Controllers\Api\Legacy\LegacyTransactionsController;
use App\Http\Controllers\Api\Legacy\LegacyUserExtrasController;
use App\Http\Controllers\Api\SchoolController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\TripHistoryController;
use App\Http\Controllers\Api\User\ChatController as UserChatController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\V1\AbsenceController as V1AbsenceController;
use App\Http\Controllers\Api\V1\ChatController as V1ChatController;
use App\Http\Controllers\Api\V1\DriverTripController as V1DriverTripController;
use App\Http\Controllers\Api\V1\DriverTripLocationController as V1DriverTripLocationController;
use App\Http\Controllers\Api\V1\TripTrackingController as V1TripTrackingController;
use App\Http\Controllers\Api\V1\FcmTokenController as V1FcmTokenController;
use App\Http\Controllers\Api\V1\FcmTripTopicController as V1FcmTripTopicController;
use App\Http\Controllers\Api\V1\HomeLocationController as V1HomeLocationController;
use App\Http\Controllers\Api\V1\InAppNotificationController as V1InAppNotificationController;
use App\Http\Controllers\Api\V1\LocationController as V1LocationController;
use App\Http\Controllers\Api\V1\MetaController as V1MetaController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController as V1NotificationPreferenceController;
use App\Http\Controllers\Api\V1\NotificationsContractController as V1NotificationsContractController;
use App\Http\Controllers\Api\V1\OrderController as V1OrderController;
use App\Http\Controllers\Api\V1\ParentStudentController as V1ParentStudentController;
use App\Http\Controllers\Api\V1\PlacesController as V1PlacesController;
use App\Http\Controllers\Api\V1\ProfileController as V1ProfileController;
use App\Http\Controllers\Api\V1\QiCardWalletPaymentController as V1QiCardWalletPaymentController;
use App\Http\Controllers\Api\V1\TrackingInfoController as V1TrackingInfoController;
use App\Http\Controllers\Api\V1\TransportLinesDriverController as V1TransportLinesDriverController;
use App\Http\Controllers\Api\V1\TripParentController as V1TripParentController;
use App\Http\Controllers\Api\V1\TripRequestController as V1TripRequestController;
use App\Http\Controllers\Api\V1\WalletController as V1WalletController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/qicard', [V1QiCardWalletPaymentController::class, 'webhook'])
    ->name('api.webhooks.qicard');

Route::match(['get', 'post'], 'wallet/payments/qicard/finish', [V1QiCardWalletPaymentController::class, 'finish'])
    ->name('api.wallet.qicard.finish');

Route::prefix('auth')->group(function (): void {
    Route::post('send-otp', [AuthController::class, 'sendOtp'])
        ->middleware('throttle:otp-send');

    Route::post('verify-otp', [AuthController::class, 'verifyOtp'])
        ->middleware('throttle:otp-verify');
});

Route::get('support/info', [LegacySupportController::class, 'info']);
Route::get('support/categories', [LegacySupportController::class, 'categories']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });

    Route::get('transactions', [LegacyTransactionsController::class, 'index']);
    Route::get('notifications', [LegacyNotificationsController::class, 'index']);
    Route::patch('notifications/read-all', [V1NotificationsContractController::class, 'markAllRead']);
    Route::patch('notifications/{notification}/read', [V1NotificationsContractController::class, 'markRead'])
        ->whereNumber('notification');
    Route::post('notifications/fcm-token', [V1NotificationsContractController::class, 'registerFcmToken']);
    Route::get('haveNewMessages', [LegacyNotificationsController::class, 'haveNewMessages']);

    Route::post('support/complaint', [LegacySupportController::class, 'complaint']);

    Route::prefix('user')->group(function (): void {
        Route::get('profile', [UserProfileController::class, 'show']);
        Route::put('profile', [UserProfileController::class, 'update']);
        Route::delete('profile', [UserProfileController::class, 'destroy']);
        Route::post('language', [UserProfileController::class, 'changeLanguage']);
        Route::get('driver', [DriverController::class, 'myDriver']);
        Route::get('settings/notifications', [LegacyUserExtrasController::class, 'notificationSettings']);
        Route::put('settings/notifications', [V1NotificationPreferenceController::class, 'update']);
        Route::get('performance', [LegacyUserExtrasController::class, 'performance']);
        Route::post('fcm-token', [V1FcmTokenController::class, 'store']);
        Route::delete('fcm-token', [V1FcmTokenController::class, 'destroy']);
    });

    Route::prefix('bus')->group(function (): void {
        Route::get('my-bus', [BusController::class, 'showMyBus']);
        Route::post('my-bus', [BusController::class, 'store']);
        Route::put('my-bus', [BusController::class, 'update']);
        Route::delete('my-bus', [BusController::class, 'destroy']);
    });

    Route::prefix('org')->group(function (): void {
        Route::prefix('schools')->group(function (): void {
            Route::get('/', [SchoolController::class, 'index']);
            Route::post('/', [SchoolController::class, 'store']);
            Route::get('{school}', [SchoolController::class, 'show']);
            Route::put('{school}', [SchoolController::class, 'update']);
            Route::delete('{school}', [SchoolController::class, 'destroy']);
        });

        Route::prefix('drivers')->group(function (): void {
            Route::get('/', [DriverController::class, 'index']);
            Route::post('/', [DriverController::class, 'store']);
            Route::get('{driver}', [DriverController::class, 'show']);
            Route::put('{driver}', [DriverController::class, 'update']);
            Route::delete('{driver}', [DriverController::class, 'destroy']);
        });

        Route::prefix('students')->group(function (): void {
            Route::get('/', [StudentController::class, 'index']);
            Route::post('/', [StudentController::class, 'store']);
            Route::get('{student}', [StudentController::class, 'show']);
            Route::put('{student}', [StudentController::class, 'update']);
            Route::delete('{student}', [StudentController::class, 'destroy']);
        });

        Route::prefix('guardians')->group(function (): void {
            Route::get('/', [GuardianController::class, 'index']);
            Route::post('/', [GuardianController::class, 'store']);
            Route::get('{guardian}', [GuardianController::class, 'show']);
            Route::put('{guardian}', [GuardianController::class, 'update']);
            Route::delete('{guardian}', [GuardianController::class, 'destroy']);
        });

        Route::prefix('trips')->group(function (): void {
            Route::get('history', [TripHistoryController::class, 'history']);
            Route::get('/', [TripHistoryController::class, 'index']);
            Route::post('/', [TripHistoryController::class, 'store']);
            Route::get('{trip}', [TripHistoryController::class, 'show']);
            Route::put('{trip}', [TripHistoryController::class, 'update']);
            Route::delete('{trip}', [TripHistoryController::class, 'destroy']);
        });
    });

    Route::get('wallet', [V1WalletController::class, 'show']);
    Route::get('wallet/transactions', [V1WalletController::class, 'transactions']);
    Route::post('wallet/recharge', [V1WalletController::class, 'recharge']);
    Route::post('wallet/payments/qicard/init', [V1QiCardWalletPaymentController::class, 'init']);

    Route::get('home-location', [V1HomeLocationController::class, 'show']);
    Route::post('home-location', [V1HomeLocationController::class, 'store']);
    Route::delete('home-location', [V1HomeLocationController::class, 'destroy']);

    Route::get('locations/districts', [V1LocationController::class, 'districts']);
    Route::get('locations/districts/{district}/areas', [V1LocationController::class, 'areas']);
    Route::get('locations/iraq', [V1LocationController::class, 'iraq']);

    Route::get('places/autocomplete', [V1PlacesController::class, 'autocomplete'])
        ->middleware('throttle:google-places');
    Route::get('places/{place}', [V1PlacesController::class, 'details'])
        ->middleware('throttle:google-places');

    Route::get('meta/schools', [V1MetaController::class, 'schools']);
    Route::get('meta/grades', [V1MetaController::class, 'grades']);

    Route::get('students', [V1ParentStudentController::class, 'index']);
    Route::post('students', [V1ParentStudentController::class, 'store']);
    Route::get('students/{student}', [V1ParentStudentController::class, 'show']);
    Route::put('students/{student}', [V1ParentStudentController::class, 'update']);
    Route::patch('students/{student}', [V1ParentStudentController::class, 'update']);
    Route::delete('students/{student}', [V1ParentStudentController::class, 'destroy']);

    Route::get('transport-lines/drivers', [V1TransportLinesDriverController::class, 'index']);
    Route::get('transport-lines/drivers/{driver}', [V1TransportLinesDriverController::class, 'show']);

    Route::get('orders', [V1OrderController::class, 'index']);
    Route::put('orders/{order}', [V1OrderController::class, 'update']);

    Route::get('scheduled-trips', [V1DriverTripController::class, 'scheduledTrips']);
    Route::get('driver-overview', [V1DriverTripController::class, 'driverOverview']);
    Route::get('driver/trips/{trip}', [V1DriverTripController::class, 'tripDetails']);
    Route::get('driver/trips/{trip}/summary', [V1DriverTripController::class, 'tripSummary']);
    Route::post('driver/trips/{trip}/finalize', [V1DriverTripController::class, 'finalizeTrip']);
    Route::post('driver/trips/{trip}/location', [V1DriverTripLocationController::class, 'store']);
    Route::get('trips/current-trip', [V1DriverTripController::class, 'currentTrip']);
    Route::post('trips/{trip}/start', [V1DriverTripController::class, 'startTrip']);
    Route::put('trips/end-trip', [V1DriverTripController::class, 'endTrip']);
    Route::put('update-status', [V1DriverTripController::class, 'updateStatus']);
    Route::post('delay-alert', [V1DriverTripController::class, 'sendDelayAlert']);
    Route::post('trip-feedback', [V1DriverTripController::class, 'submitTripFeedback']);
    Route::post('driver/sos/trigger', [V1DriverTripController::class, 'triggerSos']);
    Route::post('driver/sos/{sos}/stop', [V1DriverTripController::class, 'stopSos']);

    Route::get('trips/available', [V1TripParentController::class, 'available']);
    Route::get('trips/active', [V1TripParentController::class, 'active']);
    Route::get('trips/{trip}/driver', [V1TripParentController::class, 'driver']);
    Route::get('trips/{trip}/tracking/location', [V1TripTrackingController::class, 'location']);
    Route::get('trips/{trip}/tracking', [V1TripTrackingController::class, 'show']);
    Route::get('trips/{trip}', [V1TripParentController::class, 'show']);

    Route::get('trip-tracking/config', V1TrackingInfoController::class);
    Route::get('trip-tracking/topics', [V1FcmTripTopicController::class, 'index']);
    Route::post('trip-tracking/topics/subscribe', [V1FcmTripTopicController::class, 'subscribe']);
    Route::delete('trip-tracking/topics/unsubscribe', [V1FcmTripTopicController::class, 'unsubscribe']);

    Route::post('trip-requests', [V1TripRequestController::class, 'store']);
    Route::get('trip-requests', [V1TripRequestController::class, 'index']);
    Route::get('trip-requests/{trip_request}', [V1TripRequestController::class, 'show']);
    Route::put('trip-requests/{trip_request}', [V1TripRequestController::class, 'update']);
    Route::patch('trip-requests/{trip_request}', [V1TripRequestController::class, 'update']);
    Route::post('trip-requests/{trip_request}/cancel', [V1TripRequestController::class, 'cancel']);
    Route::delete('trip-requests/{trip_request}', [V1TripRequestController::class, 'destroy']);

    Route::post('absences', [V1AbsenceController::class, 'store']);
    Route::get('absences', [V1AbsenceController::class, 'index']);
    Route::get('absences/{absence}', [V1AbsenceController::class, 'show']);
    Route::put('absences/{absence}', [V1AbsenceController::class, 'update']);
    Route::patch('absences/{absence}', [V1AbsenceController::class, 'update']);
    Route::delete('absences/{absence}', [V1AbsenceController::class, 'destroy']);

    Route::get('profile', [V1ProfileController::class, 'show']);
    Route::put('profile', [V1ProfileController::class, 'update']);
    Route::delete('profile', [V1ProfileController::class, 'destroy']);

    Route::get('in-app-notifications', [V1InAppNotificationController::class, 'index']);
    Route::post('in-app-notifications/read', [V1InAppNotificationController::class, 'markRead']);
    Route::delete('in-app-notifications/{notification}', [V1InAppNotificationController::class, 'destroy']);

    Route::prefix('chat')->group(function (): void {
        Route::get('config', [V1ChatController::class, 'config']);
        Route::get('unread-count', [V1ChatController::class, 'unreadMessagesCount']);
        Route::get('conversations', [V1ChatController::class, 'indexConversations']);
        Route::post('conversations', [V1ChatController::class, 'storeConversation']);
        Route::get('conversations/{conversation}', [V1ChatController::class, 'showConversation']);
        Route::get('conversations/{conversation}/messages', [V1ChatController::class, 'indexMessages']);
        Route::post('conversations/{conversation}/messages', [V1ChatController::class, 'storeMessage']);
        Route::post('conversations/{conversation}/read', [V1ChatController::class, 'markRead']);
        Route::post('conversations/{conversation}/unread', [V1ChatController::class, 'markUnread']);
        Route::put('conversations/{conversation}/preferences', [V1ChatController::class, 'updatePreferences']);
        Route::post('conversations/{conversation}/pin', [V1ChatController::class, 'pinChat']);
        Route::post('conversations/{conversation}/unpin', [V1ChatController::class, 'unpinChat']);
        Route::post('conversations/{conversation}/block-user', [V1ChatController::class, 'blockUser']);
        Route::post('conversations/{conversation}/unblock-user', [V1ChatController::class, 'unblockUser']);
        Route::delete('conversations/{conversation}', [V1ChatController::class, 'destroyConversation']);
        Route::post('conversations/{conversation}/report', [V1ChatController::class, 'reportConversation']);
    });

    Route::prefix('user')->group(function (): void {
        Route::get('chats', [UserChatController::class, 'index']);
        Route::get('chats/unread-count', [UserChatController::class, 'unreadMessagesCount']);
        Route::post('chats/start', [UserChatController::class, 'start']);
        Route::get('chats/{id}/messages', [UserChatController::class, 'messages'])->whereNumber('id');
        Route::post('chats/{id}/messages', [UserChatController::class, 'send'])->whereNumber('id');
        Route::post('chats/{id}/read', [UserChatController::class, 'markRead'])->whereNumber('id');
        Route::post('chats/{id}/unread', [UserChatController::class, 'markUnread'])->whereNumber('id');
        Route::put('chats/{id}/preferences', [UserChatController::class, 'updatePreferences'])->whereNumber('id');
        Route::post('chats/{id}/pin', [UserChatController::class, 'pinChat'])->whereNumber('id');
        Route::post('chats/{id}/unpin', [UserChatController::class, 'unpinChat'])->whereNumber('id');
        Route::post('chats/{id}/block-user', [UserChatController::class, 'blockUser'])->whereNumber('id');
        Route::post('chats/{id}/unblock-user', [UserChatController::class, 'unblockUser'])->whereNumber('id');
        Route::post('chats/{id}/typing', [UserChatController::class, 'typing'])->whereNumber('id');
        Route::put('chats/{chatId}/messages/{messageId}', [UserChatController::class, 'updateMessage'])
            ->whereNumber(['chatId', 'messageId']);
        Route::delete('chats/{chatId}/messages/{messageId}', [UserChatController::class, 'deleteMessage'])
            ->whereNumber(['chatId', 'messageId']);
        Route::post('chats/{chatId}/messages/{messageId}/offer/accept', [UserChatController::class, 'acceptOffer'])
            ->whereNumber(['chatId', 'messageId']);
        Route::post('chats/{chatId}/messages/{messageId}/offer/reject', [UserChatController::class, 'rejectOffer'])
            ->whereNumber(['chatId', 'messageId']);
        Route::post('chats/{chatId}/messages/{messageId}/offer/counter', [UserChatController::class, 'counterOffer'])
            ->whereNumber(['chatId', 'messageId']);
        Route::get('chats/{chatId}/offers/{messageId}/thread', [UserChatController::class, 'offerThread'])
            ->whereNumber(['chatId', 'messageId']);
        Route::delete('chats/{id}', [UserChatController::class, 'destroy'])->whereNumber('id');
        Route::post('chats/{id}/report', [UserChatController::class, 'report'])->whereNumber('id');
    });
});
