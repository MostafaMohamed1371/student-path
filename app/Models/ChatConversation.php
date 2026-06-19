<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $deleted_at
 */

class ChatConversation extends Model
{
    public const TYPE_SUPPORT = 'support';

    public const TYPE_PARENT_DRIVER = 'parent_driver';

    protected $fillable = [
        'user_id',
        'conversation_type',
        'school_id',
        'participant_id',
        'trip_request_id',
        'trip_history_id',
        'post_id',
        'status',
        'subject',
        'last_message_at',
        'user_last_read_at',
        'staff_last_read_at',
        'participant_last_read_at',
        'deleted_at',
        'deleted_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'user_last_read_at' => 'datetime',
            'staff_last_read_at' => 'datetime',
            'participant_last_read_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ChatReport::class);
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    public function tripRequest(): BelongsTo
    {
        return $this->belongsTo(TripRequest::class);
    }

    public function tripHistory(): BelongsTo
    {
        return $this->belongsTo(TripHistory::class);
    }

    public function isParentDriverChat(): bool
    {
        return $this->conversation_type === self::TYPE_PARENT_DRIVER;
    }

    public function isSupportChat(): bool
    {
        return $this->conversation_type === self::TYPE_SUPPORT;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function userSettings(): HasMany
    {
        return $this->hasMany(ChatConversationUserSetting::class);
    }

    public function settingFor(User $user): HasOne
    {
        return $this->hasOne(ChatConversationUserSetting::class)
            ->where('user_id', $user->id);
    }

    public function unreadCountFor(User $viewer): int
    {
        return $this->messages()
            ->where('user_id', '!=', $viewer->id)
            ->whereNull('read_at')
            ->count();
    }

    public function lastReadAtFor(User $viewer): ?Carbon
    {
        if ((int) $this->user_id === (int) $viewer->id) {
            return $this->user_last_read_at;
        }

        if ($this->isParentDriverChat() && (int) $this->participant_id === (int) $viewer->id) {
            return $this->participant_last_read_at;
        }

        if ($viewer->isChatStaff()) {
            return $this->staff_last_read_at;
        }

        return null;
    }

    public function markReadBy(User $viewer): void
    {
        $now = now();

        $this->messages()
            ->where('user_id', '!=', $viewer->id)
            ->whereNull('read_at')
            ->update(['read_at' => $now]);

        if ((int) $this->user_id === (int) $viewer->id) {
            $this->forceFill(['user_last_read_at' => $now])->save();

            return;
        }

        if ($this->isParentDriverChat() && (int) $this->participant_id === (int) $viewer->id) {
            $this->forceFill(['participant_last_read_at' => $now])->save();

            return;
        }

        if ($viewer->isChatStaff()) {
            $this->forceFill(['staff_last_read_at' => $now])->save();
        }
    }

    public function canBeAccessedBy(User $user): bool
    {
        if ($this->isDeleted()) {
            return false;
        }

        if ($this->isParentDriverChat()) {
            return (int) $this->user_id === (int) $user->id
                || (int) $this->participant_id === (int) $user->id;
        }

        if ((int) $this->user_id === (int) $user->id) {
            return true;
        }

        return app(\App\Services\Chat\ChatSchoolSupport::class)->canStaffAccessConversation($user, $this);
    }
}
