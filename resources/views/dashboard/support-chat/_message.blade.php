@php
    $isStaff = (bool) ($message->sender?->isChatStaff() ?? false);
    $meta = is_array($message->meta) ? $message->meta : [];
    $attachment = $meta['attachment'] ?? null;
    $isDeleted = (bool) ($meta['is_deleted'] ?? false);
@endphp
<div class="chat-bubble {{ $isStaff ? 'is-staff' : 'is-user' }}" data-message-id="{{ $message->id }}">
    @if ($isDeleted)
        <div style="font-style:italic;opacity:0.8;">{{ __('dashboard.support_chat_message_deleted') }}</div>
    @else
        @if ($message->body)
            <div>{{ $message->body }}</div>
        @endif
        @if (is_array($attachment) && ! empty($attachment['url']))
            @php($mime = (string) ($attachment['mime'] ?? ''))
            @if (str_starts_with($mime, 'image/'))
                <a href="{{ $attachment['url'] }}" target="_blank" rel="noopener">
                    <img src="{{ $attachment['url'] }}" alt="" style="display:block;max-width:min(100%,220px);margin-top:8px;border-radius:8px;">
                </a>
            @else
                <a href="{{ $attachment['url'] }}" target="_blank" rel="noopener" style="display:inline-block;margin-top:8px;font-size:13px;">
                    {{ $attachment['name'] ?? __('dashboard.support_chat_download_attachment') }}
                </a>
            @endif
        @endif
    @endif
    <span class="chat-bubble-meta">
        {{ $message->sender?->name ?? '—' }}
        @if ($message->created_at)
            · {{ $message->created_at->format('Y-m-d H:i') }}
        @endif
    </span>
</div>
