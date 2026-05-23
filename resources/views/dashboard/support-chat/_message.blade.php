@php
    $isStaff = (bool) ($message->sender?->is_admin ?? false);
@endphp
<div class="chat-bubble {{ $isStaff ? 'is-staff' : 'is-user' }}" data-message-id="{{ $message->id }}">
    <div>{{ $message->body }}</div>
    <span class="chat-bubble-meta">
        {{ $message->sender?->name ?? '—' }}
        @if ($message->created_at)
            · {{ $message->created_at->format('Y-m-d H:i') }}
        @endif
    </span>
</div>
