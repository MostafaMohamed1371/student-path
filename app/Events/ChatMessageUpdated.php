<?php

namespace App\Events;

use App\Http\Resources\Chat\MessageResource;
use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ChatMessage $message,
        public string $action = 'updated',
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.'.$this->message->chat_conversation_id),
        ];
    }

    public function broadcastAs(): string
    {
        return (string) config('chat.message_updated_event_name', 'message.updated');
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->message->loadMissing('sender:id,name,is_admin,image');

        return [
            'conversation_id' => $this->message->chat_conversation_id,
            'action' => $this->action,
            'message' => (new MessageResource($this->message))->resolve(),
        ];
    }
}
