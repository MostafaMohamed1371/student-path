<?php

namespace App\Http\Resources\Chat;

use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ChatMessage */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isOffer = $this->message_type === 'offer';
        $meta = is_array($this->meta) ? $this->meta : [];
        $attachment = $meta['attachment'] ?? null;
        $isDeleted = (bool) ($meta['is_deleted'] ?? false);

        return [
            'id' => $this->id,
            'conversation_id' => $this->chat_conversation_id,
            'sender' => [
                'id' => $this->sender?->id,
                'name' => $this->sender?->name ?? '',
                'type' => $this->sender?->is_admin ? 'staff' : 'user',
                'is_staff' => (bool) ($this->sender?->is_admin ?? false),
                'image' => $this->sender?->image,
            ],
            'body' => $isDeleted ? null : $this->body,
            'message_type' => $this->message_type,
            'meta' => $meta,
            'attachment' => $attachment,
            'is_deleted' => $isDeleted,
            'is_edited' => (bool) ($meta['is_edited'] ?? false),
            'edited_at' => $meta['edited_at'] ?? null,
            'offer' => $isOffer ? [
                'amount' => (float) ($meta['amount'] ?? 0),
                'currency' => $meta['currency'] ?? (string) config('chat.default_currency', 'IQD'),
                'title' => $meta['title'] ?? 'Offer',
                'details' => $meta['details'] ?? null,
                'valid_until' => $meta['valid_until'] ?? null,
                'status' => $meta['status'] ?? 'pending',
            ] : null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
