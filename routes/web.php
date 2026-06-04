<?php

use App\Http\Controllers\Web\DashboardAbsenceController;
use App\Http\Controllers\Web\DashboardBusController;
use App\Http\Controllers\Web\DashboardChatController;
use App\Http\Controllers\Web\DashboardChatReportController;
use App\Http\Controllers\Web\DashboardDriverController;
use App\Http\Controllers\Web\DashboardGeocodeController;
use App\Http\Controllers\Web\DashboardGuardianController;
use App\Http\Controllers\Web\DashboardHomeController;
use App\Http\Controllers\Web\DashboardLocationController;
use App\Http\Controllers\Web\DashboardLoginController;
use App\Http\Controllers\Web\DashboardNotificationStaffController;
use App\Http\Controllers\Web\DashboardProfileController;
use App\Http\Controllers\Web\DashboardReportsController;
use App\Http\Controllers\Web\DashboardRouteController;
use App\Http\Controllers\Web\DashboardSchoolController;
use App\Http\Controllers\Web\DashboardStudentController;
use App\Http\Controllers\Web\DashboardSupportComplaintController;
use App\Http\Controllers\Web\DashboardTripController;
use App\Http\Controllers\Web\DashboardTripRequestController;
use App\Http\Controllers\Web\DashboardUserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/locale/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['en', 'ar'], true), 400);
    session(['locale' => $locale]);

    return redirect()->back();
})->name('locale.switch');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [DashboardLoginController::class, 'show'])->name('login');
    Route::post('/login/lookup', [DashboardLoginController::class, 'lookupPhone'])->name('login.lookup');
    Route::post('/login/send-otp', [DashboardLoginController::class, 'sendOtp'])
        ->middleware('throttle:otp-send')
        ->name('login.send_otp');
    Route::post('/login/verify-otp', [DashboardLoginController::class, 'verifyOtp'])
        ->middleware('throttle:otp-verify')
        ->name('login.verify_otp');
    Route::post('/login', [DashboardLoginController::class, 'authenticate'])->name('login.authenticate');
});

Route::post('/logout', [DashboardLoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [DashboardHomeController::class, 'index'])->name('dashboard');

    Route::get('/dashboard/profile', [DashboardProfileController::class, 'edit'])->name('dashboard.profile.edit');
    Route::put('/dashboard/profile', [DashboardProfileController::class, 'update'])->name('dashboard.profile.update');

    Route::get('/dashboard/buses', [DashboardBusController::class, 'index'])->name('dashboard.buses.index');
    Route::get('/dashboard/buses/create', [DashboardBusController::class, 'create'])->name('dashboard.buses.create');
    Route::get('/dashboard/buses/form-options', [DashboardBusController::class, 'formOptions'])->name('dashboard.buses.form_options');
    Route::post('/dashboard/buses', [DashboardBusController::class, 'store'])->name('dashboard.buses.store');
    Route::get('/dashboard/buses/{bus}/edit', [DashboardBusController::class, 'edit'])->name('dashboard.buses.edit');
    Route::put('/dashboard/buses/{bus}', [DashboardBusController::class, 'update'])->name('dashboard.buses.update');
    Route::delete('/dashboard/buses/{bus}', [DashboardBusController::class, 'destroy'])->name('dashboard.buses.destroy');

    Route::get('/dashboard/geocode/reverse', [DashboardGeocodeController::class, 'reverse'])
        ->name('dashboard.geocode.reverse');

    Route::get('/dashboard/schools', [DashboardSchoolController::class, 'index'])->name('dashboard.schools.index');
    Route::get('/dashboard/schools/create', [DashboardSchoolController::class, 'create'])->name('dashboard.schools.create');
    Route::post('/dashboard/schools', [DashboardSchoolController::class, 'store'])->name('dashboard.schools.store');
    Route::get('/dashboard/schools/{school}/edit', [DashboardSchoolController::class, 'edit'])->name('dashboard.schools.edit');
    Route::put('/dashboard/schools/{school}', [DashboardSchoolController::class, 'update'])->name('dashboard.schools.update');
    Route::delete('/dashboard/schools/{school}', [DashboardSchoolController::class, 'destroy'])->name('dashboard.schools.destroy');

    Route::get('/dashboard/drivers', [DashboardDriverController::class, 'index'])->name('dashboard.drivers.index');
    Route::get('/dashboard/drivers/create', [DashboardDriverController::class, 'create'])->name('dashboard.drivers.create');
    Route::post('/dashboard/drivers', [DashboardDriverController::class, 'store'])->name('dashboard.drivers.store');
    Route::get('/dashboard/drivers/{driver}/edit', [DashboardDriverController::class, 'edit'])->name('dashboard.drivers.edit');
    Route::put('/dashboard/drivers/{driver}', [DashboardDriverController::class, 'update'])->name('dashboard.drivers.update');
    Route::delete('/dashboard/drivers/{driver}', [DashboardDriverController::class, 'destroy'])->name('dashboard.drivers.destroy');

    Route::get('/dashboard/students', [DashboardStudentController::class, 'index'])->name('dashboard.students.index');
    Route::get('/dashboard/students/create', [DashboardStudentController::class, 'create'])->name('dashboard.students.create');
    Route::get('/dashboard/students/form-guardians', [DashboardStudentController::class, 'formGuardians'])->name('dashboard.students.form_guardians');
    Route::get('/dashboard/students/lookup-guardian', [DashboardStudentController::class, 'lookupGuardian'])->name('dashboard.students.lookup_guardian');
    Route::post('/dashboard/students', [DashboardStudentController::class, 'store'])->name('dashboard.students.store');
    Route::get('/dashboard/students/{student}/edit', [DashboardStudentController::class, 'edit'])->name('dashboard.students.edit');
    Route::put('/dashboard/students/{student}', [DashboardStudentController::class, 'update'])->name('dashboard.students.update');
    Route::delete('/dashboard/students/{student}', [DashboardStudentController::class, 'destroy'])->name('dashboard.students.destroy');

    Route::get('/dashboard/guardians', [DashboardGuardianController::class, 'index'])->name('dashboard.guardians.index');
    Route::get('/dashboard/guardians/create', [DashboardGuardianController::class, 'create'])->name('dashboard.guardians.create');
    Route::get('/dashboard/guardians/lookup-by-id-card', [DashboardGuardianController::class, 'lookupByIdCard'])->name('dashboard.guardians.lookup_by_id_card');
    Route::post('/dashboard/guardians', [DashboardGuardianController::class, 'store'])->name('dashboard.guardians.store');
    Route::get('/dashboard/guardians/{guardian}/edit', [DashboardGuardianController::class, 'edit'])->name('dashboard.guardians.edit');
    Route::put('/dashboard/guardians/{guardian}', [DashboardGuardianController::class, 'update'])->name('dashboard.guardians.update');
    Route::delete('/dashboard/guardians/{guardian}', [DashboardGuardianController::class, 'destroy'])->name('dashboard.guardians.destroy');

    Route::get('/dashboard/trips', [DashboardTripController::class, 'index'])->name('dashboard.trips.index');
    Route::get('/dashboard/trips/assign-students', [DashboardTripController::class, 'assignStudents'])->name('dashboard.trips.assign_students');
    Route::get('/dashboard/trips/assign-students/form-options', [DashboardTripController::class, 'assignStudentsFormOptions'])->name('dashboard.trips.assign_students.form_options');
    Route::post('/dashboard/trips/assign-students', [DashboardTripController::class, 'assignStudentsStore'])->name('dashboard.trips.assign_students.store');
    Route::get('/dashboard/trips/create', [DashboardTripController::class, 'create'])->name('dashboard.trips.create');
    Route::get('/dashboard/trips/form-options', [DashboardTripController::class, 'formOptions'])->name('dashboard.trips.form_options');
    Route::get('/dashboard/trips/driver-auto-fill', [DashboardTripController::class, 'driverAutoFill'])->name('dashboard.trips.driver_auto_fill');
    Route::post('/dashboard/trips', [DashboardTripController::class, 'store'])->name('dashboard.trips.store');
    Route::get('/dashboard/trips/{trip}', [DashboardTripController::class, 'show'])->name('dashboard.trips.show');
    Route::get('/dashboard/trips/{trip}/tracking', [DashboardTripController::class, 'tracking'])->name('dashboard.trips.tracking');
    Route::get('/dashboard/trips/{trip}/edit', [DashboardTripController::class, 'edit'])->name('dashboard.trips.edit');
    Route::put('/dashboard/trips/{trip}', [DashboardTripController::class, 'update'])->name('dashboard.trips.update');
    Route::delete('/dashboard/trips/{trip}', [DashboardTripController::class, 'destroy'])->name('dashboard.trips.destroy');

    Route::get('/dashboard/users', [DashboardUserController::class, 'index'])->name('dashboard.users.index');
    Route::get('/dashboard/users/create', [DashboardUserController::class, 'create'])->name('dashboard.users.create');
    Route::post('/dashboard/users', [DashboardUserController::class, 'store'])->name('dashboard.users.store');
    Route::get('/dashboard/users/{user}/edit', [DashboardUserController::class, 'edit'])->name('dashboard.users.edit');
    Route::put('/dashboard/users/{user}', [DashboardUserController::class, 'update'])->name('dashboard.users.update');
    Route::delete('/dashboard/users/{user}', [DashboardUserController::class, 'destroy'])->name('dashboard.users.destroy');

    Route::get('/dashboard/payments', [DashboardReportsController::class, 'payments'])->name('dashboard.payments');
    Route::get('/dashboard/notifications', [DashboardReportsController::class, 'notificationsHub'])->name('dashboard.notifications.hub');
    Route::get('/dashboard/in-app-notifications', [DashboardReportsController::class, 'notifications'])->name('dashboard.in_app_notifications');
    Route::get('/dashboard/in-app-notifications/{notification}', [DashboardNotificationStaffController::class, 'show'])->whereNumber('notification')->name('dashboard.in_app_notifications.show');
    Route::post('/dashboard/in-app-notifications/read-all', [DashboardNotificationStaffController::class, 'markAllRead'])->name('dashboard.in_app_notifications.mark_all_read');
    Route::post('/dashboard/in-app-notifications/{notification}/read', [DashboardNotificationStaffController::class, 'markRead'])->name('dashboard.in_app_notifications.mark_read');
    Route::get('/dashboard/fcm-tokens', [DashboardNotificationStaffController::class, 'fcmTokens'])->name('dashboard.fcm_tokens.index');
    Route::delete('/dashboard/fcm-tokens/{fcmToken}', [DashboardNotificationStaffController::class, 'destroyFcmToken'])->whereNumber('fcmToken')->name('dashboard.fcm_tokens.destroy');
    Route::get('/dashboard/delay-alerts', [DashboardReportsController::class, 'delayAlerts'])->name('dashboard.delay_alerts');
    Route::get('/dashboard/sos-alerts', [DashboardReportsController::class, 'sosAlerts'])->name('dashboard.sos_alerts');
    Route::get('/dashboard/trip-finalization-reports', [DashboardReportsController::class, 'tripFinalizationReports'])->name('dashboard.trip_finalization_reports');

    Route::get('/dashboard/locations/areas', [DashboardLocationController::class, 'areas'])->name('dashboard.locations.areas');
    Route::get('/dashboard/locations/neighborhoods', [DashboardLocationController::class, 'neighborhoods'])->name('dashboard.locations.neighborhoods');
    Route::get('/dashboard/assigned-drivers', [DashboardRouteController::class, 'assignedDrivers'])->name('dashboard.assigned_drivers.index');
    Route::post('/dashboard/routes/{route}/assign-driver', [DashboardRouteController::class, 'assignDriver'])->name('dashboard.routes.assign_driver');
    Route::get('/dashboard/routes', [DashboardRouteController::class, 'index'])->name('dashboard.routes.index');
    Route::get('/dashboard/routes/create', [DashboardRouteController::class, 'create'])->name('dashboard.routes.create');
    Route::post('/dashboard/routes', [DashboardRouteController::class, 'store'])->name('dashboard.routes.store');
    Route::get('/dashboard/routes/form-options', [DashboardRouteController::class, 'formOptions'])->name('dashboard.routes.form_options');
    Route::post('/dashboard/routes/auto-assign', [DashboardRouteController::class, 'autoAssign'])->name('dashboard.routes.auto_assign');
    Route::post('/dashboard/routes/assign-route-matching', [DashboardRouteController::class, 'assignRouteMatching'])->name('dashboard.routes.assign_route_matching');
    Route::post('/dashboard/routes/assign-student', [DashboardRouteController::class, 'assignStudent'])->name('dashboard.routes.assign_student');
    Route::get('/dashboard/routes/{route}/edit', [DashboardRouteController::class, 'edit'])->name('dashboard.routes.edit');
    Route::put('/dashboard/routes/{route}', [DashboardRouteController::class, 'update'])->name('dashboard.routes.update');
    Route::delete('/dashboard/routes/{route}', [DashboardRouteController::class, 'destroy'])->name('dashboard.routes.destroy');
    Route::delete('/dashboard/routes/students/{routeStudent}', [DashboardRouteController::class, 'removeStudent'])->name('dashboard.routes.remove_student');

    Route::get('/dashboard/trip-requests', [DashboardTripRequestController::class, 'index'])->name('dashboard.trip_requests.index');
    Route::get('/dashboard/trip-requests/create', [DashboardTripRequestController::class, 'create'])->name('dashboard.trip_requests.create');
    Route::get('/dashboard/trip-requests/form-options', [DashboardTripRequestController::class, 'formOptions'])->name('dashboard.trip_requests.form_options');
    Route::get('/dashboard/trip-requests/form-students', [DashboardTripRequestController::class, 'formStudents'])->name('dashboard.trip_requests.form_students');
    Route::post('/dashboard/trip-requests', [DashboardTripRequestController::class, 'store'])->name('dashboard.trip_requests.store');
    Route::get('/dashboard/trip-requests/{trip_request}', [DashboardTripRequestController::class, 'show'])->name('dashboard.trip_requests.show');
    Route::get('/dashboard/trip-requests/{trip_request}/edit', [DashboardTripRequestController::class, 'edit'])->name('dashboard.trip_requests.edit');
    Route::put('/dashboard/trip-requests/{trip_request}/status', [DashboardTripRequestController::class, 'updateStatus'])->name('dashboard.trip_requests.update_status');
    Route::put('/dashboard/trip-requests/{trip_request}', [DashboardTripRequestController::class, 'update'])->name('dashboard.trip_requests.update');
    Route::delete('/dashboard/trip-requests/{trip_request}', [DashboardTripRequestController::class, 'destroy'])->name('dashboard.trip_requests.destroy');

    Route::get('/dashboard/absences', [DashboardAbsenceController::class, 'index'])->name('dashboard.absences.index');
    Route::get('/dashboard/absences/create', [DashboardAbsenceController::class, 'create'])->name('dashboard.absences.create');
    Route::post('/dashboard/absences', [DashboardAbsenceController::class, 'store'])->name('dashboard.absences.store');
    Route::get('/dashboard/absences/{absence}/edit', [DashboardAbsenceController::class, 'edit'])->name('dashboard.absences.edit');
    Route::put('/dashboard/absences/{absence}', [DashboardAbsenceController::class, 'update'])->name('dashboard.absences.update');
    Route::delete('/dashboard/absences/{absence}', [DashboardAbsenceController::class, 'destroy'])->name('dashboard.absences.destroy');

    Route::get('/dashboard/support-chat', [DashboardChatController::class, 'index'])->name('dashboard.support_chat.index');
    Route::get('/dashboard/support-chat/{conversation}', [DashboardChatController::class, 'show'])->name('dashboard.support_chat.show');
    Route::get('/dashboard/support-chat/{conversation}/messages', [DashboardChatController::class, 'messages'])->name('dashboard.support_chat.messages');
    Route::post('/dashboard/support-chat/{conversation}/messages', [DashboardChatController::class, 'storeMessage'])->name('dashboard.support_chat.messages.store');
    Route::post('/dashboard/support-chat/{conversation}/read', [DashboardChatController::class, 'markRead'])->name('dashboard.support_chat.read');
    Route::post('/dashboard/support-chat/{conversation}/close', [DashboardChatController::class, 'close'])->name('dashboard.support_chat.close');
    Route::post('/dashboard/support-chat/{conversation}/reopen', [DashboardChatController::class, 'reopen'])->name('dashboard.support_chat.reopen');
    Route::delete('/dashboard/support-chat/{conversation}', [DashboardChatController::class, 'destroy'])->name('dashboard.support_chat.destroy');
    Route::post('/dashboard/support-chat/{conversation}/block', [DashboardChatController::class, 'block'])->name('dashboard.support_chat.block');
    Route::post('/dashboard/support-chat/{conversation}/unblock', [DashboardChatController::class, 'unblock'])->name('dashboard.support_chat.unblock');

    Route::get('/dashboard/chat-reports', [DashboardChatReportController::class, 'index'])->name('dashboard.chat_reports.index');
    Route::post('/dashboard/chat-reports/{report}/status', [DashboardChatReportController::class, 'updateStatus'])->name('dashboard.chat_reports.update_status');

    Route::get('/dashboard/support-complaints', [DashboardSupportComplaintController::class, 'index'])->name('dashboard.support_complaints.index');
    Route::get('/dashboard/support-complaints/create', [DashboardSupportComplaintController::class, 'create'])->name('dashboard.support_complaints.create');
    Route::post('/dashboard/support-complaints', [DashboardSupportComplaintController::class, 'store'])->name('dashboard.support_complaints.store');
    Route::get('/dashboard/support-complaints/{complaint}', [DashboardSupportComplaintController::class, 'show'])->name('dashboard.support_complaints.show');
    Route::get('/dashboard/support-complaints/{complaint}/edit', [DashboardSupportComplaintController::class, 'edit'])->name('dashboard.support_complaints.edit');
    Route::put('/dashboard/support-complaints/{complaint}', [DashboardSupportComplaintController::class, 'update'])->name('dashboard.support_complaints.update');
    Route::delete('/dashboard/support-complaints/{complaint}', [DashboardSupportComplaintController::class, 'destroy'])->name('dashboard.support_complaints.destroy');
});
