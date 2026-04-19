# student-path

`student-path` is a Laravel 13 backend project for a mobile-first authentication flow and a web dashboard.

It includes:

- Phone-based OTP authentication API (Iraqi format: 10 national digits, static `+964`)
- Laravel Sanctum token auth for mobile clients
- OTP security controls (hashing, expiry, cooldown, attempts, throttling)
- Standing Tech SMS/WhatsApp integration (configurable)
- Web dashboard login (phone + password) with sidebar and Users CRUD
- Arabic/English language switch support for dashboard screens

## Main Features

### 1) Mobile OTP Authentication API

API endpoints:

- `POST /api/auth/send-otp`
- `POST /api/auth/verify-otp`
- `POST /api/auth/logout` (Sanctum protected)
- `GET /api/auth/me` (Sanctum protected)

Behavior highlights:

- User enters only **10 digits** (no leading `0`)
- Server normalizes to canonical phone: `964` + 10 digits
- OTP is 4 digits, stored hashed in DB
- Expiry is 5 minutes
- Resend cooldown is 30 seconds
- Max attempts per OTP record is enforced
- User auto-created only after successful verification

### 2) SMS Provider Integration (Standing Tech)

Config is handled through `STANDINGTECH_*` variables in `.env`.

Supports:

- Real sending (`STANDINGTECH_MOCK=false`)
- Log-only mock mode (`STANDINGTECH_MOCK=true`)
- Sender, type (e.g. `whatsapp`), language, recipient formatting controls

### 3) Web Dashboard

Web routes:

- `GET /login`
- `GET /dashboard`
- `GET /dashboard/users`
- User CRUD:
  - create user
  - edit user
  - delete user

Dashboard includes:

- Sidebar navigation (Overview / Users)
- Project overview cards (users, active users, OTP records)
- User management table and forms

## Tech Stack

- PHP 8.3+
- Laravel 13
- MySQL
- Laravel Sanctum
- Blade views (dashboard)

## Project Structure (important parts)

- `app/Services/Otp/OtpService.php` - OTP generation/verification logic
- `app/Services/Phone/PhoneNormalizer.php` - Iraqi phone normalization/validation
- `app/Services/Sms/*` - SMS abstraction + Standing Tech integration
- `app/Http/Controllers/Api/*` - auth API controllers
- `app/Http/Controllers/Web/*` - dashboard controllers
- `app/Http/Requests/*` - validation for API and dashboard forms
- `resources/views/dashboard/*` - dashboard UI
- `routes/api.php` - API routes
- `routes/web.php` - web routes

## Quick Start

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

Open:

- Login page: `http://127.0.0.1:8000/login`
- Dashboard: `http://127.0.0.1:8000/dashboard`

## Default Dashboard Credentials

Configured by:

- `DASHBOARD_SEED_PHONE`
- `DASHBOARD_SEED_PASSWORD`

Current defaults:

- phone (10 digits): `7701234567`
- password: `12345678`

## Environment Notes

Important `.env` groups:

- App:
  - `APP_NAME=student-path`
- DB:
  - `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- SMS:
  - `STANDINGTECH_SMS_URL`
  - `STANDINGTECH_BEARER_TOKEN`
  - `STANDINGTECH_SENDER_ID`
  - `STANDINGTECH_TYPE`
  - `STANDINGTECH_LANG`
  - `STANDINGTECH_MOCK`
- Dashboard seed:
  - `DASHBOARD_SEED_PHONE`
  - `DASHBOARD_SEED_PASSWORD`

## Testing

Run all tests:

```bash
php artisan test
```

Includes feature tests for:

- OTP flow
- resend cooldown
- verification success/failure/expiry
- logout and me endpoints
- dashboard login and Users CRUD
