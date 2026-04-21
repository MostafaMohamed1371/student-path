# student-path

`student-path` is a Laravel 13 backend for a mobile app + admin dashboard.

Full documentation:
- `docs/PROJECT_DOCUMENTATION.md`

It provides:
- OTP login with Iraqi phone rules
- Sanctum token authentication for mobile APIs
- User Profile and Bus APIs
- Standing Tech SMS/WhatsApp integration
- Admin dashboard for app-level management (users + buses)
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
- OTP is 4 digits and stored as plain text by project requirement
- Expiry, resend cooldown, attempts limit, and API throttling are enforced

### User Profile APIs

Authenticated endpoints:
- `GET /api/user/profile`
- `PUT /api/user/profile`
- `DELETE /api/user/profile`
- `POST /api/user/language`

Profile payload supports:
- `name`
- `image` (file upload)
- `phone`
- `city`
- `licenceNumber`
- `votes`
- `rate`
- `isVerified`

### Bus APIs (Independent from User Profile)

Authenticated endpoints:
- `GET /api/bus/my-bus`
- `POST /api/bus/my-bus`
- `PUT /api/bus/my-bus`
- `DELETE /api/bus/my-bus`

User and Bus are modeled as separate entities for future scalability.

### Admin Dashboard

Web routes:
- `GET /login`
- `GET /dashboard`
- `GET /dashboard/users`
- `GET /dashboard/buses`

Admin capabilities:
- Manage all users (create, edit, delete)
- Manage all buses (create, edit, delete)
- Manage user profile fields from dashboard forms
- Upload profile images
- Color mode toggle in bus form (`Pick color` / `Type color`)

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
- `app/Http/Controllers/Api/*`
- `app/Http/Controllers/Web/*`
- `app/Http/Requests/*`
- `resources/views/dashboard/*`
- `routes/api.php`
- `routes/web.php`

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

## Testing

```bash
php artisan test
```

Feature tests cover OTP flows, auth-protected endpoints, and dashboard CRUD behavior.
