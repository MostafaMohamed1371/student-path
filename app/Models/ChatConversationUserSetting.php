<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatConversationUserSetting extends Model
{
    protected $fillable = [
        'user_id',
        'chat_conversation_id',
        'is_pinned',
        'pinned_at',
        'is_muted',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'is_muted' => 'boolean',
            'pinned_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }
}
