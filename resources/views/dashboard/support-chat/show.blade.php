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
            flex-direction: column;
            gap: 10px;
            margin-top: 12px;
        }

        .chat-compose-row {
            display: flex;
            gap: 10px;
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

        .chat-typing-indicator {
            margin: 0 0 8px;
            font-size: 13px;
            color: var(--text-muted);
            font-style: italic;
            min-height: 18px;
        }

        .chat-realtime-off,
        .chat-blocked-notice {
            margin: 0 0 12px;
            padding: 10px 12px;
            border-radius: 10px;
            font-size: 13px;
        }

        .chat-realtime-off {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
        }

        .chat-blocked-notice {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .chat-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
    </style>

    @php($title = __('dashboard.support_chat_with', ['name' => $conversation->user?->name ?? '#'.$conversation->id]))
    @php($canCompose = $conversation->status === 'open' && ! ($isBlocked ?? false))
    @component('dashboard.partials.shell', ['title' => $title])
        <div class="chat-layout">
            <div class="chat-meta">
                <div>
                    <h2>{{ $conversation->user?->name ?? '—' }}</h2>
                    <p class="mono">{{ $conversation->user?->phone ?? '—' }}
                        · {{ __('dashboard.support_chat_conversation_id') }} #{{ $conversation->id }}</p>
                </div>
                <div class="chat-actions">
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

                    @if ($isBlocked ?? false)
                        <form method="post" action="{{ route('dashboard.support_chat.unblock', $conversation) }}">
                            @csrf
                            <button type="submit" class="btn-muted">{{ __('dashboard.support_chat_unblock_user') }}</button>
                        </form>
                    @else
                        <form method="post" action="{{ route('dashboard.support_chat.block', $conversation) }}">
                            @csrf
                            <button type="submit" class="btn-muted">{{ __('dashboard.support_chat_block_user') }}</button>
                        </form>
                    @endif

                    <form method="post" action="{{ route('dashboard.support_chat.destroy', $conversation) }}" onsubmit="return confirm(@json(__('dashboard.support_chat_delete_confirm')))">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-muted">{{ __('dashboard.support_chat_delete') }}</button>
                    </form>

                    <a href="{{ route('dashboard.chat_reports.index', ['status' => 'pending']) }}" class="link">{{ __('dashboard.support_chat_view_reports') }}</a>
                    <a href="{{ route('dashboard.support_chat.index') }}" class="link">{{ __('dashboard.support_chat_back') }}</a>
                </div>
            </div>

            @unless ($chatEnabled)
                <p class="chat-realtime-off">{{ __('dashboard.support_chat_pusher_disabled') }}</p>
            @endunless

            @if ($isBlocked ?? false)
                <p class="chat-blocked-notice">{{ __('dashboard.support_chat_blocked_notice') }}</p>
            @endif

            <section class="card chat-panel">
                <div id="chat-messages" class="chat-messages" aria-live="polite">
                    @foreach ($messages as $message)
                        @include('dashboard.support-chat._message', ['message' => $message])
                    @endforeach
                </div>

                <form id="chat-compose-form" class="chat-compose" enctype="multipart/form-data" @if (! $canCompose) style="opacity:0.6" @endif>
                    <label style="display:flex;flex-direction:column;gap:6px;">
                        <span class="field-label" style="margin:0;">{{ __('dashboard.support_chat_reply_label') }}</span>
                        <div class="chat-compose-row">
                            <textarea
                                id="chat-body"
                                name="body"
                                maxlength="{{ (int) config('chat.max_message_length', 5000) }}"
                                placeholder="{{ __('dashboard.support_chat_reply_placeholder') }}"
                                @disabled(! $canCompose)
                            ></textarea>
                            <button type="submit" class="btn-primary" style="width:auto;padding:12px 18px;white-space:nowrap;" @disabled(! $canCompose)>
                                {{ __('dashboard.support_chat_send') }}
                            </button>
                        </div>
                    </label>
                    <label style="display:flex;flex-direction:column;gap:6px;">
                        <span class="field-label" style="margin:0;">{{ __('dashboard.support_chat_attachment_label') }}</span>
                        <input type="file" id="chat-attachment" name="attachment" accept="image/*,.pdf,.doc,.docx,.xlsx,.txt,.zip" @disabled(! $canCompose)>
                    </label>
                </form>
                <p id="chat-typing-indicator" class="chat-typing-indicator" aria-live="polite"></p>
                <p id="chat-status" class="chat-status-pill" style="margin:8px 0 0;"></p>
            </section>
        </div>
    @endcomponent

    <script>
        (function () {
            const conversationId = @json($conversation->id);
            const messagesEl = document.getElementById('chat-messages');
            const form = document.getElementById('chat-compose-form');
            const bodyInput = document.getElementById('chat-body');
            const attachmentInput = document.getElementById('chat-attachment');
            const statusEl = document.getElementById('chat-status');
            const typingEl = document.getElementById('chat-typing-indicator');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const messagesUrl = @json(route('dashboard.support_chat.messages', $conversation));
            const sendUrl = @json(route('dashboard.support_chat.messages.store', $conversation));
            const typingUrl = @json(route('dashboard.support_chat.typing', $conversation));
            const staffUserId = @json($staffUserId ?? 0);
            const typingLabelTemplate = @json(__('dashboard.support_chat_user_typing', ['name' => '__NAME__']));
            const canCompose = @json($canCompose);
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

            function attachmentHtml(payload) {
                const attachment = payload?.attachment || payload?.meta?.attachment;
                if (!attachment?.url) {
                    return '';
                }
                const mime = String(attachment.mime || '');
                const url = escapeHtml(attachment.url);
                const name = escapeHtml(attachment.name || @json(__('dashboard.support_chat_download_attachment')));
                if (mime.startsWith('image/')) {
                    return '<a href="' + url + '" target="_blank" rel="noopener"><img src="' + url + '" alt="" style="display:block;max-width:min(100%,220px);margin-top:8px;border-radius:8px;"></a>';
                }
                return '<a href="' + url + '" target="_blank" rel="noopener" style="display:inline-block;margin-top:8px;font-size:13px;">' + name + '</a>';
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
                const bodyPart = payload.is_deleted
                    ? '<div style="font-style:italic;opacity:0.8;">' + escapeHtml(@json(__('dashboard.support_chat_message_deleted'))) + '</div>'
                    : '<div>' + escapeHtml(payload.body || '') + '</div>' + attachmentHtml(payload);
                bubble.innerHTML =
                    bodyPart +
                    '<span class="chat-bubble-meta">' +
                    escapeHtml(payload.sender?.name || '') + (when ? ' · ' + escapeHtml(when) : '') +
                    '</span>';
                messagesEl.appendChild(bubble);
                messagesEl.scrollTop = messagesEl.scrollHeight;
            }

            async function sendMessage() {
                const body = bodyInput.value.trim();
                const file = attachmentInput?.files?.[0];
                if (!body && !file) {
                    return;
                }
                await sendTypingStatus(false);
                statusEl.textContent = @json(__('dashboard.support_chat_sending'));
                const formData = new FormData();
                if (body) {
                    formData.append('body', body);
                }
                if (file) {
                    formData.append('attachment', file);
                }
                try {
                    const res = await fetch(sendUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: formData,
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        const err = data?.message || data?.errors?.body?.[0] || data?.errors?.conversation?.[0] || @json(__('dashboard.support_chat_send_failed'));
                        throw new Error(err);
                    }
                    appendMessage(data.message);
                    bodyInput.value = '';
                    if (attachmentInput) {
                        attachmentInput.value = '';
                    }
                    statusEl.textContent = '';
                } catch (err) {
                    statusEl.textContent = err?.message || @json(__('dashboard.support_chat_send_failed'));
                }
            }

            form?.addEventListener('submit', async (e) => {
                e.preventDefault();
                await sendMessage();
            });

            let typingIdleTimer = null;
            let typingActive = false;

            function setRemoteTypingIndicator(userName, isTyping) {
                if (!typingEl) {
                    return;
                }
                typingEl.textContent = isTyping
                    ? typingLabelTemplate.replace('__NAME__', userName || '')
                    : '';
            }

            async function sendTypingStatus(isTyping) {
                if (!canCompose || !typingUrl) {
                    return;
                }
                if (typingActive === isTyping) {
                    return;
                }
                typingActive = isTyping;
                try {
                    await fetch(typingUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ is_typing: isTyping }),
                    });
                } catch (_) {}
            }

            bodyInput?.addEventListener('input', () => {
                if (!canCompose) {
                    return;
                }
                const hasText = bodyInput.value.trim().length > 0;
                if (hasText) {
                    void sendTypingStatus(true);
                    clearTimeout(typingIdleTimer);
                    typingIdleTimer = setTimeout(() => {
                        void sendTypingStatus(false);
                    }, 1500);
                } else {
                    clearTimeout(typingIdleTimer);
                    void sendTypingStatus(false);
                }
            });

            bodyInput?.addEventListener('blur', () => {
                clearTimeout(typingIdleTimer);
                void sendTypingStatus(false);
            });

            @if ($chatEnabled)
            const eventName = @json($eventName);
            const typingEventName = @json($typingEventName ?? 'typing.updated');
            const pusherScript = document.createElement('script');
            pusherScript.src = 'https://js.pusher.com/8.4.0-rc2/pusher.min.js';
            pusherScript.onload = function () {
                const echoScript = document.createElement('script');
                echoScript.src = 'https://cdn.jsdelivr.net/npm/laravel-echo@1.19.0/dist/echo.iife.js';
                echoScript.onload = function () {
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
                        })
                        .listen('.' + typingEventName, (event) => {
                            const userId = Number(event?.user?.id || 0);
                            if (userId > 0 && userId === staffUserId) {
                                return;
                            }
                            setRemoteTypingIndicator(event?.user?.name || '', Boolean(event?.is_typing));
                        });
                };
                document.body.appendChild(echoScript);
            };
            document.body.appendChild(pusherScript);
            @endif

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
@endsection
