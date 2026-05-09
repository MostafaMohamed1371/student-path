# Parent Transport API — `/api`

Mobile parent-app surface. **All URL paths in this document are under `/api/...` only** (there is no `/api/v1` prefix; that prefix was removed so routes are not duplicated). PHP controllers for many parent features still live in the `App\Http\Controllers\Api\V1\` namespace, but the HTTP paths are the ones listed below.

**School/staff org CRUD** (schools, roster students, guardians, drivers, trip admin/history) is under **`/api/org/...`** so it does not collide with parent **`GET /api/students`** (guardian children) and parent **`GET /api/trips/...`**.

Companion artifacts:

- PDF traceability checklist: [`PARENT_APP_API_PDF_CHECKLIST.md`](PARENT_APP_API_PDF_CHECKLIST.md)
- Postman: [`postman/V1-Parent-Transport.postman_collection.json`](../postman/V1-Parent-Transport.postman_collection.json)

---

## Authentication

| Kind | Header / body | Notes |
|------|-----------------|--------|
| **Public (no `Authorization`)** | — | `POST /api/auth/send-otp`, `POST /api/auth/verify-otp`. **`GET /api/support/info`**, **`GET /api/support/categories`**. **`POST /api/webhooks/qicard`** (Qi Card server → app; signed/validated in controller). **`GET` or `POST` `/api/wallet/payments/qicard/finish`** (browser/WebView return after payment; query/body `paymentId` / `payment_id` per implementation). |
| **Authenticated** | `Authorization: Bearer <sanctum_token>` | Everything else under `/api/*` that is not listed as public above, including `POST /api/auth/logout` and `GET /api/auth/me`. |

Tokens are obtained from **`POST /api/auth/verify-otp`** (`data.token`). Throttling: `otp-send`, `otp-verify` (see `AppServiceProvider`).

---

## School scope (non-admin)

Access to school-bound data is computed in `App\Http\Controllers\Api\Concerns\AppliesApiSchoolScoping::apiSchoolScopeSchoolIds()`:

1. `users.school_id` (e.g. staff)
2. Linked **driver**’s `school_id`
3. Resolved **guardian** (`users.guardian_id` or guardian row matching `users.phone`)
4. **`school_id` on every student** belonging to that guardian

**Admins** (`users.is_admin`): no school filter.

**Guardians with children in more than one school** receive a **union** of those school IDs (not only `guardians.school_id`).

Org and legacy endpoints still run as the authenticated user; additional **admin** checks apply on some org mutations (e.g. trips — see `TripHistoryController`).

---

## Response shapes

### Parent transport envelope (`FormatsParentApiResponse`)

Used by parent transport controllers under `Api\V1\` (wallet, home-location, locations, places, meta, students, trips, trip-requests, absences, in-app notifications, QiCard init, etc.):

```json
{
  "success": true,
  "message": "success",
  "msg": "success",
  "data": { }
}
```

Errors: `success: false`, `message` / `msg`, optional `errors`, HTTP 4xx/5xx as applicable.

### Auth controller (`AuthController` for `me`, `logout`, OTP)

Uses `ApiResponse`: `success`, `message`, `data` — **no** duplicate `msg` field.

### Legacy mobile envelope (`RespondsWithLegacySuccess`)

Used by **`LegacyTransactionsController`**, **`LegacyNotificationsController`**, **`LegacySupportController`** (complaint response), **`LegacyUserExtrasController`**:

```json
{
  "success": true,
  "data": [ ],
  "msg": "success"
}
```

### Parent profile (`GET` / `PUT` / `DELETE /api/profile`)

Implemented by `Api\V1\ProfileController`, which **delegates** to `UserProfileController` for show/update/destroy — same business rules as **`/api/user/profile`**, but the parent app is expected to call **`/api/profile`**. **`/api/user/profile`** remains for older clients.

---

## Endpoints

### Public (no Sanctum)

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/auth/send-otp` | Body: **`phone`** (10-digit national, no leading `0`) and required **`type_user`**: `guardian` (see `ParentContext::guardian`: `users.guardian_id`, exact `guardians.phone`, or **10-digit national** when `users.phone` is `964…`), `student` (a `students` row matches this user’s phone as `student_phone`), or **`driver`** (user has a linked `drivers` row via `user_id`). |
| POST | `/api/auth/verify-otp` | Body: `phone`, `code`, and required **`type_user`**. Returns `data.token`, `data.token_type`, and **`data.user`**: includes **`type_user`** (same value as the request), full account (`userId`, …), nested **`driver`** / **`school`** / **`guardian`** when linked. Legacy top-level `id` / `name` / `phone` stay driver-aware when a driver profile exists. |
| GET | `/api/support/info` | Contact methods + FAQs from `config/mobile_legacy_api.php`. |
| GET | `/api/support/categories` | Category list for complaint dropdown (`id` + `label`). |
| POST | `/api/webhooks/qicard` | Qi Card webhook (JSON; `paymentId` / `payment_id` handling in `QiCardWalletPaymentController`). |
| GET, POST | `/api/wallet/payments/qicard/finish` | Return URL after hosted payment (no Bearer; validates payment reference). |

### Authenticated — Parent transport (`FormatsParentApiResponse`)

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/auth/logout` | Revokes current Sanctum token. |
| GET | `/api/auth/me` | Same **`data.user`** shape as verify-otp. **`type_user`** is inferred when multiple roles apply: first match among **`driver`**, **`guardian`**, **`student`** (same matching rules as OTP). |
| GET | `/api/wallet` | Balance + currency. |
| GET | `/api/wallet/transactions` | Query: `per_page` (optional). Paginated `items` + `pagination`. |
| POST | `/api/wallet/recharge` | Body: `amount` (required), optional `reference`, `currency`, `payment_method`, `idempotency_key`. Header: **`Idempotency-Key`** (optional; preferred). Idempotent repeat returns **200** with cached balance. **403** when `QI_CARD_ENABLED=true` and `QI_CARD_BLOCK_DIRECT_RECHARGE=true` (use Qi Card flow below). |
| POST | `/api/wallet/payments/qicard/init` | Qi Card top-up: body `amount` (required), optional `currency`, `locale`, `description`, `customer_info` (object). Requires `QI_CARD_*` env and `QI_CARD_ENABLED=true`. Returns `data.form_url` (hosted payment), `payment_id`, `request_id`. **201** on success; **503** if disabled/misconfigured; **502** if gateway response is invalid. |
| GET | `/api/home-location` | Saved home location or `data: null`. |
| POST | `/api/home-location` | Body: `latitude`, `longitude`, optional `formatted_address`, `place_id` (upsert). |
| DELETE | `/api/home-location` | Removes the row for the current user. |
| GET | `/api/locations/districts` | List districts. |
| GET | `/api/locations/districts/{district}/areas` | Areas for district. |
| GET | `/api/locations/iraq` | **`data`**: array of districts → areas → **neighborhoods** (`distance_km` = Haversine km from reference). **Default reference** (when `latitude`/`longitude` omitted): approximate centre of Iraq — see `config/locations.php` / env `LOCATIONS_IRAQ_REF_*`. **Optional query** `latitude`+`longitude`: distances from the client point instead (`distance_reference.source` = `request` vs `iraq_default`). Optional **`max_radius_km`**: exclude neighborhoods farther than radius from the active reference. Response also includes root **`distance_reference`** `{ latitude, longitude, source, label }`. |
| GET | `/api/places/autocomplete` | Query: `input`, optional `sessiontoken`. Requires `GOOGLE_PLACES_API_KEY`; otherwise **503**. Throttle: `google-places`. |
| GET | `/api/places/{place}` | Google Place Details for `{place}` (place_id). Throttle: `google-places`. |
| GET | `/api/meta/schools` | Query: optional `format=minimal` (`id` + `name`) or full `SchoolResource` list; school-scoped for non-admins. |
| GET | `/api/meta/grades` | Grade catalog. |
| GET | `/api/students` | Parent: linked students (+ `current_trip`). Guardian with no students: empty list (no full-school leak). |
| POST | `/api/students` | Parent creates student; requires resolved guardian. Validation allows null `district_area` / `nearest_landmark`, but the DB columns are **NOT NULL** — omitting them causes a **500**; send strings (see Postman example). |
| GET | `/api/students/{student}` | In school scope; if the user has a resolved **guardian**, they must **own** the student. |
| PUT, PATCH | `/api/students/{student}` | **Admin:** any student in scope. **Parent:** must own the student. Partial fields allowed (`sometimes` rules). |
| DELETE | `/api/students/{student}` | **Admin** or **owning** parent. Cascades related `trip_requests` / `absences` (FK). |
| GET | `/api/trips/available` | Query: optional `student_id`. Future/non-cancelled trips from relevant schools. |
| GET | `/api/trips/active` | Query: optional `student_id`. Current trip payload(s). |
| GET | `/api/trips/{trip}` | Trip detail (parent-scoped school). |
| GET | `/api/trips/{trip}/driver` | Driver + bus when resolvable from trip. |
| GET | `/api/trip-tracking/config` | Echo / Firebase hints for realtime. |
| POST | `/api/trip-requests` | Body: `student_id`, optional `trip_history_id`, `notes`. |
| GET | `/api/trip-requests` | Paginated list for current user. |
| GET | `/api/trip-requests/{trip_request}` | |
| PUT, PATCH | `/api/trip-requests/{trip_request}` | Only while `status` is `pending`. Body: optional `student_id`, `trip_history_id`, `notes` (same scoping rules as create). |
| POST | `/api/trip-requests/{trip_request}/cancel` | Sets `cancelled` + `cancelled_at`. |
| DELETE | `/api/trip-requests/{trip_request}` | Only while `pending` (hard delete). Cancelled/completed → **422**. |
| POST | `/api/absences` | Body: `student_id`, `start_date`, `end_date`, `reason`, optional `notes`. |
| GET | `/api/absences` | Query: optional `student_id`, `from`, `to`, `per_page`. |
| GET | `/api/absences/{absence}` | |
| PUT, PATCH | `/api/absences/{absence}` | Owner only; `end_date` must be ≥ `start_date` after merge. |
| DELETE | `/api/absences/{absence}` | Owner only. |
| GET | `/api/profile` | Delegates to `UserProfileController::show`. |
| PUT | `/api/profile` | Same payload rules as `/api/user/profile` (via `UpdateUserProfileRequest`). |
| DELETE | `/api/profile` | Deletes account and revokes tokens (delegates to `UserProfileController::destroy`). |
| GET | `/api/in-app-notifications` | Query: optional `unread_only`, `per_page`. Includes `unread_count`. Paginated parent contract. |
| POST | `/api/in-app-notifications/read` | Body: optional `ids` (array); omit to mark all unread as read. |
| DELETE | `/api/in-app-notifications/{notification}` | Deletes one row for the current user. |

### Authenticated — Legacy mobile (flat `success` / `data` / `msg`)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/transactions` | Legacy wallet transaction list for the current user’s wallet. Query: optional `limit` (default 50, max 100). Items: `id` (`#TXN-{id}`), `amount`, `date` (ISO8601), `title`, `status` (`COMPLETED` / `PENDING` / `FAILED` from `meta` when set), `type` (`CHARGE` / `PAYMENT` / `WITHDREW`). **Different** from paginated **`GET /api/wallet/transactions`**. |
| GET | `/api/notifications` | Legacy flat list from `in_app_notifications`. Query: optional `limit`. Fields: `id`, `type`, `title`, `body`, `time_ago`, `is_read`. |
| GET | `/api/haveNewMessages` | `data.hasNewMessages`, `data.unreadCount` (legacy shape). |

### Authenticated — Support (complaint)

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/support/complaint` | `multipart/form-data`: **`category_id`** (required, must match an `id` from **`GET /api/support/categories`**), **`details`** (required), optional **`attachment`** (jpg/png, max 5MB). Response **201** with `complaintNumber`, `status`, `submittedAt`, `attachmentCount`. |

### Authenticated — User (legacy paths)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/user/profile` | Same resource as `/api/profile` for show/update/delete, different URL. |
| PUT | `/api/user/profile` | |
| DELETE | `/api/user/profile` | |
| POST | `/api/user/language` | Body: `language` — `en` or `ar` (`ChangeLanguageRequest`). |
| GET | `/api/user/driver` | Linked driver for current user (`DriverController@myDriver`). |
| GET | `/api/user/settings/notifications` | Toggles from `config/mobile_legacy_api.notification_settings`. |
| GET | `/api/user/performance` | Driver/performance-style payload (ratings, trip stats, etc.; partly static/demo fields — see `LegacyUserExtrasController`). |

### Authenticated — Bus

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/bus/my-bus` | Current user’s bus (if any). |
| POST | `/api/bus/my-bus` | Create — `StoreBusRequest` (`busName`, `busType`, `busCity`, `busNumber`, …). |
| PUT | `/api/bus/my-bus` | Update — `UpdateBusRequest` (`sometimes` fields). |
| DELETE | `/api/bus/my-bus` | |

### Authenticated — Org (`/api/org/...`)

Standard REST under Sanctum. **Route order:** `GET /api/org/trips/history` is registered **before** `GET /api/org/trips/{trip}` so `history` is not captured as an id.

| Prefix | Methods | Notes |
|--------|---------|--------|
| `/api/org/schools` | GET `/`, POST `/`, GET `/{school}`, PUT `/{school}`, DELETE `/{school}` | `StoreSchoolRequest` / updates per controller. |
| `/api/org/drivers` | GET `/`, POST `/`, GET `/{driver}`, PUT `/{driver}`, DELETE `/{driver}` | `StoreDriverRequest` / `UpdateDriverRequest`. |
| `/api/org/students` | GET `/`, POST `/`, GET `/{student}`, PUT `/{student}`, DELETE `/{student}` | Org roster (not parent **`/api/students`**). |
| `/api/org/guardians` | GET `/`, POST `/`, GET `/{guardian}`, PUT `/{guardian}`, DELETE `/{guardian}` | |
| `/api/org/trips` | GET `/history`, GET `/`, POST `/`, GET `/{trip}`, PUT `/{trip}`, DELETE `/{trip}` | Trip admin/history; mutations may require admin (`TripHistoryController`). |

---

## Not full CRUD (by design)

| Area | Notes |
|------|--------|
| **Wallet** | Read balance + paginated transactions + **POST recharge** (optional block when Qi Card enabled) + **POST …/qicard/init** (hosted top-up) + public **webhook/finish**. No delete wallet / edit ledger rows via parent API. |
| **Parent trips** | Read-only for parents (`available` / `active` / `show` / `driver`). Full trip CRUD for staff is **`/api/org/trips`** (+ `history`). |
| **Meta / locations / places** | Read-only reference and Google proxy. |
| **Legacy lists** | **`/api/transactions`** and **`/api/notifications`** are convenience/contract lists; canonical paginated notifications are **`/api/in-app-notifications`**. |

---

## Realtime / broadcasting

- Private channel pattern: `trip.{tripHistoryId}` — see `routes/channels.php`.
- HTTP auth: `Broadcast::routes` with `auth:sanctum` in `AppServiceProvider`.
- Config: `config/realtime.php`, env vars in `.env.example` (`BROADCAST_DRIVER`, `PUSHER_*`, `FIREBASE_*`).

---

## Environment variables (summary)

| Variable | Used for |
|----------|----------|
| `GOOGLE_PLACES_API_KEY` | Places autocomplete/details |
| `BROADCAST_DRIVER`, `PUSHER_*` | Laravel Echo / websockets |
| `FIREBASE_PROJECT_ID`, `FIREBASE_DATABASE_URL` | Hints in tracking config |
| `QI_CARD_*`, `QI_CARD_ENABLED`, `QI_CARD_BLOCK_DIRECT_RECHARGE` | Qi Card wallet init/webhook/finish (see `.env.example` and `QiCardWalletPaymentController`) |
| `MOBILE_SUPPORT_*` | Optional overrides for `GET /api/support/info` (WhatsApp, phone, live chat) |

---

## Web dashboard (staff)

The authenticated web UI under **`/dashboard`** mirrors much of the same domain with **session login** (not Sanctum). It already managed org entities (schools, students, guardians, drivers, trips, buses, users) aligned with **`/api/org/...`**. The following **parent-API** data is also visible there with the **same school / user scoping** as payments and in-app notifications:

- **`/dashboard/trip-requests`** — full **CRUD** (list, create, show, edit, delete when **`pending`**, plus **`PUT …/status`** for **`accepted`** / **`rejected`**). On **accepted**, backend auto-creates a `trip_histories` row and links it to `trip_requests.trip_history_id`. Scoped by student’s `school_id` for staff. Parent **`POST /api/trip-requests/.../cancel`** only works from **`pending`**.
- **`/dashboard/absences`** — full **CRUD** for absences tied to students in scope (parent `user_id` is resolved from the student’s guardian).
- **`/dashboard/support-complaints`** — full **CRUD** for complaints in user scope (same rules as payments/notifications); categories must match **`config/mobile_legacy_api`**.

Overview counts on **`/dashboard`** include trip requests, absences, and complaints in scope.

---

## Testing

Feature tests (file names still contain `ApiV1` for history): `tests/Feature/ApiV1ModulesTest.php`, `tests/Feature/ApiV1ParentEndpointsTest.php`, plus wallet/QiCard and merged-route tests as applicable. Run:

```bash
php artisan test
```
