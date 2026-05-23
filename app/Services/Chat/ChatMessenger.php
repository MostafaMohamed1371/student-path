<?php

namespace App\Services\Chat;

use App\Events\ChatMessageSent;
use App\Http\Resources\Chat\MessageResource;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ChatMessenger
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function send(
        ChatConversation $conversation,
        User $sender,
        string $messageType = 'text',
        ?string $body = null,
        array $meta = [],
        ?UploadedFile $attachment = null,
    ): ChatMessage {
        if ($conversation->status !== 'open') {
            throw ValidationException::withMessages([
                'body' => ['This conversation is closed.'],
            ]);
        }

        $messageType = $messageType !== '' ? $messageType : 'text';
        $meta = $meta ?: [];

        if ($attachment !== null && $messageType === 'offer') {
            throw ValidationException::withMessages([
                'attachment' => ['Attachment is not supported for offer messages.'],
            ]);
        }

        if ($attachment !== null) {
            $disk = (string) config('chat.attachment_disk', 'public');
            $storedPath = $attachment->store((string) config('chat.attachment_path', 'chat-attachments'), $disk);
            $mime = (string) $attachment->getMimeType();
            $isImage = str_starts_with($mime, 'image/');
            $messageType = $isImage ? 'image' : 'file';
            $meta['attachment'] = [
                'path' => $storedPath,
                'url' => Storage::disk($disk)->url($storedPath),
                'name' => $attachment->getClientOriginalName(),
                'mime' => $mime,
                'size' => $attachment->getSize(),
            ];
            $body = $body ?: ($isImage ? 'Image attachment' : 'File attachment');
        }

        if ($messageType === 'text' && (! is_string($body) || trim($body) === '')) {
            throw ValidationException::withMessages([
                'body' => ['The body field is required for text messages.'],
            ]);
        }

        if ($messageType === 'offer') {
            $body = $body ?: 'Offer submitted';
            $meta = [
                'amount' => (float) ($meta['amount'] ?? 0),
                'currency' => strtoupper((string) ($meta['currency'] ?? config('chat.default_currency', 'IQD'))),
                'title' => $meta['title'] ?? 'Offer',
                'details' => $meta['details'] ?? null,
                'valid_until' => $meta['valid_until'] ?? null,
                'status' => 'pending',
            ];
        }

        $message = $conversation->messages()->create([
            'user_id' => $sender->id,
            'body' => $body,
            'message_type' => $messageType,
            'meta' => $meta,
        ]);

        $conversation->forceFill(['last_message_at' => $message->created_at])->save();

        $message->load('sender:id,name,is_admin,image');

        ChatMessageSent::dispatch($message);

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function formatMessage(ChatMessage $message): array
    {
        $message->loadMissing('sender:id,name,is_admin,image');

        return (new MessageResource($message))->resolve();
    }
}
