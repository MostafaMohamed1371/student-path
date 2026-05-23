<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\Chat\ChatMessenger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardChatController extends Controller
{
    public function __construct(
        private readonly ChatMessenger $messenger,
    ) {}

    public function index(Request $request): View
    {
        $this->ensureSupportStaff();

        $staff = $request->user();

        $conversations = ChatConversation::query()
            ->with('user:id,name,phone')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $conversations->getCollection()->transform(function (ChatConversation $conversation) use ($staff) {
            $conversation->setAttribute('unread_count', $conversation->unreadCountFor($staff));

            return $conversation;
        });

        return view('dashboard.support-chat.index', [
            'conversations' => $conversations,
        ]);
    }

    public function show(Request $request, ChatConversation $conversation): View
    {
        $this->ensureSupportStaff();
        abort_unless($conversation->canBeAccessedBy($request->user()), 404);

        $conversation->load('user:id,name,phone');
        $conversation->markReadBy($request->user());

        $messages = $conversation->messages()
            ->with('sender:id,name,is_admin')
            ->orderBy('id')
            ->limit(200)
            ->get();

        $pusherKey = (string) config('broadcasting.connections.pusher.key');
        $pusherCluster = (string) config('broadcasting.connections.pusher.options.cluster');
        $chatEnabled = config('broadcasting.default') === 'pusher' && $pusherKey !== '';

        return view('dashboard.support-chat.show', [
            'conversation' => $conversation,
            'messages' => $messages,
            'chatEnabled' => $chatEnabled,
            'pusherKey' => $pusherKey,
            'pusherCluster' => $pusherCluster,
            'eventName' => (string) config('chat.event_name', 'message.sent'),
        ]);
    }

    public function messages(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->ensureSupportStaff();
        abort_unless($conversation->canBeAccessedBy($request->user()), 404);

        $validated = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $q = $conversation->messages()
            ->with('sender:id,name,is_admin')
            ->orderBy('id');

        if (! empty($validated['after_id'])) {
            $q->where('id', '>', (int) $validated['after_id']);
        }

        $rows = $q->limit(100)->get();

        return response()->json([
            'items' => $rows->map(fn (ChatMessage $m) => $this->messenger->formatMessage($m))->values()->all(),
        ]);
    }

    public function storeMessage(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->ensureSupportStaff();
        abort_unless($conversation->canBeAccessedBy($request->user()), 404);

        $maxLen = (int) config('chat.max_message_length', 5000);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:'.$maxLen],
        ]);

        $message = $this->messenger->send(
            $conversation,
            $request->user(),
            'text',
            $validated['body'],
        );

        return response()->json(['message' => $this->messenger->formatMessage($message)], 201);
    }

    public function markRead(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->ensureSupportStaff();
        abort_unless($conversation->canBeAccessedBy($request->user()), 404);

        $conversation->markReadBy($request->user());

        return response()->json(['ok' => true]);
    }

    public function close(Request $request, ChatConversation $conversation): RedirectResponse
    {
        $this->ensureSupportStaff();
        abort_unless($conversation->canBeAccessedBy($request->user()), 404);

        $conversation->update(['status' => 'closed']);

        return redirect()
            ->route('dashboard.support_chat.show', $conversation)
            ->with('success', __('dashboard.support_chat_closed'));
    }

    public function reopen(Request $request, ChatConversation $conversation): RedirectResponse
    {
        $this->ensureSupportStaff();
        abort_unless($conversation->canBeAccessedBy($request->user()), 404);

        $conversation->update(['status' => 'open']);

        return redirect()
            ->route('dashboard.support_chat.show', $conversation)
            ->with('success', __('dashboard.support_chat_reopened'));
    }

    private function ensureSupportStaff(): void
    {
        abort_unless(auth()->user()?->is_admin, 403);
    }
}
