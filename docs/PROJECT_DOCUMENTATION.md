# Student Path - Project Documentation

## 1) Project Overview

Student Path is a Laravel 13 backend that serves:
- Mobile app authentication using OTP
- Token-protected APIs for user profile and bus data
- Admin dashboard for managing all users and all buses

The platform uses Iraqi phone normalization rules and supports Arabic/English on the dashboard.

## 2) Architecture Summary

Main layers:
- **Controllers**: API and web request handling
- **Form Requests**: validation and input preparation
- **Services**: OTP, phone normalization, SMS delivery
- **Models**: `User`, `OtpCode`, `Bus`
- **Resources**: API response shaping for profile and bus
- **Views**: Blade-based admin dashboard UI

## 3) Authentication Flow

### OTP Login (Mobile)
1. Client calls `POST /api/auth/send-otp` with phone (10 digits).
2. Server normalizes phone to `964XXXXXXXXXX`.
3. Server creates OTP record and sends OTP via configured SMS sender.
4. Client verifies code using `POST /api/auth/verify-otp`.
5. On success, server issues Sanctum token.

### Auth Protection
- Protected API routes use `auth:sanctum`.
- Dashboard uses session auth (phone + password).

## 4) Phone Number Rules

- Input format: exactly 10 digits
- Must not start with `0`
- Canonical stored format: `964` + 10 digits

Implemented in:
- `app/Services/Phone/PhoneNormalizer.php`
- request traits under `app/Http/Requests/Concerns`

## 5) API Endpoints

## Auth APIs
- `POST /api/auth/send-otp`
- `POST /api/auth/verify-otp`
- `POST /api/auth/logout` (auth)
- `GET /api/auth/me` (auth)

## User APIs
- `GET /api/user/profile` (auth)
- `PUT /api/user/profile` (auth)
- `DELETE /api/user/profile` (auth)
- `POST /api/user/language` (auth)

## Bus APIs
- `GET /api/user/bus` (auth alias)
- `GET /api/bus/my-bus` (auth)
- `POST /api/bus/my-bus` (auth)
- `PUT /api/bus/my-bus` (auth)
- `DELETE /api/bus/my-bus` (auth)

Route source:
- `routes/api.php`

## 6) API Response Style

Project currently uses a consistent success/error structure across newer endpoints, centered on:
- `success`
- `data`
- `msg` or message text

For strict contract behavior, verify specific endpoint responses against Postman collection:
- `postman/OTP-Auth.postman_collection.json`

## 7) Data Model

## User
Core fields:
- `name`
- `image`
- `phone`
- `password` (nullable for mobile-only accounts)
- `city`
- `licence_number`
- `votes`
- `rate`
- `is_verified`
- `preferred_language`
- `is_active`

## OtpCode
- `phone`
- `code` (plain text by current requirement)
- `purpose`
- `expires_at`
- `resend_available_at`
- `attempts`
- `max_attempts`
- `verified_at`

## Bus
- `user_id` (one bus per user currently)
- `name`
- `type`
- `city`
- `number`
- `color`
- `capacity`
- `fuel_type`
- `status`
- `annual_status`
- `insurance`

Migration source:
- `database/migrations/*`

## 8) Admin Dashboard

## Modules
- Overview (`/dashboard`)
- Users CRUD (`/dashboard/users`)
- Buses CRUD (`/dashboard/buses`)
- Admin profile (`/dashboard/profile`)

## Dashboard Features
- User create/update/delete with extended profile fields
- Image upload support for users
- Bus create/update/delete for all app buses
- Bus color UI mode toggle (`Pick color` vs `Type color`)
- Locale switch (`en` / `ar`)

Route source:
- `routes/web.php`

View source:
- `resources/views/dashboard/*`

## 9) SMS Integration (Standing Tech)

Configured with `.env` keys:
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

## 10) Setup and Run

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve
```

Default admin credentials (if unchanged):
- Phone: `7701234567`
- Password: `12345678`

## 11) Testing

Run:

```bash
php artisan test
```

Coverage includes OTP and major auth/profile/bus flows.

## 12) Operational Notes

- OTP prune command exists for old/verified codes:
  - `php artisan otp:prune-expired`
- Keep `STANDINGTECH_MOCK=true` in non-production testing environments.
- Ensure filesystem public link exists for profile image URLs.
