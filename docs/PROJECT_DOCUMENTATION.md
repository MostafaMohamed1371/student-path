# Student Path - Project Documentation

## 1) Project Overview

Student Path is a Laravel 13 backend + admin dashboard for school transportation operations.

Current scope includes:
- OTP mobile login (phone-based)
- Sanctum-protected APIs
- School management
- Driver management
- Bus management (driver-based)
- User profile management
- Admin web dashboard modules for all entities

The platform uses Iraqi phone normalization rules and supports Arabic/English dashboard UI.

## 2) Architecture Summary

Main layers:
- **Controllers**: API + web request handling
- **Form Requests**: validation rules and payload mapping
- **Services**: OTP, phone normalization, SMS gateway
- **Models**: `User`, `OtpCode`, `School`, `Driver`, `Bus`
- **Resources**: API response DTO shaping
- **Views**: Blade-based admin dashboard

## 3) Authentication Flow

### OTP Login (Mobile)
1. Client calls `POST /api/auth/send-otp` with a 10-digit national phone.
2. Server normalizes phone to `964` + national digits.
3. OTP is generated and sent via configured SMS sender.
4. Client verifies with `POST /api/auth/verify-otp`.
5. Server issues Sanctum token.

### Driver linkage behavior
- User accounts can be linked to drivers (`drivers.user_id`).
- API profile/auth resources prefer driver identity fields when linked.

### Auth protection
- API routes use `auth:sanctum`.
- Dashboard uses web session auth (phone + password).

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

### School APIs
- `GET /api/schools` (auth)
- `POST /api/schools` (auth)
- `GET /api/schools/{school}` (auth)
- `PUT /api/schools/{school}` (auth)
- `DELETE /api/schools/{school}` (auth)

### Driver APIs
- `GET /api/drivers` (auth)
- `POST /api/drivers` (auth)
- `GET /api/drivers/{driver}` (auth)
- `PUT /api/drivers/{driver}` (auth)
- `DELETE /api/drivers/{driver}` (auth)

## 6) API Response Style

Current API style for newer endpoints:
- `success`
- `data`
- `msg` (or `message` in some auth responses)

For exact request bodies and usage:
- `postman/OTP-Auth.postman_collection.json`

## 7) Data Model

### User
Core fields:
- `id`, `name`, `phone`, `password`, `is_active`
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
- Schools (`/dashboard/schools`)
- Drivers (`/dashboard/drivers`)
- Users (`/dashboard/users`)
- Buses (`/dashboard/buses`)
- Admin profile (`/dashboard/profile`)

Current behavior:
- School-first / driver-first workflows enforced where needed.
- Driver and bus assignment aligned with latest structure.
- Overview cards summarize schools/drivers/users/buses/OTP metrics.

## 10) SMS Integration (Standing Tech)

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

## 11) Setup and Run

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

## 12) Testing

```bash
php artisan test
```

Coverage includes OTP and major API/dashboard flows.

## 13) Operational Notes

- OTP cleanup command:
  - `php artisan otp:prune-expired`
- Keep `STANDINGTECH_MOCK=true` in non-production testing environments.
- Ensure `storage:link` exists for public file access.
