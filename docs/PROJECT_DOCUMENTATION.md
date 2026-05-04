# Student Path - Project Documentation

## 1) Project Overview

Student Path is a Laravel 13 backend + admin dashboard for school transportation operations.

Current scope includes:
- OTP mobile login (phone-based)
- Sanctum-protected APIs
- School, student, guardian, driver, and bus management
- Bus self-service for linked drivers (`/api/bus/my-bus`, separate from admin data entry)
- User profile management
- Web dashboard modules for all entities, with **role-based write access** (see §3.1)

The platform uses Iraqi phone normalization rules and supports Arabic/English dashboard UI.

## 2) Architecture Summary

Main layers:
- **Controllers**: API + web request handling
- **Form Requests**: validation rules and payload mapping
- **Services**: OTP, phone normalization, SMS gateway
- **Models**: `User`, `OtpCode`, `School`, `Student`, `Guardian`, `Driver`, `Bus`
- **Resources**: API response DTO shaping
- **Views**: Blade-based admin dashboard

## 3) Authentication Flow

### OTP Login (Mobile)
1. Client calls `POST /api/auth/send-otp` with a 10-digit national phone.
2. Server normalizes phone to `964` + national digits.
3. Server checks `users.phone` first (**login-only policy**).
4. If phone exists and account is active, OTP is generated and sent via configured SMS sender.
5. Client verifies with `POST /api/auth/verify-otp`.
6. Server issues Sanctum token for that existing user.

Notes:
- If phone is not found in `users`, auth endpoints return validation error (`422`).
- OTP auth does **not** create users or related records (no registration flow).
- OTP auth does **not** auto-create driver/student/guardian/school data.

### Driver linkage behavior
- User accounts can be linked to drivers (`drivers.user_id`).
- API profile/auth resources prefer driver identity fields when linked.

### Auth protection
- API routes use `auth:sanctum`.
- Dashboard uses web session auth (phone + password).

### 3.1) Authorization: admin vs school staff (API and dashboard)

Users have `users.is_admin` (boolean). Behavior is aligned between the **web dashboard** and the **JSON API** for org-wide resources.

| Area | Non-admin (school staff / driver-linked scope) | Admin (`is_admin = true`) |
|------|------------------------------------------------|----------------------------|
| **Dashboard** — schools, students, guardians, drivers, buses | **Read-only** list views, scoped to their school (where applicable). **No** add/edit/delete. | Full CRUD. |
| **Dashboard** — users | Typically hidden / restricted to admins (see routes). | Full user management. |
| **API** — `GET` org schools, students, guardians, drivers (`/api/org/...`) | **Allowed**, data limited to the user’s effective school (see `AppliesApiSchoolScoping`: `users.school_id` or linked `driver.school_id`). | Sees all rows. |
| **API** — `POST` / `PUT` / `DELETE` on org schools, students, guardians, drivers | **403** `forbidden` | **Allowed** (valid payloads and validation). |

**Not** subject to the above admin-only write rule (still authenticated):

- `GET/PUT/DELETE` **user profile**, `POST` **language**, `GET` **/api/user/driver** — the signed-in user’s own data.
- **`/api/bus/my-bus`** — the authenticated user’s bus when they have a linked **driver** record (driver self-service, not the admin “buses” list).

Implementation references:

- API: `app/Http/Controllers/Api/Concerns/AppliesApiSchoolScoping.php` — `ensureApiAdminForMutations()`.
- Web: dashboard controllers `abort_unless($this->isAdmin(), 403)` on `create` / `store` / `edit` / `update` / `destroy` for the same resources.

**Postman**: use one Sanctum token in `{{token}}` for reads, and a separate **`{{admin_token}}`** (from an `is_admin` user) for mutation requests on **`/api/org/...`**. See `postman/OTP-Auth.postman_collection.json`.

## 4) Phone Number Rules

- Input format: exactly 10 digits
- Must not start with `0`
- Canonical storage format: `964` + 10 digits

Implementation:
- `app/Services/Phone/PhoneNormalizer.php`
- Request preprocessing traits under `app/Http/Requests/Concerns`

## 5) API Endpoints

Route source:
- `routes/api.php`

### Parent transport API (mobile, canonical **`/api/...`**)

Parent-app endpoints (wallet, home location, districts/areas, Places proxy, meta, **guardian** students & trips, trip requests, absences, in-app notifications, parent profile, tracking config) are under **`/api/...`** (no `v1` prefix). **Paginated** in-app notifications: **`/api/in-app-notifications`**; **`GET /api/notifications`** is the **legacy flat PDF list**. Full reference: **`docs/API_V1_PARENT_TRANSPORT.md`**. Postman: **`postman/V1-Parent-Transport.postman_collection.json`**.

### Org / staff CRUD API (`/api/org/...`)

To avoid colliding with parent **`GET /api/students`** and **`GET /api/trips/{trip}`**, the **school administration** JSON resources previously at **`/api/schools`**, **`/api/students`**, **`/api/guardians`**, **`/api/drivers`**, and **`/api/trips`** are now under **`/api/org/...`** (same controllers, same admin write rules). Postman: **`postman/OTP-Auth.postman_collection.json`**.

### Legacy mobile PDF helpers (same token)

| Endpoint | Role |
|----------|------|
| `GET /api/transactions` | Flat wallet-style list (`#TXN-*`, enums). |
| `GET /api/notifications`, `GET /api/haveNewMessages` | Legacy notification list / unread hint (not the same JSON as `GET /api/in-app-notifications`). |
| `GET /api/user/settings/notifications`, `GET /api/user/performance` | Config / summary helpers. |
| `GET /api/support/*`, `POST /api/support/complaint` | Support bundle + complaints. |

### Auth APIs
- `POST /api/auth/send-otp`
- `POST /api/auth/verify-otp`
- `POST /api/auth/logout` (auth)
- `GET /api/auth/me` (auth)

### User APIs
- `GET /api/user/profile` (auth)
- `PUT /api/user/profile` (auth)
- `DELETE /api/user/profile` (auth)
- `POST /api/user/language` (auth)
- `GET /api/user/driver` (auth)

### Bus APIs (driver-based ownership)
- `GET /api/bus/my-bus` (auth)
- `POST /api/bus/my-bus` (auth)
- `PUT /api/bus/my-bus` (auth)
- `DELETE /api/bus/my-bus` (auth)

### School APIs (org)
- `GET /api/org/schools` (auth) — school-scoped list for non-admins
- `GET /api/org/schools/{school}` (auth) — non-admin only if the school is in scope
- `POST /api/org/schools` (auth, **admin only**)
- `PUT /api/org/schools/{school}` (auth, **admin only**)
- `DELETE /api/org/schools/{school}` (auth, **admin only**)

### Student APIs (org — admin/staff resource)
- `GET /api/org/students` (auth) — school-scoped list for non-admins
- `GET /api/org/students/{student}` (auth) — non-admin only in scope
- `POST /api/org/students` (auth, **admin only**)
- `PUT /api/org/students/{student}` (auth, **admin only**)
- `DELETE /api/org/students/{student}` (auth, **admin only**)

### Guardian APIs (org)
- `GET /api/org/guardians` (auth) — school-scoped list for non-admins
- `GET /api/org/guardians/{guardian}` (auth) — non-admin only in scope
- `POST /api/org/guardians` (auth, **admin only**)
- `PUT /api/org/guardians/{guardian}` (auth, **admin only**)
- `DELETE /api/org/guardians/{guardian}` (auth, **admin only**)

### Driver APIs (org)
- `GET /api/org/drivers` (auth) — school-scoped list for non-admins
- `GET /api/org/drivers/{driver}` (auth) — non-admin only in scope
- `POST /api/org/drivers` (auth, **admin only**)
- `PUT /api/org/drivers/{driver}` (auth, **admin only**)
- `DELETE /api/org/drivers/{driver}` (auth, **admin only**)

### Trip history & admin trips (org)
- `GET /api/org/trips/history` (auth) — UI-oriented history with filters
- `GET /api/org/trips` (auth) — school-scoped index for non-admins
- `GET /api/org/trips/{trip}` (auth)
- `POST /api/org/trips` (auth, **admin only**)
- `PUT /api/org/trips/{trip}` (auth, **admin only**)
- `DELETE /api/org/trips/{trip}` (auth, **admin only**)

## 6) API Response Style

Current API style for newer endpoints:
- `success`
- `data`
- `msg` (or `message` in some auth responses)

For exact request bodies, headers, and which calls use `admin_token` vs `token`:
- `postman/OTP-Auth.postman_collection.json` (collection description + per-request notes)

## 7) Data Model

### User
Core fields:
- `id`, `name`, `phone`, `password`, `is_active`, `is_admin`
- profile fields: `image`, `city`, `licence_number`, `votes`, `rate`, `is_verified`
- `preferred_language`
- optional `school_id`

Relations:
- `driver` (hasOne)
- `school` (belongsTo)
- `bus` (legacy relation retained)

### Driver
Core fields:
- `id`, `user_id`, `school_id`
- personal: `first_name`, `father_name`, `grandfather_name`, `last_name`, `age`
- identity: `id_card_number`, `license_number`
- contact: `primary_phone`, `emergency_phone`, `residential_address`
- status + docs: `status`, `id_card_image`, `license_image`, `non_conviction_certificate`

### School
Core fields:
- names (`name_ar`, `name_en`)
- location/admin/contact fields
- `attachment`

### Student / Guardian
- Students belong to a `school` and a `guardian` (and duplicate guardian contact fields for convenience where needed).
- Guardians belong to a `school`. See migrations under `database/migrations/`.

### Bus
Core fields:
- `driver_id` (dashboard + API ownership key)
- `user_id` (legacy compatibility)
- `name`, `type`, `city`, `number`, `color`, `capacity`, `fuel_type`, `status`
- `annual_status`, `insurance`

### OtpCode
- `phone`, `code`, `purpose`, `expires_at`, `resend_available_at`, `attempts`, `max_attempts`, `verified_at`

Migration source:
- `database/migrations/*`

## 8) File Storage Paths

Uploads are stored on public disk under:
- `storage/app/public/profiles`
- `storage/app/public/schools`
- `storage/app/public/drivers`

API resources normalize file paths to:
- `/student-path/storage/app/public/...`

## 9) Admin Dashboard

Route source:
- `routes/web.php`

View source:
- `resources/views/dashboard/*`

Modules:
- Overview (`/dashboard`)
- Schools, Students, Guardians, Drivers, Buses
- Users (`/dashboard/users`) — typically **admin** menu / routes only
- Admin profile (`/dashboard/profile`)

Current behavior:
- **Admins** (`is_admin`): full create/update/delete for schools, students, guardians, drivers, buses, and users; add buttons and action columns in index tables.
- **Non-admins**: can open scoped **index** and **view**-style list pages (their school, where applicable); add/edit/delete actions are **hidden in the UI** and return **HTTP 403** if URLs are called directly.
- School-first / driver-first workflows are enforced in forms where still relevant.
- Overview cards summarize schools, drivers, users, buses, students, guardians, and OTP-related metrics (see `DashboardHomeController` / views).
- **Parent-app data (payments & in-app notifications):** **`GET /dashboard/payments`** — paginated **wallet transactions** and **Qi Card wallet payment** rows (when migrated) for users in the viewer’s dashboard scope; **`GET /dashboard/in-app-notifications`** — paginated **in-app notifications**. Admins see all users; school staff see rows whose **user** matches the same school scoping as other dashboard modules (`school_id`, linked **guardian** school, or linked **driver** school). Implementation: `App\Http\Controllers\Web\DashboardReportsController`, views `resources/views/dashboard/payments.blade.php` and `in-app-notifications.blade.php`.

## 10) Qi Card wallet payments (Iraq)

Wallet top-up can go through **Qi Card** (HTTP integration aligned with common gateway usage: create payment, then poll `/payment/{id}/status` with Basic Auth and `X-Terminal-Id`).

**Configuration** (see `.env.example` and `config/qicard.php`):

- `QI_CARD_ENABLED`, `QI_CARD_API_HOST`, `QI_CARD_USERNAME`, `QI_CARD_PASSWORD`, `QI_CARD_TERMINAL_ID`
- Optional absolute URLs: `QI_CARD_FINISH_PAYMENT_URL`, `QI_CARD_NOTIFICATION_URL` (otherwise derived from `APP_URL`)
- `QI_CARD_BLOCK_DIRECT_RECHARGE` — when `true` (default) and Qi Card is enabled, `POST /api/wallet/recharge` returns **403** so balance is only increased after a verified gateway payment.

**Flow:**

1. App: `POST /api/wallet/payments/qicard/init` (Sanctum) → open `data.form_url` (WebView / browser).
2. Qi Card calls **`POST /api/webhooks/qicard`** and/or redirects the user to **`/api/wallet/payments/qicard/finish`** with `paymentId`.
3. Server **always** confirms status with Qi Card’s status API before crediting the wallet **once** (`wallet_qicard_payments.credited_at` + locked wallet row). Do not trust callback body alone.

**Code:** `App\Services\Payments\QiCardPaymentClient`, `WalletQiCardPaymentService`, `App\Http\Controllers\Api\V1\QiCardWalletPaymentController`. **Docs:** [Qi developers portal](https://developers-gate.qi.iq).

## 11) SMS Integration (Standing Tech)

`.env` keys:
- `STANDINGTECH_SMS_URL`
- `STANDINGTECH_BEARER_TOKEN`
- `STANDINGTECH_SENDER_ID`
- `STANDINGTECH_TYPE`
- `STANDINGTECH_LANG`
- `STANDINGTECH_RECIPIENT_FORMAT`
- `STANDINGTECH_RECIPIENT_PREFIX`
- `STANDINGTECH_MOBILE_TRUNK`
- `STANDINGTECH_STRIP_INTERNATIONAL_PREFIX`
- `STANDINGTECH_MOCK`
- `STANDINGTECH_TIMEOUT`

Implementation:
- `app/Services/Sms/StandingTechSmsSender.php`
- `app/Services/Sms/FakeSmsSender.php`

## 12) Setup and Run

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve
```

Default admin credentials:
- Phone: `7701234567`
- Password: `12345678`

## 13) Testing

```bash
php artisan test
```

Coverage includes OTP, API school scoping, admin-only mutation rules for org resources, and key dashboard behavior.

## 14) Operational Notes

- OTP cleanup command:
  - `php artisan otp:prune-expired`
- Keep `STANDINGTECH_MOCK=true` in non-production testing environments.
- Ensure `storage:link` exists for public file access.
