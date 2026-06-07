# Absences & parent schedule — Postman collection guide

This document describes the **Student Path — Absences API** Postman collection:

**File:** [`postman/Absences.postman_collection.json`](../postman/Absences.postman_collection.json)

It covers parent **absence reporting**, the **monthly attendance calendar**, the **daily trip timeline**, **arrival notification setup**, and **driver absence listing**. For the full parent transport API, see [`API_V1_PARENT_TRANSPORT.md`](API_V1_PARENT_TRANSPORT.md).

---

## Import

1. Open Postman → **Import** → select `postman/Absences.postman_collection.json`.
2. Open the collection → **Variables** tab.
3. Set **`base_url`** (e.g. `http://localhost:8000` or your staging URL).

All authenticated requests use:

```http
Authorization: Bearer {{token}}
Accept: application/json
```

Parent API responses use:

```json
{
  "success": true,
  "message": "...",
  "msg": "...",
  "data": { }
}
```

---

## Collection variables

| Variable | Set by | Purpose |
|----------|--------|---------|
| `base_url` | You | API host |
| `token` | verify-otp (guardian) test script | Parent Bearer token |
| `driver_token` | verify-otp (driver) test script | Driver Bearer token |
| `student_id` | GET /api/students test script (or manual) | Child to test |
| `absence_id` | POST /api/absences test script | Last created absence |
| `absence_reason` | Default `travel` | Reason code for POST |
| `absence_start_date` / `absence_end_date` | You | Date range for report/filter |
| `schedule_year` / `schedule_month` | You | Monthly calendar |
| `timeline_date` | You | Daily timeline date |
| `trip_id` | GET /api/trips/active test script | Active trip (`TRP-{id}`) |
| `fcm_token` | You (from Firebase SDK) | Push registration token |

---

## Quick start (parent — report absence)

Run folders **in this order**:

| Step | Folder / request | Result |
|------|------------------|--------|
| 1 | **Auth** → `POST verify-otp (guardian)` | Saves `token` |
| 2 | **Parent — Students** → `GET /api/students` | Saves `student_id` |
| 3 | **Meta** → `GET /api/meta/absence-reasons` | Picker codes for mobile |
| 4 | **Parent — Absences** → `POST /api/absences (report)` | Creates absence, saves `absence_id` |

**Prerequisite:** The student must be on an **active transport route** with an **active driver**. Otherwise step 4 returns **422** on `student_id`.

---

## Folder reference

### Auth

OTP login only — phone must already exist in `users`.

| Request | API | Notes |
|---------|-----|-------|
| send-otp (guardian) | `POST /api/auth/send-otp` | Body: `phone`, `type_user: "guardian"` |
| verify-otp (guardian) | `POST /api/auth/verify-otp` | Auto-saves `token` |
| send-otp (driver) | `POST /api/auth/send-otp` | `type_user: "driver"` |
| verify-otp (driver) | `POST /api/auth/verify-otp` | Auto-saves `driver_token` |

---

### Meta

| Request | API | Purpose |
|---------|-----|---------|
| absence-reasons | `GET /api/meta/absence-reasons` | Reason picker for report form |

**Response example:**

```json
{
  "success": true,
  "data": [
    { "code": "medical", "label_en": "Medical", "label_ar": "صحية" },
    { "code": "travel", "label_en": "Travel", "label_ar": "سفر" },
    { "code": "family", "label_en": "Family circumstances", "label_ar": "ظروف عائلية" },
    { "code": "other", "label_en": "Other", "label_ar": "أخرى" }
  ]
}
```

Use **`code`** in `POST /api/absences` body (`reason` field).

---

### Parent — Students

| Request | API | Purpose |
|---------|-----|---------|
| GET students | `GET /api/students` | Lists guardian’s children; test script sets `student_id` |

---

### Parent — Attendance schedule

Mobile screen: **جدول الحضور و الغياب** (monthly calendar).

| Request | API | Query params |
|---------|-----|--------------|
| attendance-schedule | `GET /api/students/{id}/attendance-schedule` | `year`, `month`, `recent_limit` |
| current month | same | none (defaults to current month) |

**What it returns:**

- **`summary`** — `present_days`, `absence_days`, `late_count` + colors
- **`status_legend`** — present / absent / late with colors and icons
- **`calendar[]`** — one row per day with `status`, `status_color`, `status_icon`
- **`recent_events[]`** — recent absences (with `reason_text`) and delays (`delay_minutes`)

**Calendar colors (mobile badges):**

| Status | Color | Icon |
|--------|-------|------|
| present | `#00796B` | `check` |
| absent | `#D32F2F` | `x` |
| late | `#5D4037` | `clock` |

**Dashboard mirror:** `/dashboard/students/{student}/attendance-schedule`

---

### Parent — Daily timeline

Mobile screen: **الجدول الزمني** (four daily milestones).

| Request | API | Query |
|---------|-----|-------|
| daily-timeline | `GET /api/students/{id}/daily-timeline` | `date` (optional, default today) |
| today | same | none |

**Four milestones (`milestones[]`):**

| Code | Arabic title |
|------|----------------|
| `morning_pickup_home` | موعد الذهاب |
| `morning_arrive_school` | الوصول للمدرسة |
| `evening_pickup_school` | موعد العودة |
| `evening_arrive_home` | الوصول للمنزل |

Each milestone includes:

- `status` — `scheduled`, `boarded`, `completed`, `absent`, `on_way`, `arrived`
- `status_color` / `status_background_color` — badge colors for UI
- `scheduled_time_label_ar` / `actual_time_label_ar` — display times
- `icon` — `home`, `school`, or `bus`

If the parent reported absence for that day, **`is_absent_today: true`** and milestones use red (`#D32F2F`).

**Dashboard mirror:** `/dashboard/students/{student}/daily-timeline`

---

### Parent — Absences

Core absence CRUD for the parent app.

#### Report absence

```http
POST /api/absences
Authorization: Bearer {{token}}
Content-Type: application/json
```

```json
{
  "student_id": 1,
  "start_date": "2026-06-06",
  "end_date": "2026-06-06",
  "reason": "travel",
  "notes": "Family trip — notify driver and school."
}
```

**Server actions on create:**

1. Resolves **subscribed driver** (student → transport route → driver).
2. Saves `driver_id` and `transport_route_id` on the absence row.
3. Sends **in-app notifications** to the driver and school staff.
4. Marks matching **trip roster** students as `ABSENT` for covered dates.

**Success (201) — key `data` fields:**

| Field | Description |
|-------|-------------|
| `id` | Absence id |
| `driver_id` | Assigned driver |
| `transport_route_id` | Route used for resolution |
| `reason` | Enum code |
| `reason_label_en` / `reason_label_ar` | Display labels |
| `driver_notified` / `school_notified` | Boolean flags |
| `driver` | Nested driver summary (when loaded) |

**Errors:**

| Code | When |
|------|------|
| 403 | Student not owned by parent |
| 422 | Student not on active route (`student_id`) |
| 422 | Invalid `reason` or date range |

#### List / filter

```http
GET /api/absences?student_id=1&date=2026-06-06&from=2026-06-01&to=2026-06-30&per_page=20
```

| Query | Purpose |
|-------|---------|
| `student_id` | Filter by child |
| `date` | Absences covering that day |
| `from` / `to` | Range overlap filter |
| `per_page` | Pagination (max 100) |

List response shape:

```json
{
  "data": {
    "items": [ /* AbsenceResource[] */ ],
    "pagination": { "current_page", "per_page", "total", "last_page" }
  }
}
```

#### Show / update / delete

| Method | Path | Who |
|--------|------|-----|
| GET | `/api/absences/{id}` | Parent owner or assigned driver |
| PUT / PATCH | `/api/absences/{id}` | Parent owner only |
| DELETE | `/api/absences/{id}` | Parent owner only |

Update uses the same `reason` enum codes. Update does **not** re-send driver notifications or re-apply trip roster.

---

### Enable arrival alerts

Mobile button: **تفعيل تنبيهات الوصول** (on the daily timeline screen).

Run **requests 1 → 4 in order** after guardian login.

| # | Request | Purpose |
|---|---------|---------|
| 1 | `GET /api/trips/active?student_id=` | Finds active trip → sets `trip_id` |
| 2 | `POST /api/user/fcm-token` | Registers device for push |
| 3 | `PUT /api/user/settings/notifications` | Enables `busArrival` and related toggles |
| 4 | `POST /api/trip-tracking/topics/subscribe` | Subscribes to trip FCM topic |

Before step 2, set collection variable **`fcm_token`** to a real Firebase registration token (from the mobile app).

Step 1 requires an **active trip** for the student. If none exists, set `trip_id` manually (e.g. `TRP-42`).

Push types enabled by step 3 include:

- `TRIP_STUDENT_ARRIVED` → `busArrival`
- `TRIP_STARTED` / `TRIP_COMPLETED` → `busMovement`
- `DELAY_ALERT` → `driverDelay`

See also [`FCM.md`](FCM.md).

---

### Driver — Absences

Login with **verify-otp (driver)** first (`driver_token`).

| Request | API | Purpose |
|---------|-----|---------|
| List all | `GET /api/absences` | Absences where `driver_id` = logged-in driver |
| By date | `GET /api/absences?date=` | Filter by day |
| Show | `GET /api/absences/{id}` | When assigned to this driver |
| Notifications | `GET /api/in-app-notifications?unread_only=1` | Absence alerts (`data.type: ABSENCE`) |

Drivers **cannot** create, update, or delete parent absences via this API.

---

## End-to-end test scenarios

### Scenario A — Parent reports absence → driver sees it

1. Auth → verify-otp (guardian)
2. GET students → note `student_id`
3. POST absences (report)
4. Auth → verify-otp (driver)
5. GET absences (driver)
6. GET in-app-notifications (driver) — expect `ABSENCE` type

### Scenario B — Absence updates calendar and timeline

1. POST absences for today
2. GET attendance-schedule (current month) — day shows `absent` / red
3. GET daily-timeline — `is_absent_today: true`, milestones red

### Scenario C — Enable arrival alerts

1. Auth → verify-otp (guardian)
2. Set `fcm_token`
3. Run **Enable arrival alerts** folder 1 → 4
4. Driver marks student `ARRIVED` on trip → parent receives `TRIP_STUDENT_ARRIVED` push (if FCM enabled on server)

---

## Related docs & collections

| Resource | Path |
|----------|------|
| Full parent API reference | [`docs/API_V1_PARENT_TRANSPORT.md`](API_V1_PARENT_TRANSPORT.md) |
| FCM & notification preferences | [`docs/FCM.md`](FCM.md) |
| Broader parent Postman | [`postman/V1-Parent-Transport.postman_collection.json`](../postman/V1-Parent-Transport.postman_collection.json) |
| FCM-focused Postman | [`postman/FCM.postman_collection.json`](../postman/FCM.postman_collection.json) |
| Dashboard absences CRUD | `/dashboard/absences` |
| Dashboard attendance schedule | `/dashboard/students/{student}/attendance-schedule` |
| Dashboard daily timeline | `/dashboard/students/{student}/daily-timeline` |

---

## Troubleshooting

| Problem | Likely cause | Fix |
|---------|--------------|-----|
| 401 Unauthorized | Missing or expired `token` | Re-run verify-otp |
| 422 on POST absences (`student_id`) | Student not on active route | Assign student to route in dashboard |
| Empty GET absences (driver) | Wrong driver or no reports | Report absence as parent first; verify `driver_id` on absence |
| Step 4 arrival alerts fails | No active trip | Create/start a trip for today or set `trip_id` manually |
| No push notifications | FCM disabled or mock mode | Check `.env`: `FCM_ENABLED=true`; see `FCM.md` |
| Reason validation error | Free text instead of code | Use `medical`, `travel`, `family`, or `other` from meta endpoint |
