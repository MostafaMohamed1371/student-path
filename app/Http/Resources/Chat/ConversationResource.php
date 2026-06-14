<?php

namespace App\Http\Resources\Chat;

use App\Models\ChatConversation;
use App\Models\User;
use App\Services\Chat\ChatSchoolSupport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ChatConversation */
class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $other = $this->resolveOtherUser($viewer);
        $schoolSupport = app(ChatSchoolSupport::class);

        $lastMessage = $this->relationLoaded('messages')
            ? $this->messages->first()
            : null;

        $setting = null;
        if ($viewer && $this->relationLoaded('userSettings')) {
            $setting = $this->userSettings->firstWhere('user_id', $viewer->id);
        } elseif ($viewer && $this->relationLoaded('settingFor')) {
            $setting = $this->settingFor;
        }

        return [
            'id' => $this->id,
            'post_id' => $this->post_id,
            'school_id' => $this->school_id,
            'unread_count' => (int) ($this->getAttribute('unread_messages_count')
                ?? ($viewer ? $this->unreadCountFor($viewer) : 0)),
            'is_pinned' => (bool) ($setting?->is_pinned ?? false),
            'is_muted' => (bool) ($setting?->is_muted ?? false),
            'pinned_at' => $setting?->pinned_at?->toIso8601String(),
            'is_blocked' => (bool) ($this->getAttribute('is_blocked') ?? false),
            'other_user' => $other ? [
                'id' => $other->id,
                'name' => $other->name ?? '',
                'type' => $schoolSupport->isChatStaff($other) ? 'staff' : 'user',
                'image' => $other->image,
            ] : [
                'id' => null,
                'name' => $schoolSupport->displayNameForSchool(
                    $this->school_id !== null ? (int) $this->school_id : null,
                ),
                'type' => 'staff',
                'image' => null,
            ],
            'last_message' => $lastMessage ? new MessageResource($lastMessage) : null,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function resolveOtherUser(?User $viewer): ?User
    {
        if (! $viewer) {
            return null;
        }

        if ($viewer->isChatStaff()) {
            return $this->relationLoaded('user') ? $this->user : $this->user()->first();
        }

        if ($this->participant_id) {
            return $this->relationLoaded('participant') ? $this->participant : $this->participant()->first();
        }

        return app(ChatSchoolSupport::class)->defaultStaffUserForSchool(
            $this->school_id !== null ? (int) $this->school_id : null,
        );
    }
}
