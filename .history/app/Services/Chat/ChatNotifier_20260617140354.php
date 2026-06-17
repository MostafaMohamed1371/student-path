<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatConversationUserSetting;
use App\Models\ChatMessage;
use App\Models\InAppNotification;
use App\Models\User;
use Illuminate\Support\Str;

class ChatNotifier
{
    public function __construct(
        private readonly ChatSchoolSupport $schoolSupport,
    ) {}

    public function notifyNewMessage(ChatMessage $message): void
    {
        if (! config('chat.in_app_notifications_enabled', true)) {
            return;
        }

        $message->loadMissing('sender:id,name,is_admin,school_id,phone_account_type', 'conversation');

        $conversation = $message->conversation;
        $sender = $message->sender;

        if (! $conversation || ! $sender || $conversation->deleted_at !== null) {
            return;
        }

        foreach ($this->recipientsFor($conversation, $sender) as $recipient) {
            if ($this->isMuted($conversation, $recipient)) {
                continue;
            }

            InAppNotification::query()->create([
                'user_id' => $recipient->id,
                'title' => (string) config('chat.notification_title', 'New chat message'),
                'body' => $this->previewBody($message, $sender),
                'data' => [
                    'type' => 'CHAT_MESSAGE',
                    'conversation_id' => $conversation->id,
                    'chat_id' => $conversation->id,
                    'message_id' => $message->id,
                    'sender_id' => $sender->id,
                    'sender_name' => $sender->name,
                ],
            ]);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function recipientsFor(ChatConversation $conversation, User $sender): \Illuminate\Support\Collection
    {
        if ($conversation->isDeleted()) {
            return collect();
        }
        if ($conversation->isParentDriverChat()) {
            $otherId = (int) $sender->id === (int) $conversation->user_id
                ? (int) ($conversation->participant_id ?? 0)
                : (int) $conversation->user_id;
            if ($otherId <= 0) {
                return collect();
            }

            $other = User::query()->find($otherId);

            return $other ? collect([$other]) : collect();
        }

        if ($this->schoolSupport->isChatStaff($sender)) {
            $owner = $conversation->relationLoaded('user')
                ? $conversation->user
                : $conversation->user()->first();

            return $owner ? collect([$owner]) : collect();
        }

        return $this->schoolSupport->staffRecipientsFor($conversation, $sender);
    }

    private function isMuted(ChatConversation $conversation, User $recipient): bool
    {
        return ChatConversationUserSetting::query()
            ->where('chat_conversation_id', $conversation->id)
            ->where('user_id', $recipient->id)
            ->where('is_muted', true)
            ->exists();
    }

    private function previewBody(ChatMessage $message, User $sender): string
    {
        $name = trim((string) ($sender->name ?? 'User'));

        return match ($message->message_type) {
            'offer' => "{$name}: ".(string) config('chat.notification_offer_preview', 'Sent an offer'),
            'image' => "{$name}: ".(string) config('chat.notification_image_preview', 'Sent an image'),
            'file' => "{$name}: ".(string) config('chat.notification_file_preview', 'Sent a file'),
            default => "{$name}: ".Str::limit(trim((string) ($message->body ?? '')), 120),
        };
    }
}
