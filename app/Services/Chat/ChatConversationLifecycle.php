<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatConversationUserSetting;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ChatConversationLifecycle
{
    public function __construct(
        private readonly ChatParticipantResolver $participants,
    ) {}

    public function unreadCount(User $user): int
    {
        return $this->participants->unreadMessagesCount($user);
    }

    public function markRead(ChatConversation $conversation, User $viewer): int
    {
        $before = $conversation->messages()
            ->whereNull('read_at')
            ->where('user_id', '!=', $viewer->id)
            ->count();

        $conversation->markReadBy($viewer);

        return $before;
    }

    public function markUnread(ChatConversation $conversation, User $viewer): int
    {
        $updated = $conversation->messages()
            ->where('user_id', '!=', $viewer->id)
            ->whereNotNull('read_at')
            ->update(['read_at' => null]);

        if ((int) $conversation->user_id === (int) $viewer->id) {
            $conversation->forceFill(['user_last_read_at' => null])->save();
        } elseif ($viewer->is_admin) {
            $conversation->forceFill(['staff_last_read_at' => null])->save();
        }

        if ($updated === 0) {
            $latestIncoming = $conversation->messages()
                ->where('user_id', '!=', $viewer->id)
                ->latest('id')
                ->first();

            if ($latestIncoming && $latestIncoming->read_at !== null) {
                $latestIncoming->update(['read_at' => null]);
                $updated = 1;
            }
        }

        return $updated;
    }

    /**
     * @return array<string, mixed>
     */
    public function updatePreferences(ChatConversation $conversation, User $viewer, Request $request): array
    {
        $validated = $request->validate([
            'is_pinned' => ['sometimes', 'boolean'],
            'is_muted' => ['sometimes', 'boolean'],
        ]);

        if (! $request->has('is_pinned') && ! $request->has('is_muted')) {
            throw ValidationException::withMessages([
                'is_pinned' => ['Provide is_pinned and/or is_muted.'],
            ]);
        }

        $setting = ChatConversationUserSetting::query()->firstOrNew([
            'user_id' => $viewer->id,
            'chat_conversation_id' => $conversation->id,
        ]);

        if ($request->has('is_pinned')) {
            $pinned = $request->boolean('is_pinned');
            $setting->is_pinned = $pinned;
            $setting->pinned_at = $pinned ? now() : null;
        }

        if ($request->has('is_muted')) {
            $setting->is_muted = $request->boolean('is_muted');
        }

        $setting->save();

        return [
            'is_pinned' => (bool) $setting->is_pinned,
            'is_muted' => (bool) $setting->is_muted,
            'pinned_at' => $setting->pinned_at?->toIso8601String(),
        ];
    }

    public function pin(ChatConversation $conversation, User $viewer, bool $pinned = true): ChatConversationUserSetting
    {
        $setting = ChatConversationUserSetting::query()->firstOrNew([
            'user_id' => $viewer->id,
            'chat_conversation_id' => $conversation->id,
        ]);

        $setting->is_pinned = $pinned;
        $setting->pinned_at = $pinned ? now() : null;
        $setting->save();

        return $setting;
    }

    public function block(ChatConversation $conversation, User $viewer, ?string $reason): void
    {
        $otherId = $this->participants->otherUserId($conversation, $viewer);
        if ($otherId === null) {
            throw ValidationException::withMessages([
                'user' => ['Cannot resolve blocked user.'],
            ]);
        }

        UserBlock::query()->updateOrCreate(
            [
                'blocker_id' => $viewer->id,
                'blocked_id' => $otherId,
            ],
            ['reason' => $reason],
        );
    }

    public function unblock(ChatConversation $conversation, User $viewer): void
    {
        $otherId = $this->participants->otherUserId($conversation, $viewer);
        if ($otherId === null) {
            throw ValidationException::withMessages([
                'user' => ['Cannot resolve unblocked user.'],
            ]);
        }

        UserBlock::query()
            ->where('blocker_id', $viewer->id)
            ->where('blocked_id', $otherId)
            ->delete();
    }

    public function assertNotBlocked(ChatConversation $conversation, User $viewer): void
    {
        $otherId = $this->participants->otherUserId($conversation, $viewer);
        if ($otherId !== null && $this->participants->isBlockedBetween((int) $viewer->id, $otherId)) {
            throw ValidationException::withMessages([
                'conversation' => ['You cannot interact with this chat while the user is blocked.'],
            ]);
        }
    }
}
