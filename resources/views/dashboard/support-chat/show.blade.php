@extends('dashboard.layout')

@section('title', __('dashboard.support_chat_with', ['name' => $conversation->user?->name ?? '#'.$conversation->id]))

@section('content')
    <style>
        .chat-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 16px;
        }

        .chat-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }

        .chat-meta h2 {
            margin: 0;
            font-size: 18px;
        }

        .chat-meta p {
            margin: 4px 0 0;
            color: var(--text-muted);
            font-size: 14px;
        }

        .chat-panel {
            display: flex;
            flex-direction: column;
            min-height: 420px;
            max-height: min(70vh, 640px);
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .chat-bubble {
            max-width: min(85%, 520px);
            padding: 10px 12px;
            border-radius: 12px;
            font-size: 14px;
            line-height: 1.45;
            word-break: break-word;
        }

        .chat-bubble.is-staff {
            align-self: flex-end;
            background: #1a2744;
            color: #fff;
            border-bottom-right-radius: 4px;
        }

        .chat-bubble.is-user {
            align-self: flex-start;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-bottom-left-radius: 4px;
        }

        .chat-bubble-meta {
            display: block;
            margin-top: 6px;
            font-size: 11px;
            opacity: 0.75;
        }

        .chat-compose {
            display: flex;
            gap: 10px;
            margin-top: 12px;
            align-items: flex-end;
        }

        .chat-compose textarea {
            flex: 1;
            min-height: 72px;
            resize: vertical;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 14px;
            font-family: inherit;
        }

        .chat-compose textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 39, 68, 0.12);
        }

        .chat-compose textarea:disabled {
            background: #f1f5f9;
            cursor: not-allowed;
        }

        .chat-status-pill {
            font-size: 12px;
            color: var(--text-muted);
        }

        .chat-realtime-off {
            margin: 0 0 12px;
            padding: 10px 12px;
            border-radius: 10px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
            font-size: 13px;
        }
    </style>

    @php($title = __('dashboard.support_chat_with', ['name' => $conversation->user?->name ?? '#'.$conversation->id]))
    @component('dashboard.partials.shell', ['title' => $title])
        <div class="chat-layout">
            <div class="chat-meta">
                <div>
                    <h2>{{ $conversation->user?->name ?? '—' }}</h2>
                    <p class="mono">{{ $conversation->user?->phone ?? '—' }}
                        · {{ __('dashboard.support_chat_conversation_id') }} #{{ $conversation->id }}</p>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                    @if ($conversation->status === 'open')
                        <span class="badge ok">{{ __('dashboard.support_chat_status_open') }}</span>
                        <form method="post" action="{{ route('dashboard.support_chat.close', $conversation) }}">
                            @csrf
                            <button type="submit" class="btn-muted">{{ __('dashboard.support_chat_close') }}</button>
                        </form>
                    @else
                        <span class="badge off">{{ __('dashboard.support_chat_status_closed') }}</span>
                        <form method="post" action="{{ route('dashboard.support_chat.reopen', $conversation) }}">
                            @csrf
                            <button type="submit" class="btn-muted">{{ __('dashboard.support_chat_reopen') }}</button>
                        </form>
                    @endif
                    <a href="{{ route('dashboard.support_chat.index') }}" class="link">{{ __('dashboard.support_chat_back') }}</a>
                </div>
            </div>

            @unless ($chatEnabled)
                <p class="chat-realtime-off">{{ __('dashboard.support_chat_pusher_disabled') }}</p>
            @endunless

            <section class="card chat-panel">
                <div id="chat-messages" class="chat-messages" aria-live="polite">
                    @foreach ($messages as $message)
                        @include('dashboard.support-chat._message', ['message' => $message])
                    @endforeach
                </div>

                <form id="chat-compose-form" class="chat-compose" @if ($conversation->status !== 'open') style="opacity:0.6" @endif>
                    <label style="flex:1;display:flex;flex-direction:column;gap:6px;">
                        <span class="field-label" style="margin:0;">{{ __('dashboard.support_chat_reply_label') }}</span>
                        <textarea
                            id="chat-body"
                            name="body"
                            maxlength="{{ (int) config('chat.max_message_length', 5000) }}"
                            placeholder="{{ __('dashboard.support_chat_reply_placeholder') }}"
                            @disabled($conversation->status !== 'open')
                            required
                        ></textarea>
                    </label>
                    <button type="submit" class="btn-primary" style="width:auto;padding:12px 18px;" @disabled($conversation->status !== 'open')>
                        {{ __('dashboard.support_chat_send') }}
                    </button>
                </form>
                <p id="chat-status" class="chat-status-pill" style="margin:8px 0 0;"></p>
            </section>
        </div>
    @endcomponent

    @if ($chatEnabled && $conversation->status === 'open')
        <script src="https://js.pusher.com/8.4.0-rc2/pusher.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.19.0/dist/echo.iife.js"></script>
        <script>
            (function () {
                const conversationId = @json($conversation->id);
                const staffUserId = @json(auth()->id());
                const messagesEl = document.getElementById('chat-messages');
                const form = document.getElementById('chat-compose-form');
                const bodyInput = document.getElementById('chat-body');
                const statusEl = document.getElementById('chat-status');
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const eventName = @json($eventName);
                const messagesUrl = @json(route('dashboard.support_chat.messages', $conversation));
                const sendUrl = @json(route('dashboard.support_chat.messages.store', $conversation));
                const renderedIds = new Set(
                    Array.from(messagesEl.querySelectorAll('[data-message-id]')).map((el) => Number(el.dataset.messageId))
                );

                function escapeHtml(value) {
                    return String(value)
                        .replaceAll('&', '&amp;')
                        .replaceAll('<', '&lt;')
                        .replaceAll('>', '&gt;')
                        .replaceAll('"', '&quot;')
                        .replaceAll("'", '&#39;');
                }

                function appendMessage(payload) {
                    if (!payload?.id || renderedIds.has(payload.id)) {
                        return;
                    }
                    renderedIds.add(payload.id);
                    const isStaff = Boolean(payload.sender?.is_staff);
                    const bubble = document.createElement('div');
                    bubble.className = 'chat-bubble ' + (isStaff ? 'is-staff' : 'is-user');
                    bubble.dataset.messageId = String(payload.id);
                    const when = payload.created_at ? new Date(payload.created_at).toLocaleString() : '';
                    bubble.innerHTML =
                        '<div>' + escapeHtml(payload.body || '') + '</div>' +
                        '<span class="chat-bubble-meta">' +
                        escapeHtml(payload.sender?.name || '') + (when ? ' · ' + escapeHtml(when) : '') +
                        '</span>';
                    messagesEl.appendChild(bubble);
                    messagesEl.scrollTop = messagesEl.scrollHeight;
                }

                window.Pusher = Pusher;
                const echo = new Echo({
                    broadcaster: 'pusher',
                    key: @json($pusherKey),
                    cluster: @json($pusherCluster),
                    forceTLS: true,
                    authEndpoint: @json(url('/broadcasting/auth')),
                    auth: {
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                        },
                    },
                });

                echo.private('chat.' + conversationId)
                    .listen('.' + eventName, (event) => {
                        if (event?.message) {
                            appendMessage(event.message);
                        }
                    });

                form?.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const body = bodyInput.value.trim();
                    if (!body) {
                        return;
                    }
                    statusEl.textContent = @json(__('dashboard.support_chat_sending'));
                    try {
                        const res = await fetch(sendUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({ body }),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok) {
                            const err = data?.message || data?.errors?.body?.[0] || @json(__('dashboard.support_chat_send_failed'));
                            throw new Error(err);
                        }
                        appendMessage(data.message);
                        bodyInput.value = '';
                        statusEl.textContent = '';
                    } catch (err) {
                        statusEl.textContent = err?.message || @json(__('dashboard.support_chat_send_failed'));
                    }
                });

                setInterval(async () => {
                    const lastId = Math.max(0, ...Array.from(renderedIds));
                    try {
                        const res = await fetch(messagesUrl + '?after_id=' + lastId, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        if (!res.ok) {
                            return;
                        }
                        const data = await res.json();
                        (data.items || []).forEach(appendMessage);
                    } catch (_) {}
                }, 30000);
            })();
        </script>
    @endif
@endsection
