<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatConversationUserSetting;
use App\Models\ChatMessage;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Database\Eloquent\Builder;

class ChatParticipantResolver
{
    public function __construct(
        private readonly ChatSchoolSupport $schoolSupport,
    ) {}

    public function otherUserId(ChatConversation $conversation, User $viewer): ?int
    {
        if ($conversation->isParentDriverChat()) {
            if ((int) $viewer->id === (int) $conversation->user_id) {
                return $conversation->participant_id ? (int) $conversation->participant_id : null;
            }

            if ((int) $viewer->id === (int) $conversation->participant_id) {
                return (int) $conversation->user_id;
            }

            return null;
        }

        if ($viewer->isChatStaff()) {
            return (int) $conversation->user_id;
        }

        if ($conversation->participant_id) {
            return (int) $conversation->participant_id;
        }

        $staffId = $this->schoolSupport->defaultStaffUserForSchool(
            $conversation->school_id !== null ? (int) $conversation->school_id : null,
        )?->id;

        return $staffId ? (int) $staffId : null;
    }

    public function isBlockedBetween(int $userId, int $otherUserId): bool
    {
        return UserBlock::query()
            ->where(function ($q) use ($userId, $otherUserId) {
                $q->where('blocker_id', $userId)->where('blocked_id', $otherUserId);
            })
            ->orWhere(function ($q) use ($userId, $otherUserId) {
                $q->where('blocker_id', $otherUserId)->where('blocked_id', $userId);
            })
            ->exists();
    }

    public function visibleConversationsQuery(User $user): Builder
    {
        $query = ChatConversation::query();

        if ($user->is_admin) {
            $query->where('chat_conversations.conversation_type', ChatConversation::TYPE_SUPPORT);
        } elseif ($user->isSchoolChatStaff()) {
            $schoolId = $user->scopingSchoolId();
            if ($schoolId !== null) {
                $query->where('chat_conversations.school_id', $schoolId);
            } else {
                $query->whereRaw('1 = 0');
            }
            $query->where('chat_conversations.conversation_type', ChatConversation::TYPE_SUPPORT);
        } else {
            $userId = (int) $user->id;
            $query->where(function (Builder $inner) use ($userId): void {
                $inner->where('chat_conversations.user_id', $userId)
                    ->orWhere(function (Builder $peer) use ($userId): void {
                        $peer->where('chat_conversations.conversation_type', ChatConversation::TYPE_PARENT_DRIVER)
                            ->where('chat_conversations.participant_id', $userId);
                    });
            });
        }

        $query->whereNull('chat_conversations.deleted_at');

        return $query->whereNotExists(function ($sub) use ($user) {
            $sub->selectRaw('1')
                ->from('user_blocks')
                ->where(function ($q) use ($user) {
                    $q->where(function ($inner) use ($user) {
                        $inner->where('user_blocks.blocker_id', $user->id)
                            ->whereColumn('user_blocks.blocked_id', 'chat_conversations.user_id');
                    })->orWhere(function ($inner) use ($user) {
                        $inner->where('user_blocks.blocked_id', $user->id)
                            ->whereColumn('user_blocks.blocker_id', 'chat_conversations.user_id');
                    })->orWhere(function ($inner) use ($user) {
                        $inner->where('user_blocks.blocker_id', $user->id)
                            ->whereColumn('user_blocks.blocked_id', 'chat_conversations.participant_id');
                    })->orWhere(function ($inner) use ($user) {
                        $inner->where('user_blocks.blocked_id', $user->id)
                            ->whereColumn('user_blocks.blocker_id', 'chat_conversations.participant_id');
                    });
                });
        });
    }

    /**
     * @param  iterable<int, ChatConversation>  $conversations
     */
    public function attachIsBlockedFlag(iterable $conversations, User $viewer): void
    {
        foreach ($conversations as $conversation) {
            $otherId = $this->otherUserId($conversation, $viewer);
            $conversation->setAttribute(
                'is_blocked',
                $otherId !== null && $this->isBlockedBetween((int) $viewer->id, $otherId),
            );
        }
    }

    public function unreadMessagesCount(User $user): int
    {
        $mutedIds = ChatConversationUserSetting::query()
            ->where('user_id', $user->id)
            ->where('is_muted', true)
            ->pluck('chat_conversation_id');

        $query = ChatMessage::query()
            ->where('user_id', '!=', $user->id)
            ->whereNull('read_at')
            ->whereIn('chat_conversation_id', $this->visibleConversationsQuery($user)->select('id'));

        if ($mutedIds->isNotEmpty()) {
            $query->whereNotIn('chat_conversation_id', $mutedIds);
        }

        return $query->count();
    }
}
