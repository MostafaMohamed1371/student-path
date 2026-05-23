# Notifications Module — Backend API Contract

## Overview

This document defines the backend API contract for the Notifications module used in the StudentWay application.

The module currently supports:

- Fetching notifications
- Marking a notification as read
- Marking all notifications as read
- Registering Firebase Cloud Messaging (FCM) tokens

**Base URL:** all routes below are under `/api` and require `Authorization: Bearer <sanctum_token>`.

---

# Base Response Structure

All API responses follow this structure:

```json
{
  "success": true,
  "message": "Success",
  "data": {}
}
```

Legacy responses also include `"msg"` (same text as `message`) for older mobile builds.

---

# Notification Object

```json
{
  "id": "string",
  "title": "string",
  "body": "string",
  "type": "TRIP",
  "isRead": false,
  "createdAt": "2026-05-23T16:30:00Z"
}
```

---

# Notification Types

```text
TRIP
ALERT
SCHEDULE
WARNING
LOCATION
```

Internal event types (e.g. `TRIP_STARTED`, `DELAY_ALERT`, `CHAT_MESSAGE`) are mapped to these five categories in `config/notifications.php`.

---

# Endpoints

## Get Notifications

```http
GET /api/notifications
```

- Newest first, no pagination (max `NOTIFICATIONS_CONTRACT_MAX_LIST`, default 500).
- Legacy shape (`time_ago`, `is_read`): `GET /api/notifications?legacy=1`

### Response

```json
{
  "success": true,
  "message": "Notifications fetched successfully",
  "data": [
    {
      "id": "1",
      "title": "تم بدء الرحلة الصباحية",
      "body": "بدأت الرحلة الصباحية في الموعد المحدد",
      "type": "TRIP",
      "isRead": false,
      "createdAt": "2026-05-23T16:30:00Z"
    }
  ]
}
```

**Also available:** `GET /api/haveNewMessages` → `{ hasNewMessages, unreadCount }`

---

## Mark Notification As Read

```http
PATCH /api/notifications/{id}/read
```

### Response

```json
{
  "success": true,
  "message": "Notification marked as read",
  "data": null
}
```

---

## Mark All Notifications As Read

```http
PATCH /api/notifications/read-all
```

### Response

```json
{
  "success": true,
  "message": "All notifications marked as read",
  "data": null
}
```

---

## Register FCM Token

```http
POST /api/notifications/fcm-token
```

### Request Body

```json
{
  "token": "fcm_device_token"
}
```

Optional: `platform` (`ios` | `android` | `web`), `device_id`.

**Alias (same behaviour):** `POST /api/user/fcm-token`

### Response

```json
{
  "success": true,
  "message": "FCM token registered successfully",
  "data": null
}
```

---

# FCM Payload Contract

When a row is created in `in_app_notifications`, the server sends FCM with:

- **notification:** tray `title` / `body`
- **data:** string fields including contract keys:

```json
{
  "notificationId": "123",
  "type": "TRIP",
  "title": "تم بدء الرحلة",
  "body": "بدأت الرحلة الصباحية",
  "type": "TRIP_STARTED",
  "trip_id": "TRP-42"
}
```

(`type` in data is the contract category; internal `data.type` from storage may also be present under other keys.)

Requires `FCM_ENABLED=true` and a valid Firebase service account. See `docs/FCM.md`.

---

# Notes (implementation)

| Note | How the backend handles it |
|------|------------------------------|
| No pagination | `data` is a **JSON array**, not `{ items, pagination }`. Query params `page`, `per_page`, `limit`, `cursor` → **422**. Optional safety cap: `NOTIFICATIONS_CONTRACT_MAX_LIST` (default 500, `0` = no cap). |
| No filtering | Query params `unread_only`, `type`, `notification_type`, `filter`, `search`, `q` → **422**. Use `GET /api/in-app-notifications` for filters. |
| No deep links / click actions | List items expose only `id`, `title`, `body`, `type`, `isRead`, `createdAt`. Internal `data` (trip ids, chat ids, etc.) is **not** returned on this endpoint. |
| Read state on backend | `in_app_notifications.read_at`; `PATCH …/read` and `PATCH …/read-all` update it; `isRead` is derived on read. |
| Newest → oldest | `ORDER BY id DESC`. |
| `createdAt` ISO-8601 UTC | `Y-m-d\TH:i:s\Z` (e.g. `2026-05-23T16:30:00Z`). |

---

# Extended APIs (not in mobile contract)

| Feature | Endpoint |
|--------|----------|
| Paginated list + `data` blob | `GET /api/in-app-notifications` |
| Mark read by ids | `POST /api/in-app-notifications/read` |
| Delete | `DELETE /api/in-app-notifications/{id}` |
| User preferences | `GET/PUT /api/user/settings/notifications` |
| Trip FCM topics | `POST /api/trip-tracking/topics/subscribe` |

See `docs/FCM.md`.

**Postman:** import `postman/StudentWay-Notifications-Contract.postman_collection.json` (this contract only). For FCM topics, preferences, and triggers: `postman/FCM.postman_collection.json`.

---

# Dashboard (staff)

Staff use the web dashboard (session auth), not the mobile contract JSON.

| Action | Dashboard |
|--------|-----------|
| View in-app notifications | `/dashboard/in-app-notifications` |
| Mark one / all read (scoped) | POST buttons on in-app list (same `read_at` as mobile API) |
| View FCM tokens | `/dashboard/fcm-tokens` |
| Remove FCM token | DELETE on FCM tokens page |

School scoping matches other dashboard reports (non-admins only see their school’s users).
