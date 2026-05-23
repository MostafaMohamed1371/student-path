# Pusher live chat setup

> **Full documentation:** [`docs/CHAT.md`](CHAT.md) — architecture, all endpoints, payloads, roles, Postman, and mobile flow.

## 1. Environment

Add to your `.env` (from [Pusher dashboard](https://dashboard.pusher.com/)):

```env
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_key
PUSHER_APP_SECRET=your_secret
PUSHER_APP_CLUSTER=eu
PUSHER_SCHEME=https
```

Run migrations:

```bash
php artisan migrate
```

## 2. API (Bearer Sanctum)

### Postman contract (`postman/User-Chat.postman_collection.json`)

Base path: **`/api/user/chats`** — matches the MyAppBackend collection.

| Method | Path | Implemented |
|--------|------|-------------|
| GET | `/api/user/chats?search=&per_page=20` | Yes |
| POST | `/api/user/chats/start` | Yes (`participant_id` = admin user, `post_id` optional) |
| GET | `/api/user/chats/{chat_id}/messages?per_page=30` | Yes |
| POST | `/api/user/chats/{chat_id}/read` | Yes (`data.updated_count`) |
| POST | `/api/user/chats/{chat_id}/messages` | Yes (text / offer / file) |
| PUT | `/api/user/chats/{chat_id}/messages/{message_id}` | Yes |
| DELETE | `/api/user/chats/{chat_id}/messages/{message_id}` | Yes |
| POST | `.../offer/accept` | Yes |
| POST | `.../offer/reject` | Yes |
| POST | `.../offer/counter` | Yes |
| GET | `/api/user/chats/{chat_id}/offers/{message_id}/thread` | Yes |
| POST | `/api/user/chats/{chat_id}/typing` | Yes |
| GET | `/api/user/chats/unread-count` | Yes (`data.unread_count`, excludes muted chats) |
| PUT | `/api/user/chats/{chat_id}/preferences` | Yes (`is_pinned`, `is_muted`) |
| POST | `/api/user/chats/{chat_id}/pin` | Yes |
| POST | `/api/user/chats/{chat_id}/unpin` | Yes |
| POST | `/api/user/chats/{chat_id}/unread` | Yes (`data.updated_count`) |
| POST | `/api/user/chats/{chat_id}/block-user` | Yes |
| POST | `/api/user/chats/{chat_id}/unblock-user` | Yes |
| DELETE | `/api/user/chats/{chat_id}` | Yes (soft-delete) |
| POST | `/api/user/chats/{chat_id}/report` | Yes (`reason`, optional `details`) |

In-app notifications: created on send → `GET /api/in-app-notifications` (`data.type` = `CHAT_MESSAGE`). See `docs/CHAT.md`.

Response shape: `{ message, data, pagination? }` with `MessageResource` / `ConversationResource` (includes `message_type`, `offer`, `attachment`, `is_pinned`, `is_muted`, `is_blocked`, etc.).

Same pin/read/unread/block endpoints exist under `/api/chat/conversations/{id}/...` for the legacy parent transport API.

### Legacy parent transport routes (still available)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/chat/config` | Pusher key, cluster, auth URL, channel template |
| GET | `/api/chat/conversations` | List conversations (user: own; admin: all) |
| POST | `/api/chat/conversations` | Start or resume open support chat |
| GET | `/api/chat/conversations/{id}` | Conversation details |
| GET | `/api/chat/conversations/{id}/messages` | Message history |
| POST | `/api/chat/conversations/{id}/messages` | Send text message (broadcasts via Pusher) |
| POST | `/api/chat/conversations/{id}/read` | Mark messages as read |

Channel authorization: `POST /broadcasting/auth` with the same Bearer token.

Private channel name: `private-chat.{conversationId}`  
Event name: `message.sent` (listen with `.listen('.message.sent', ...)` in Echo).

## 3. Mobile / Echo example

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const echo = new Echo({
  broadcaster: 'pusher',
  key: PUSHER_KEY,
  cluster: 'eu',
  forceTLS: true,
  authEndpoint: 'https://your-api.example.com/broadcasting/auth',
  auth: { headers: { Authorization: `Bearer ${token}` } },
});

echo.private(`chat.${conversationId}`)
  .listen('.message.sent', (e) => console.log(e.message));
```

Trip tracking uses a separate channel: `private-trip.{tripHistoryId}` (see `GET /api/trip-tracking/config`).

## 4. Dashboard (support staff)

Admins (`is_admin`) can manage chats in the web dashboard:

- **List:** `/dashboard/support-chat`
- **Reply:** open a conversation — realtime via Pusher (session auth on `/broadcasting/auth` with CSRF cookie)
- **Close / reopen** conversations from the chat page

Sidebar link: **Live support chat** (admin only).
