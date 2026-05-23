<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\Chat\ChatConversationLifecycle;
use App\Services\Chat\ChatMessenger;
use App\Services\Chat\ChatParticipantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ChatController extends Controller
{
    use FormatsParentApiResponse;

    public function __construct(
        private readonly ChatMessenger $messenger,
        private readonly ChatParticipantResolver $participants,
        private readonly ChatConversationLifecycle $lifecycle,
    ) {}

    public function config(): JsonResponse
    {
        $echo = config('realtime.laravel_echo', []);
        $connection = (string) config('broadcasting.default', 'null');

        return $this->parentSuccess([
            'enabled' => $connection === 'pusher' && filled(config('broadcasting.connections.pusher.key')),
            'private_channel_template' => (string) config('chat.private_channel_template', 'chat.{conversationId}'),
            'event_name' => (string) config('chat.event_name', 'message.sent'),
            'laravel_echo' => [
                'broadcaster' => $connection,
                'key' => $echo['key'] ?? config('broadcasting.connections.pusher.key'),
                'cluster' => $echo['cluster'] ?? config('broadcasting.connections.pusher.options.cluster'),
                'ws_host' => $echo['ws_host'] ?? null,
                'ws_port' => $echo['ws_port'] ?? null,
                'wss_port' => $echo['wss_port'] ?? null,
                'force_tls' => (bool) ($echo['force_tls'] ?? true),
                'auth_endpoint' => url($echo['auth_endpoint'] ?? '/broadcasting/auth'),
            ],
        ], 'success');
    }

    public function indexConversations(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = (int) $user->id;

        $pinnedSub = \App\Models\ChatConversationUserSetting::query()
            ->select('is_pinned')
            ->whereColumn('chat_conversation_id', 'chat_conversations.id')
            ->where('user_id', $userId)
            ->limit(1);

        $q = $this->participants->visibleConversationsQuery($user)
            ->with([
                'user:id,name,phone',
                'userSettings' => fn ($sq) => $sq->where('chat_conversation_user_settings.user_id', $userId),
            ])
            ->orderByDesc($pinnedSub)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        $rows = $q->paginate(min(100, max(1, (int) $request->query('per_page', 20))));
        $this->participants->attachIsBlockedFlag($rows->getCollection(), $user);

        $items = collect($rows->items())->map(fn (ChatConversation $c) => $this->formatConversation($c, $user))->values()->all();

        return $this->parentSuccess([
            'items' => $items,
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'last_page' => $rows->lastPage(),
            ],
        ]);
    }

    public function storeConversation(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->is_admin) {
            return $this->parentError('Only app users can start a support conversation.', null, 403);
        }

        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
        ]);

        $conversation = ChatConversation::query()
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->whereNull('deleted_at')
            ->first();

        $created = false;

        if (! $conversation) {
            $conversation = ChatConversation::query()->create([
                'user_id' => $user->id,
                'status' => 'open',
                'subject' => $validated['subject'] ?? null,
                'user_last_read_at' => now(),
            ]);
            $created = true;
        } elseif (! empty($validated['subject']) && $conversation->subject === null) {
            $conversation->update(['subject' => $validated['subject']]);
        }

        $conversation->load('user:id,name,phone');

        return $this->parentSuccess(
            $this->formatConversation($conversation, $user),
            'Conversation ready',
            $created ? 201 : 200,
        );
    }

    public function showConversation(Request $request, ChatConversation $conversation): JsonResponse
    {
        if (! $conversation->canBeAccessedBy($request->user())) {
            return $this->parentError('forbidden', null, 403);
        }

        $conversation->load('user:id,name,phone');

        return $this->parentSuccess($this->formatConversation($conversation, $request->user()));
    }

    public function indexMessages(Request $request, ChatConversation $conversation): JsonResponse
    {
        if (! $conversation->canBeAccessedBy($request->user())) {
            return $this->parentError('forbidden', null, 403);
        }

        $validated = $request->validate([
            'before_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $q = $conversation->messages()
            ->with('sender:id,name,is_admin')
            ->orderByDesc('id');

        if (! empty($validated['before_id'])) {
            $q->where('id', '<', (int) $validated['before_id']);
        }

        $rows = $q->limit(min(100, max(1, (int) $request->query('limit', 50))))->get()->reverse()->values();

        return $this->parentSuccess([
            'conversation_id' => $conversation->id,
            'items' => $rows->map(fn (ChatMessage $m) => $this->messenger->formatMessage($m))->all(),
        ]);
    }

    public function storeMessage(Request $request, ChatConversation $conversation): JsonResponse
    {
        if (! $conversation->canBeAccessedBy($request->user())) {
            return $this->parentError('forbidden', null, 403);
        }

        try {
            $this->lifecycle->assertNotBlocked($conversation, $request->user());
        } catch (ValidationException $e) {
            return $this->parentError(
                collect($e->errors())->flatten()->first() ?: 'Blocked.',
                $e->errors(),
                403,
            );
        }

        $maxLen = (int) config('chat.max_message_length', 5000);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:'.$maxLen],
        ]);

        try {
            $message = $this->messenger->send(
                $conversation,
                $request->user(),
                'text',
                $validated['body'],
            );
            $payload = $this->messenger->formatMessage($message);
        } catch (ValidationException $e) {
            return $this->parentError(
                collect($e->errors())->flatten()->first() ?: 'Validation error.',
                $e->errors(),
                422,
            );
        }

        return $this->parentSuccess($payload, 'Message sent', 201);
    }

    public function markRead(Request $request, ChatConversation $conversation): JsonResponse
    {
        if (! $conversation->canBeAccessedBy($request->user())) {
            return $this->parentError('forbidden', null, 403);
        }

        $count = $this->lifecycle->markRead($conversation, $request->user());

        return $this->parentSuccess(['updated_count' => $count], 'Conversation marked as read');
    }

    public function markUnread(Request $request, ChatConversation $conversation): JsonResponse
    {
        if (! $conversation->canBeAccessedBy($request->user())) {
            return $this->parentError('forbidden', null, 403);
        }

        $count = $this->lifecycle->markUnread($conversation, $request->user());

        return $this->parentSuccess(['updated_count' => $count], 'Conversation marked as unread');
    }

    public function unreadMessagesCount(Request $request): JsonResponse
    {
        return $this->parentSuccess([
            'unread_count' => $this->lifecycle->unreadCount($request->user()),
        ], 'Unread count retrieved');
    }

    public function updatePreferences(Request $request, ChatConversation $conversation): JsonResponse
    {
        if (! $conversation->canBeAccessedBy($request->user())) {
            return $this->parentError('forbidden', null, 403);
        }

        try {
            $prefs = $this->lifecycle->updatePreferences($conversation, $request->user(), $request);
        } catch (ValidationException $e) {
            return $this->parentError('Validation error', $e->errors(), 422);
        }

        return $this->parentSuccess(
            array_merge($this->formatConversation($conversation, $request->user()), $prefs),
            'Chat preferences updated',
        );
    }

    public function pinChat(Request $request, ChatConversation $conversation): JsonResponse
    {
        return $this->setPinnedState($request, $conversation, true);
    }

    public function unpinChat(Request $request, ChatConversation $conversation): JsonResponse
    {
        return $this->setPinnedState($request, $conversation, false);
    }

    public function blockUser(Request $request, ChatConversation $conversation): JsonResponse
    {
        if (! $conversation->canBeAccessedBy($request->user())) {
            return $this->parentError('forbidden', null, 403);
        }

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        try {
            $this->lifecycle->block($conversation, $request->user(), $validated['reason'] ?? null);
        } catch (ValidationException $e) {
            return $this->parentError(collect($e->errors())->flatten()->first() ?: 'Error', $e->errors(), 422);
        }

        return $this->parentSuccess(['is_blocked' => true], 'User blocked successfully');
    }

    public function unblockUser(Request $request, ChatConversation $conversation): JsonResponse
    {
        if (! $conversation->canBeAccessedBy($request->user())) {
            return $this->parentError('forbidden', null, 403);
        }

        try {
            $this->lifecycle->unblock($conversation, $request->user());
        } catch (ValidationException $e) {
            return $this->parentError(collect($e->errors())->flatten()->first() ?: 'Error', $e->errors(), 422);
        }

        return $this->parentSuccess(['is_blocked' => false], 'User unblocked successfully');
    }

    public function destroyConversation(Request $request, ChatConversation $conversation): JsonResponse
    {
        if (! $conversation->canBeAccessedBy($request->user())) {
            return $this->parentError('forbidden', null, 403);
        }

        try {
            $conversation = $this->lifecycle->deleteConversation($conversation, $request->user());
        } catch (ValidationException $e) {
            return $this->parentError(collect($e->errors())->flatten()->first() ?: 'Error', $e->errors(), 422);
        }

        return $this->parentSuccess([
            'id' => $conversation->id,
            'deleted_at' => $conversation->deleted_at?->toIso8601String(),
        ], 'Conversation deleted successfully');
    }

    public function reportConversation(Request $request, ChatConversation $conversation): JsonResponse
    {
        if (! $conversation->canBeAccessedBy($request->user())) {
            return $this->parentError('forbidden', null, 403);
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
            return $this->parentError(collect($e->errors())->flatten()->first() ?: 'Error', $e->errors(), 422);
        }

        return $this->parentSuccess($data, 'Chat reported successfully', 201);
    }

    private function setPinnedState(Request $request, ChatConversation $conversation, bool $pinned): JsonResponse
    {
        if (! $conversation->canBeAccessedBy($request->user())) {
            return $this->parentError('forbidden', null, 403);
        }

        $this->lifecycle->pin($conversation, $request->user(), $pinned);

        return $this->parentSuccess(
            $this->formatConversation($conversation, $request->user()),
            $pinned ? 'Chat pinned' : 'Chat unpinned',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formatConversation(ChatConversation $conversation, User $viewer): array
    {
        $conversation->loadMissing(['userSettings' => fn ($q) => $q->where('chat_conversation_user_settings.user_id', $viewer->id)]);
        $this->participants->attachIsBlockedFlag([$conversation], $viewer);

        $lastMessage = $conversation->messages()
            ->with('sender:id,name,is_admin')
            ->latest('id')
            ->first();

        $setting = $conversation->userSettings->firstWhere('user_id', $viewer->id);

        return [
            'id' => $conversation->id,
            'status' => $conversation->status,
            'subject' => $conversation->subject,
            'private_channel' => 'private-chat.'.$conversation->id,
            'user' => $conversation->user ? [
                'id' => $conversation->user->id,
                'name' => $conversation->user->name,
                'phone' => $conversation->user->phone,
            ] : null,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'unread_count' => $conversation->unreadCountFor($viewer),
            'is_pinned' => (bool) ($setting?->is_pinned ?? false),
            'is_muted' => (bool) ($setting?->is_muted ?? false),
            'pinned_at' => $setting?->pinned_at?->toIso8601String(),
            'is_blocked' => (bool) ($conversation->getAttribute('is_blocked') ?? false),
            'last_message' => $lastMessage ? $this->messenger->formatMessage($lastMessage) : null,
            'created_at' => $conversation->created_at?->toIso8601String(),
        ];
    }
}
