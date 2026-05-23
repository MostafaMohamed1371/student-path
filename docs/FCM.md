# Firebase Cloud Messaging (FCM)

Push notifications for the parent/driver mobile apps. The backend stores device tokens, sends FCM when an **in-app notification** row is created, and exposes APIs to register tokens after login.

## Step 1 — Firebase project

1. Open [Firebase Console](https://console.firebase.google.com/) and create or select a project.
2. Add your **Android** and/or **iOS** app(s) to the project (same project as mobile `google-services.json` / `GoogleService-Info.plist`).
3. Enable **Cloud Messaging** (enabled by default on new projects).

## Step 2 — Service account (server)

1. Firebase Console → **Project settings** → **Service accounts**.
2. Click **Generate new private key** and download the JSON file.
3. On the server, place it outside version control, for example:

   ```text
   storage/app/firebase/service-account.json
   ```

4. In `.env`:

   ```env
   FIREBASE_CREDENTIALS=storage/app/firebase/service-account.json
   FCM_ENABLED=true
   FCM_MOCK=false
   ```

   Optional (already used for trip realtime hints): `FIREBASE_PROJECT_ID`, `FIREBASE_DATABASE_URL` in `config/realtime.php`.

Local development without Firebase: leave `FCM_ENABLED=false` (default). Pushes are logged only via `FakePushNotifier`.

## Step 3 — Database migration

```bash
php artisan migrate
```

Creates `user_fcm_tokens` (`user_id`, `token`, `platform`, `device_id`, `last_used_at`).

## Step 4 — Mobile app: obtain FCM token

- **Android:** Firebase SDK → `FirebaseMessaging.getInstance().token`.
- **iOS:** APNs + Firebase → `Messaging.messaging().fcmToken`.

Send the token to the API after the user logs in (Bearer Sanctum token required).

## Step 5 — Register token (API)

**POST** `/api/notifications/fcm-token` (StudentWay contract)

**POST** `/api/user/fcm-token` (alias)

Headers: `Authorization: Bearer <sanctum_token>`

Body (JSON):

```json
{
  "token": "<fcm_registration_token>",
  "platform": "android",
  "device_id": "optional-stable-device-id"
}
```

`platform`: `ios` | `android` | `web` (optional).

**DELETE** `/api/user/fcm-token`

Body:

```json
{
  "token": "<fcm_registration_token>"
}
```

Call DELETE on logout if you want to stop pushes to that device.

## Step 6 — When pushes are sent

Whenever the backend creates a row in `in_app_notifications`, `InAppNotificationObserver` sends an FCM message to all tokens for that user (if `FCM_ENABLED=true`).

Examples that already create in-app notifications:

- Chat messages (`CHAT_MESSAGE` in `data.type`)
- Trip delay alerts (`DELAY_ALERT`)
- SOS (`SOS_TRIGGERED`)
- Trip started (`TRIP_STARTED` / `RETURN_TRIP_STARTED`) — `POST /api/trips/{id}/start`
- Trip ended (`TRIP_COMPLETED` / `RETURN_TRIP_COMPLETED`) — `PUT /api/trips/end-trip` or finalize
- Bus arrived at student (`TRIP_STUDENT_ARRIVED`) — `PUT /api/update-status` with `new_status: ARRIVED`

Payload:

- **notification:** `title`, `body` (system tray)
- **data:** string key/value pairs from `in_app_notifications.data` (use `data.type` for routing in the app)

Invalid or expired tokens are removed automatically after a failed send.

## Trip tracking topics (FCM)

Realtime location uses Firebase topics named `trip_{tripHistoryId}` (see `GET /api/trip-tracking/config` → `topic_template`).

After registering an FCM device token, subscribe the server to the topic (validates the user may track that trip):

**POST** `/api/trip-tracking/topics/subscribe`

```json
{
  "trip_id": "TRP-42",
  "token": "<optional — defaults to all tokens registered for the user>"
}
```

**DELETE** `/api/trip-tracking/topics/unsubscribe` — same body.

**GET** `/api/trip-tracking/topics` — list active subscriptions for the current user.

Authorized users: guardian of a student on the trip, assigned driver, school staff/admin for that school.

The mobile app may also subscribe client-side to the same topic name; server subscription uses your stored tokens via the Firebase Admin API.

## Per-user notification preferences

**GET** `/api/user/settings/notifications` — returns merged toggles (defaults + saved).

**PUT** `/api/user/settings/notifications` — partial update, legacy `{ success, data, msg }` format.

Groups:

| Group | Keys | Used for push when `data.type` is |
|-------|------|-----------------------------------|
| `tripNotifications` | `busMovement`, `busArrival`, `returnTrip`, `driverDelay`, `sos` | `TRIP_STARTED` / `TRIP_COMPLETED` → `busMovement`; `RETURN_TRIP_*` → `returnTrip`; `TRIP_STUDENT_ARRIVED` → `busArrival`; `DELAY_ALERT` → `driverDelay`; `SOS_TRIGGERED` → `sos` |
| `chatNotifications` | `messages` | `CHAT_MESSAGE` |
| `paymentNotifications` | `installmentReminder`, `paymentConfirmation` | `WALLET_PAYMENT` |
| `systemNotifications` | `appUpdates` | (reserved) |

Example:

```json
{
  "chatNotifications": { "messages": false },
  "tripNotifications": { "driverDelay": true, "sos": true }
}
```

When a preference is `false`, the in-app notification row is still created; only the **FCM push** is skipped.

## Step 7 — Verify

1. Register a token with Postman (`postman/FCM.postman_collection.json` or `postman/V1-Parent-Transport.postman_collection.json`) or curl.
2. Trigger an in-app notification (e.g. send a chat message to a user with a registered token).
3. With `FCM_MOCK=true`, check `storage/logs/laravel.log` for `FCM push (fake)`.
4. With `FCM_ENABLED=true` and real credentials, confirm delivery on the device.

## Configuration reference

| Variable | Default | Description |
|----------|---------|-------------|
| `FCM_ENABLED` | `false` | Master switch for sending pushes |
| `FCM_MOCK` | `true` when disabled | Log only, no FCM HTTP |
| `FIREBASE_CREDENTIALS` | — | Path to service account JSON |

Package: `kreait/laravel-firebase` (`config/firebase.php`).
