<?php

namespace App\Http\Controllers\Api\User;

use App\Events\ChatMessageUpdated;
use App\Events\ChatTypingStatusUpdated;
use App\Http\Controllers\Controller;
use App\Http\Resources\Chat\ConversationResource;
use App\Http\Resources\Chat\MessageResource;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\Chat\ChatConversationLifecycle;
use App\Services\Chat\ChatMessenger;
use App\Services\Chat\ChatOfferActions;
use App\Services\Chat\ChatParticipantResolver;
use App\Support\ApiPagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Contract: myappbackend User-Chat.postman_collection.json (/api/user/chats/*)
 */
class ChatController extends Controller
{
    public function __construct(
        private readonly ChatMessenger $messenger,
        private readonly ChatOfferActions $offerActions,
        private readonly ChatParticipantResolver $participants,
        private readonly ChatConversationLifecycle $lifecycle,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $perPage = (int) ($validated['per_page'] ?? 20);
        $search = trim((string) ($validated['search'] ?? ''));

        $userId = (int) $user->id;

        $pinnedSub = \App\Models\ChatConversationUserSetting::query()
            ->select('is_pinned')
            ->whereColumn('chat_conversation_id', 'chat_conversations.id')
            ->where('user_id', $userId)
            ->limit(1);

        $query = $this->participants->visibleConversationsQuery($user)
            ->withCount([
                'messages as unread_messages_count' => function ($q) use ($user) {
                    $q->whereNull('chat_messages.read_at')
                        ->where('chat_messages.user_id', '!=', $user->id);
                },
            ])
            ->with([
                'user:id,name,phone,image,is_admin',
                'participant:id,name,phone,image,is_admin',
                'userSettings' => fn ($q) => $q->where('chat_conversation_user_settings.user_id', $userId),
                'messages' => fn ($q) => $q->latest('id')->limit(1)->with('sender:id,name,is_admin,image'),
            ])
            ->orderByDesc($pinnedSub)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search, $user) {
                if ($user->is_admin) {
                    $q->whereHas('user', function (Builder $uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
                } else {
                    $q->whereHas('participant', function (Builder $uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
                }
            });
        }

        $conversations = $query->paginate($perPage);
        $this->participants->attachIsBlockedFlag($conversations->getCollection(), $user);

        return response()->json([
            'message' => 'Conversations retrieved successfully.',
            'data' => ConversationResource::collection($conversations),
            'pagination' => ApiPagination::meta($conversations),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'participant_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('is_admin', true)),
                Rule::notIn([$user->id]),
            ],
            'post_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($user->is_admin) {
            return response()->json([
                'message' => 'Only app users can start a support conversation from this endpoint.',
            ], 403);
        }

        $participantId = isset($validated['participant_id']) ? (int) $validated['participant_id'] : null;

        $conversation = ChatConversation::query()
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->whereNull('deleted_at')
            ->first();

        $existing = $conversation !== null;

        if (! $conversation) {
            $conversation = ChatConversation::query()->create([
                'user_id' => $user->id,
                'participant_id' => $participantId,
                'post_id' => $validated['post_id'] ?? null,
                'status' => 'open',
                'user_last_read_at' => now(),
            ]);
        } else {
            $updates = [];
            if ($participantId !== null) {
                $updates['participant_id'] = $participantId;
            }
            if (array_key_exists('post_id', $validated)) {
                $updates['post_id'] = $validated['post_id'];
            }
            if ($updates !== []) {
                $conversation->update($updates);
            }
        }

        $conversation->load([
            'user:id,name,phone,image,is_admin',
            'participant:id,name,phone,image,is_admin',
            'userSettings' => fn ($q) => $q->where('user_id', $user->id),
            'messages' => fn ($q) => $q->latest('id')->limit(1)->with('sender:id,name,is_admin,image'),
        ]);
        $this->participants->attachIsBlockedFlag([$conversation], $user);

        return response()->json([
            'message' => $existing ? 'Existing conversation returned.' : 'Conversation ready.',
            'existing' => $existing,
            'data' => new ConversationResource($conversation),
        ], $existing ? 200 : 201);
    }

    public function messages(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $conversation = $this->resolveConversation($request, $id);
        if (! $conversation) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        $perPage = (int) ($validated['per_page'] ?? 30);

        $messages = $conversation->messages()
            ->with('sender:id,name,is_admin,image')
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'message' => 'Messages retrieved successfully.',
            'data' => MessageResource::collection($messages),
            'pagination' => ApiPagination::meta($messages),
        ]);
    }

    public function send(Request $request, int $id): JsonResponse
    {
        $conversation = $this->resolveConversation($request, $id);
        if (! $conversation) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        try {
            $this->lifecycle->assertNotBlocked($conversation, $request->user());
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?: 'Blocked.',
                'errors' => $e->errors(),
            ], 403);
        }

        $maxBody = (int) config('chat.max_message_length', 5000);
        $maxKb = (int) config('chat.attachment_max_kb', 20480);
        $mimes = (string) config('chat.attachment_mimes');

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:'.$maxBody],
            'message_type' => ['nullable', 'in:text,offer,image,file'],
            'meta' => ['nullable', 'array'],
            'meta.amount' => ['required_if:message_type,offer', 'numeric', 'min:0.01'],
            'meta.currency' => ['nullable', 'string', 'size:3'],
            'meta.title' => ['nullable', 'string', 'max:255'],
            'meta.details' => ['nullable', 'string', 'max:4000'],
            'meta.valid_until' => ['nullable', 'date', 'after:now'],
            'attachment' => ['nullable', 'file', 'max:'.$maxKb, 'mimes:'.$mimes],
        ]);

        try {
            $message = $this->messenger->send(
                $conversation,
                $request->user(),
                (string) ($validated['message_type'] ?? 'text'),
                $validated['body'] ?? null,
                $validated['meta'] ?? [],
                $request->file('attachment'),
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'Message sent successfully.',
            'data' => new MessageResource($message),
        ], 201);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $conversation = $this->resolveConversation($request, $id);
        if (! $conversation) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        $count = $this->lifecycle->markRead($conversation, $request->user());

        return response()->json([
            'message' => 'Messages marked as read.',
            'data' => ['updated_count' => $count],
        ]);
    }

    public function markUnread(Request $request, int $id): JsonResponse
    {
        $conversation = $this->resolveConversation($request, $id);
        if (! $conversation) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        $count = $this->lifecycle->markUnread($conversation, $request->user());

        return response()->json([
            'message' => 'Conversation marked as unread.',
            'data' => ['updated_count' => $count],
        ]);
    }

    public function unreadMessagesCount(Request $request): JsonResponse
    {
        $count = $this->lifecycle->unreadCount($request->user());

        return response()->json([
            'message' => 'Unread messages count retrieved successfully.',
            'data' => ['unread_count' => $count],
        ]);
    }

    public function updatePreferences(Request $request, int $id): JsonResponse
    {
        $conversation = $this->resolveConversation($request, $id);
        if (! $conversation) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        try {
            $this->lifecycle->updatePreferences($conversation, $request->user(), $request);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        }

        $conversation->load([
            'user:id,name,phone,image,is_admin',
            'participant:id,name,phone,image,is_admin',
            'userSettings' => fn ($q) => $q->where('user_id', $request->user()->id),
            'messages' => fn ($q) => $q->latest('id')->limit(1)->with('sender:id,name,is_admin,image'),
        ]);
        $this->participants->attachIsBlockedFlag([$conversation], $request->user());

        return response()->json([
            'message' => 'Chat preferences updated.',
            'data' => new ConversationResource($conversation),
        ]);
    }

    public function pinChat(Request $request, int $id): JsonResponse
    {
        return $this->setPinned($request, $id, true);
    }

    public function unpinChat(Request $request, int $id): JsonResponse
    {
        return $this->setPinned($request, $id, false);
    }

    public function blockUser(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $conversation = $this->resolveConversation($request, $id);
        if (! $conversation) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        try {
            $this->lifecycle->block($conversation, $request->user(), $validated['reason'] ?? null);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?: 'Validation error.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'User blocked successfully.',
            'data' => ['is_blocked' => true],
        ]);
    }

    public function unblockUser(Request $request, int $id): JsonResponse
    {
        $conversation = $this->resolveConversation($request, $id);
        if (! $conversation) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        try {
            $this->lifecycle->unblock($conversation, $request->user());
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?: 'Validation error.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'User unblocked successfully.',
            'data' => ['is_blocked' => false],
        ]);
    }

    private function setPinned(Request $request, int $id, bool $pinned): JsonResponse
    {
        $conversation = $this->resolveConversation($request, $id);
        if (! $conversation) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        $this->lifecycle->pin($conversation, $request->user(), $pinned);

        $conversation->load([
            'user:id,name,phone,image,is_admin',
            'participant:id,name,phone,image,is_admin',
            'userSettings' => fn ($q) => $q->where('user_id', $request->user()->id),
            'messages' => fn ($q) => $q->latest('id')->limit(1)->with('sender:id,name,is_admin,image'),
        ]);
        $this->participants->attachIsBlockedFlag([$conversation], $request->user());

        return response()->json([
            'message' => $pinned ? 'Chat pinned successfully.' : 'Chat unpinned successfully.',
            'data' => new ConversationResource($conversation),
        ]);
    }

    public function typing(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'is_typing' => ['required', 'boolean'],
        ]);

        $conversation = $this->resolveConversation($request, $id);
        if (! $conversation) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        $user = $request->user();

        ChatTypingStatusUpdated::dispatch(
            $conversation->id,
            (int) $user->id,
            (string) ($user->name ?? ''),
            (bool) $validated['is_typing'],
        );

        return response()->json([
            'message' => 'Typing status updated.',
        ]);
    }

    public function updateMessage(Request $request, int $chatId, int $messageId): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:'.(int) config('chat.max_message_length', 5000)],
        ]);

        $message = $this->resolveMessage($request, $chatId, $messageId);
        if (! $message) {
            return response()->json(['message' => 'Message not found.'], 404);
        }

        if ((int) $message->user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'You can only edit your own messages.'], 403);
        }

        if (! in_array($message->message_type, ['text', 'offer'], true)) {
            return response()->json(['message' => 'This message type cannot be edited.'], 422);
        }

        $meta = is_array($message->meta) ? $message->meta : [];
        $meta['edited_at'] = now()->toIso8601String();
        $meta['is_edited'] = true;

        $message->update([
            'body' => $validated['body'],
            'meta' => $meta,
        ]);

        $message->refresh()->load('sender:id,name,is_admin,image');

        ChatMessageUpdated::dispatch($message, 'edited');

        return response()->json([
            'message' => 'Message updated successfully.',
            'data' => new MessageResource($message),
        ]);
    }

    public function deleteMessage(Request $request, int $chatId, int $messageId): JsonResponse
    {
        $message = $this->resolveMessage($request, $chatId, $messageId);
        if (! $message) {
            return response()->json(['message' => 'Message not found.'], 404);
        }

        if ((int) $message->user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'You can only delete your own messages.'], 403);
        }

        $conversationId = (int) $message->chat_conversation_id;
        $deletedId = (int) $message->id;

        $message->delete();

        $shadow = new ChatMessage([
            'id' => $deletedId,
            'chat_conversation_id' => $conversationId,
            'user_id' => $request->user()->id,
            'body' => null,
            'message_type' => 'text',
            'meta' => ['is_deleted' => true, 'deleted_at' => now()->toIso8601String()],
            'created_at' => now(),
        ]);
        $shadow->setRelation('sender', $request->user());

        ChatMessageUpdated::dispatch($shadow, 'deleted');

        return response()->json([
            'message' => 'Message deleted successfully.',
            'data' => ['id' => $deletedId],
        ]);
    }

    public function acceptOffer(Request $request, int $chatId, int $messageId): JsonResponse
    {
        $message = $this->resolveOfferMessage($request, $chatId, $messageId);
        if (! $message) {
            return response()->json(['message' => 'Offer message not found.'], 404);
        }

        $conversation = $this->resolveConversation($request, $chatId);
        if (! $conversation) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        try {
            $offer = $this->offerActions->accept($conversation, $message, $request->user());
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?: 'Validation error.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'Offer accepted successfully.',
            'data' => new MessageResource($offer),
        ]);
    }

    public function rejectOffer(Request $request, int $chatId, int $messageId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $message = $this->resolveOfferMessage($request, $chatId, $messageId);
        if (! $message) {
            return response()->json(['message' => 'Offer message not found.'], 404);
        }

        $conversation = $this->resolveConversation($request, $chatId);
        if (! $conversation) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        try {
            $offer = $this->offerActions->reject(
                $conversation,
                $message,
                $request->user(),
                $validated['reason'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?: 'Validation error.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'Offer rejected successfully.',
            'data' => new MessageResource($offer),
        ]);
    }

    public function counterOffer(Request $request, int $chatId, int $messageId): JsonResponse
    {
        $message = $this->resolveOfferMessage($request, $chatId, $messageId);
        if (! $message) {
            return response()->json(['message' => 'Offer message not found.'], 404);
        }

        $conversation = $this->resolveConversation($request, $chatId);
        if (! $conversation) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        try {
            $result = $this->offerActions->counter($conversation, $message, $request->user(), $request);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'Counter offer sent successfully.',
            'data' => [
                'updated_original_offer' => new MessageResource($result['original']),
                'counter_offer_message' => new MessageResource($result['counter']),
            ],
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $conversation = $this->resolveConversation($request, $id);
        if (! $conversation) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        try {
            $conversation = $this->lifecycle->deleteConversation($conversation, $request->user());
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?: 'Unable to delete conversation.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'Conversation deleted successfully.',
            'data' => [
                'id' => $conversation->id,
                'deleted_at' => $conversation->deleted_at?->toIso8601String(),
            ],
        ]);
    }

    public function report(Request $request, int $id): JsonResponse
    {
        $conversation = $this->resolveConversation($request, $id);
        if (! $conversation) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->lifecycle->assertNotBlocked($conversation, $request->user());
            $data = $this->lifecycle->reportConversation(
                $conversation,
                $request->user(),
                $validated['reason'],
                $validated['details'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?: 'Unable to report conversation.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'Chat reported successfully. Our team will review it.',
            'data' => $data,
        ], 201);
    }

    public function offerThread(Request $request, int $chatId, int $messageId): JsonResponse
    {
        $message = $this->resolveOfferMessage($request, $chatId, $messageId);
        if (! $message) {
            return response()->json(['message' => 'Offer message not found.'], 404);
        }

        $conversation = $this->resolveConversation($request, $chatId);
        if (! $conversation) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        $thread = $this->offerActions->thread($conversation, $message);

        return response()->json([
            'message' => 'Offer thread retrieved successfully.',
            'data' => MessageResource::collection($thread),
        ]);
    }

    private function resolveConversation(Request $request, int $id): ?ChatConversation
    {
        $conversation = ChatConversation::query()
            ->whereNull('deleted_at')
            ->when(
                ! $request->user()->is_admin,
                fn (Builder $q) => $q->where('user_id', $request->user()->id),
            )
            ->find($id);

        if (! $conversation || ! $conversation->canBeAccessedBy($request->user())) {
            return null;
        }

        return $conversation;
    }

    private function resolveMessage(Request $request, int $chatId, int $messageId): ?ChatMessage
    {
        $conversation = $this->resolveConversation($request, $chatId);
        if (! $conversation) {
            return null;
        }

        return $conversation->messages()->whereKey($messageId)->first();
    }

    private function resolveOfferMessage(Request $request, int $chatId, int $messageId): ?ChatMessage
    {
        $message = $this->resolveMessage($request, $chatId, $messageId);

        return $message && $message->message_type === 'offer' ? $message : null;
    }
}
