<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class ChatConversation extends Model
{
    protected $fillable = [
        'user_id',
        'participant_id',
        'post_id',
        'status',
        'subject',
        'last_message_at',
        'user_last_read_at',
        'staff_last_read_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'user_last_read_at' => 'datetime',
            'staff_last_read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

        if ($viewer->is_admin) {
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

        if ($viewer->is_admin) {
            $this->forceFill(['staff_last_read_at' => $now])->save();
        }
    }

    public function canBeAccessedBy(User $user): bool
    {
        if ($user->is_admin) {
            return true;
        }

        return (int) $this->user_id === (int) $user->id;
    }
}
