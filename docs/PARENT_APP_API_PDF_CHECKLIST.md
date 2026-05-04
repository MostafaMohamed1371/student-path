# Parent Transport API — PDF traceability checklist

This file maps the **Backend API Specification – Parent Transport System** (PDF; not stored in this repository) to concrete routes and automated tests. When reviewing against the PDF, go row by row: confirm the HTTP contract in the PDF matches the route, then run or extend the listed test.

Human-readable API reference: [`API_V1_PARENT_TRANSPORT.md`](API_V1_PARENT_TRANSPORT.md). Postman: [`postman/V1-Parent-Transport.postman_collection.json`](../postman/V1-Parent-Transport.postman_collection.json).

**Convention:** Authenticated parent routes are under **`/api/...`** (Bearer token). Public OTP is **`/api/auth/*`**. Paginated in-app notifications: **`/api/in-app-notifications`**. The separate legacy flat list for older PDF clients is **`GET /api/notifications`** (different handler than in-app).

| # | Spec area (verify against your PDF) | Method & path | Implementation | Automated test |
|---|-------------------------------------|---------------|----------------|----------------|
| 1 | Public OTP — send code | `POST /api/auth/send-otp` | `routes/api.php` → `AuthController@sendOtp` | `AuthOtpTest` (legacy path); `ApiV1ParentEndpointsTest::test_v1_public_send_otp_matches_legacy_validation` |
| 2 | Public OTP — verify | `POST /api/auth/verify-otp` | `AuthController@verifyOtp` | `AuthOtpTest` (legacy path); mirror with `/api/...` when adding a dedicated test |
| 3 | Logout | `POST /api/auth/logout` | `AuthController@logout` | `AuthOtpTest::test_logout_success` uses `/api/auth/logout`; same controller for `/api/...` |
| 4 | Current user | `GET /api/auth/me` | `AuthController@me` | `ApiV1ParentEndpointsTest::test_v1_auth_me_under_prefix` |
| 5 | Wallet balance | `GET /api/wallet` | `V1\WalletController@show` | `ApiV1ModulesTest::test_v1_wallet_and_meta_endpoints` |
| 6 | Wallet transactions | `GET /api/wallet/transactions` | `V1\WalletController@transactions` | `ApiV1ModulesTest::test_v1_wallet_and_meta_endpoints` |
| 7 | Wallet recharge (idempotency) | `POST /api/wallet/recharge` | `V1\WalletController@recharge` | `ApiV1ModulesTest` (recharge); `ApiV1ParentEndpointsTest::test_v1_wallet_recharge_idempotent` |
| 8 | Home location | `GET /api/home-location`, `POST /api/home-location` | `V1\HomeLocationController` | `ApiV1ParentEndpointsTest::test_v1_home_location_show_and_store` |
| 9 | Districts | `GET /api/locations/districts` | `V1\LocationController@districts` | `ApiV1ModulesTest`; `ApiV1ParentEndpointsTest::test_v1_district_areas` |
| 10 | Areas | `GET /api/locations/districts/{district}/areas` | `V1\LocationController@areas` | `ApiV1ParentEndpointsTest::test_v1_district_areas` |
| 11 | Google Places autocomplete | `GET /api/places/autocomplete` | `V1\PlacesController@autocomplete` | `ApiV1ModulesTest::test_v1_places_without_key_returns_503` |
| 12 | Place details | `GET /api/places/{place}` | `V1\PlacesController@details` | Add when stubbing HTTP; unconfigured key → 503 (same as autocomplete) |
| 13 | Meta schools | `GET /api/meta/schools` | `V1\MetaController@schools` | `ApiV1ParentEndpointsTest::test_v1_meta_schools_minimal_lists_id_and_display_name` |
| 14 | Meta grades | `GET /api/meta/grades` | `V1\MetaController@grades` | `ApiV1ModulesTest::test_v1_wallet_and_meta_endpoints` |
| 15 | Parent students — list | `GET /api/students` | `V1\ParentStudentController@index` | `ApiV1ParentEndpointsTest::test_v1_parent_students_index_includes_linked_child` |
| 16 | Parent students — show | `GET /api/students/{student}` | `V1\ParentStudentController@show` | `ApiV1ParentEndpointsTest::test_v1_parent_students_index_includes_linked_child` |
| 17 | Parent students — create | `POST /api/students` | `V1\ParentStudentController@store` | `ApiV1ParentEndpointsTest::test_v1_parent_can_create_student_when_guardian_linked` |
| 18 | Trips available (optional `student_id`) | `GET /api/trips/available` | `V1\TripParentController@available` | `ApiV1ParentEndpointsTest::test_v1_trips_available_active_show_and_driver` |
| 19 | Trips active | `GET /api/trips/active` | `V1\TripParentController@active` | `ApiV1ParentEndpointsTest::test_v1_trips_available_active_show_and_driver` |
| 20 | Trip detail | `GET /api/trips/{trip}` | `V1\TripParentController@show` | `ApiV1ParentEndpointsTest::test_v1_trips_available_active_show_and_driver` |
| 21 | Trip driver / bus | `GET /api/trips/{trip}/driver` | `V1\TripParentController@driver` | `ApiV1ParentEndpointsTest::test_v1_trips_available_active_show_and_driver` |
| 22 | Realtime / tracking hints | `GET /api/trip-tracking/config` | `V1\TrackingInfoController` | `ApiV1ModulesTest::test_v1_wallet_and_meta_endpoints` |
| 23 | Trip request — create | `POST /api/trip-requests` | `V1\TripRequestController@store` | `ApiV1ParentEndpointsTest::test_v1_trip_requests_and_cancel` |
| 24 | Trip request — list | `GET /api/trip-requests` | `V1\TripRequestController@index` | `ApiV1ParentEndpointsTest::test_v1_trip_requests_and_cancel` |
| 25 | Trip request — show | `GET /api/trip-requests/{trip_request}` | `V1\TripRequestController@show` | `ApiV1ParentEndpointsTest::test_v1_trip_requests_and_cancel` |
| 26 | Trip request — cancel | `POST /api/trip-requests/{trip_request}/cancel` | `V1\TripRequestController@cancel` | `ApiV1ParentEndpointsTest::test_v1_trip_requests_and_cancel` |
| 27 | Absence — create | `POST /api/absences` | `V1\AbsenceController@store` | `ApiV1ParentEndpointsTest::test_v1_absences_index_and_show` |
| 28 | Absence — list / filter | `GET /api/absences` | `V1\AbsenceController@index` | `ApiV1ParentEndpointsTest::test_v1_absences_index_and_show` |
| 29 | Absence — show | `GET /api/absences/{absence}` | `V1\AbsenceController@show` | `ApiV1ParentEndpointsTest::test_v1_absences_index_and_show` |
| 30 | Notifications — list (`unread_only`, `unread_count`) | `GET /api/in-app-notifications` | `V1\InAppNotificationController@index` | `ApiV1ParentEndpointsTest::test_v1_notifications_unread_and_mark_read` |
| 31 | Notifications — mark read | `POST /api/in-app-notifications/read` | `V1\InAppNotificationController@markRead` | `ApiV1ParentEndpointsTest::test_v1_notifications_unread_and_mark_read` |
| 32 | Profile | `GET /api/profile`, `PUT /api/profile` | `V1\ProfileController` → `UserProfileController` | `ApiV1ModulesTest::test_v1_profile_put_delegates`; `ApiV1ParentEndpointsTest::test_v1_profile_get` |

## Broadcasting (PDF “realtime”)

| # | Spec area | Notes |
|---|-----------|--------|
| B1 | Private channel `trip.{tripHistoryId}` | `routes/channels.php` — parents whose students appear on the trip, admins, school-scoped staff. |
| B2 | Auth for broadcasting | `AppServiceProvider`: `Broadcast::routes(['middleware' => ['auth:sanctum']])`. |
| B3 | Env | `BROADCAST_DRIVER`, `PUSHER_*`, optional Firebase — see `config/realtime.php` and `.env.example`. |

No PHPUnit coverage asserts a live WebSocket; verify with a Pusher-compatible client or Laravel Echo after configuring credentials.

## Response envelope (PDF vs legacy)

Many `/api/*` JSON responses use `FormatsParentApiResponse`: `success`, `message`, `msg`, and `data`. Auth handlers (`/api/auth/me`, logout, OTP) reuse `ApiResponse` (`success`, `message`, `data` — no `msg`). When the PDF mandates a single shape, align in code and update this table.

## Maintenance

When you add or rename a parent-app endpoint:

1. Update `routes/api.php`.
2. Add or adjust a row in the table above.
3. Add a feature test and reference it in the **Automated test** column.
