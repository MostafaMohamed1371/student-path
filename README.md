# student-path

`student-path` is a Laravel 13 backend and admin dashboard for a school transport platform.

Full documentation:
- `docs/PROJECT_DOCUMENTATION.md`
- Parent mobile API (`/api`): `docs/API_V1_PARENT_TRANSPORT.md`
- **Support live chat (Pusher):** `docs/CHAT.md` — setup: `docs/PUSHER_CHAT_SETUP.md`
- Postman: `postman/OTP-Auth.postman_collection.json` (core API), `postman/V1-Parent-Transport.postman_collection.json` (parent v1), `postman/User-Chat.postman_collection.json` (chat)

It provides:
- OTP login with Iraqi phone rules
- Sanctum token authentication for mobile APIs
- User, school, student, guardian, driver, and bus APIs (see **Authorization** below)
- Driver self-service bus record under `/api/bus/my-bus` (separate from admin data entry)
- Standing Tech SMS/WhatsApp integration
- Web dashboard with **admin vs school-staff** permissions (read-only lists for non-admins on org data)
- Arabic/English dashboard localization

## Core Features

### Mobile Authentication (OTP)

Endpoints:
- `POST /api/auth/send-otp`
- `POST /api/auth/verify-otp`
- `POST /api/auth/logout` (auth required)
- `GET /api/auth/me` (auth required)

Behavior:
- Client sends Iraqi national mobile number (10 digits, no leading `0`)
- Server normalizes to canonical format: `964` + 10 digits
- OTP works for **existing users only** (login flow; no registration)
- If phone is not found in `users`, auth endpoints return `422`
- OTP is 4 digits and stored as plain text by project requirement
- Expiry, resend cooldown, attempts limit, and API throttling are enforced

### Authorization (API and dashboard)

`users.is_admin` controls who may **create, update, or delete** org-wide data (schools, students, guardians, drivers) in both the **JSON API** and the **web dashboard**.

| | Non-admin | Admin |
|---|-----------|--------|
| **Dashboard** | Scoped **read-only** index pages (no add/edit/delete UI; direct write URLs return **403**) | Full CRUD where implemented |
| **API** `GET` schools, students, guardians, drivers | **Allowed** — results scoped to the user’s school (or linked driver’s school) | All rows |
| **API** `POST` / `PUT` / `DELETE` those resources | **403** `forbidden` | **Allowed** (valid requests) |

Unchanged for any authenticated user (not admin-gated in the same way): **user profile**, **language**, **`GET /api/user/driver`**, and **`/api/bus/my-bus`** (linked driver’s bus). See `docs/PROJECT_DOCUMENTATION.md` §3.1 and `postman/OTP-Auth.postman_collection.json` (use `{{admin_token}}` for mutation calls).

### User Profile APIs

Authenticated endpoints:
- `GET /api/user/profile`
- `PUT /api/user/profile`
- `DELETE /api/user/profile`
- `POST /api/user/language`
- `GET /api/user/driver`

Profile payload supports:
- `name`
- `image` (file upload)
- `phone`
- `city`
- `licenceNumber`
- `votes`
- `rate`
- `isVerified`

### Bus APIs (Driver-Based)

Authenticated endpoints:
- `GET /api/bus/my-bus`
- `POST /api/bus/my-bus`
- `PUT /api/bus/my-bus`
- `DELETE /api/bus/my-bus`

Bus ownership in API flow is linked through the authenticated user's driver record.

### School APIs (auth, org / staff)

- `GET /api/org/schools` — list (school-scoped for non-admins)
- `GET /api/org/schools/{school}` — show (in scope for non-admins)
- `POST`, `PUT`, `DELETE` — **admin only** (403 otherwise)

### Student APIs (auth, org — admin roster; not parent children)

- `GET /api/org/students`, `GET /api/org/students/{student}` — **read**, school-scoped for non-admins
- `POST`, `PUT`, `DELETE` — **admin only**

### Guardian APIs (auth, org)

- `GET /api/org/guardians`, `GET /api/org/guardians/{guardian}` — **read**, school-scoped for non-admins
- `POST`, `PUT`, `DELETE` — **admin only**

### Driver APIs (auth, org)

- `GET /api/org/drivers`, `GET /api/org/drivers/{driver}` — **read**, school-scoped for non-admins
- `POST`, `PUT`, `DELETE` — **admin only**

Parent-app resources (wallet, guardian **children** at `GET /api/students`, trips, etc.) are documented in **`docs/API_V1_PARENT_TRANSPORT.md`** (**`/api/...`** only).

### Web dashboard

Routes include `/login`, `/dashboard`, and modules for schools, students, guardians, drivers, buses, users, and profile (see `routes/web.php`).

- **Admins** — full create/update/delete for the resources above, plus user management (UI is often admin-only for users)
- **Non-admins** — can open **list** pages for their school; **no** add/edit/delete (server returns **403** on writes)

Other dashboard features: file uploads, bus color helpers, overview counters (schools, students, guardians, drivers, users, buses, OTP-related), localized UI

## Tech Stack

- PHP 8.3+
- Laravel 13
- MySQL
- Laravel Sanctum
- Blade (dashboard UI)

## Important Paths

- `app/Services/Otp/OtpService.php`
- `app/Services/Phone/PhoneNormalizer.php`
- `app/Services/Sms/*`
- `app/Http/Resources/*`
- `app/Http/Controllers/Api/*`
- `app/Http/Controllers/Web/*`
- `app/Http/Requests/*`
- `app/Models/*`
- `resources/views/dashboard/*`
- `routes/api.php`
- `routes/web.php`
- `postman/OTP-Auth.postman_collection.json`

## Quick Start

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve
```

Open:
- Login: `http://127.0.0.1:8000/login`
- Dashboard: `http://127.0.0.1:8000/dashboard`

## Default Admin Credentials

Configured by:
- `DASHBOARD_SEED_PHONE`
- `DASHBOARD_SEED_PASSWORD`

Default values:
- Phone (10 digits): `7701234567`
- Password: `12345678`

## Environment Variables

App / DB:
- `APP_NAME=student-path`
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`

Standing Tech:
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

Dashboard seed:
- `DASHBOARD_SEED_PHONE`
- `DASHBOARD_SEED_PASSWORD`

## File Storage

Public uploads are stored in:
- `storage/app/public/profiles`
- `storage/app/public/schools`
- `storage/app/public/drivers`

API resources normalize file URLs to:
- `/student-path/storage/app/public/...`

## Testing

```bash
php artisan test
```

Feature tests cover OTP flows, auth/profile/bus API behavior, and API scoping (including admin-only mutations for schools, students, guardians, drivers).

## Useful Commands

```bash
php artisan otp:prune-expired
```
