<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\Chat\ChatConversationLifecycle;
use App\Services\Chat\ChatMessenger;
use App\Services\Chat\ChatParticipantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DashboardChatController extends Controller
{
    public function __construct(
        private readonly ChatMessenger $messenger,
        private readonly ChatConversationLifecycle $lifecycle,
        private readonly ChatParticipantResolver $participants,
    ) {}

    public function index(Request $request): View
    {
        $this->ensureSupportStaff();

        $staff = $request->user();

        $conversations = ChatConversation::query()
            ->whereNull('deleted_at')
            ->with('user:id,name,phone')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $conversations->getCollection()->transform(function (ChatConversation $conversation) use ($staff) {
            $conversation->setAttribute('unread_count', $conversation->unreadCountFor($staff));

            return $conversation;
        });

        $pendingReports = \App\Models\ChatReport::query()->where('status', 'pending')->count();

        return view('dashboard.support-chat.index', [
            'conversations' => $conversations,
            'pendingReports' => $pendingReports,
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

        $staff = $request->user();
        $otherId = $this->participants->otherUserId($conversation, $staff);
        $isBlocked = $otherId !== null && $this->participants->isBlockedBetween((int) $staff->id, $otherId);

        return view('dashboard.support-chat.show', [
            'conversation' => $conversation,
            'messages' => $messages,
            'chatEnabled' => $chatEnabled,
            'pusherKey' => $pusherKey,
            'pusherCluster' => $pusherCluster,
            'eventName' => (string) config('chat.event_name', 'message.sent'),
            'isBlocked' => $isBlocked,
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
        $maxKb = (int) config('chat.attachment_max_kb', 20480);
        $mimes = (string) config('chat.attachment_mimes');

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:'.$maxLen],
            'attachment' => ['nullable', 'file', 'max:'.$maxKb, 'mimes:'.$mimes],
        ]);

        if (empty(trim((string) ($validated['body'] ?? ''))) && ! $request->hasFile('attachment')) {
            return response()->json([
                'message' => 'Message body or attachment is required.',
                'errors' => ['body' => ['Message body or attachment is required.']],
            ], 422);
        }

        try {
            $this->lifecycle->assertNotBlocked($conversation, $request->user());
            $message = $this->messenger->send(
                $conversation,
                $request->user(),
                'text',
                $validated['body'] ?? null,
                [],
                $request->file('attachment'),
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?: 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        }

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

    public function destroy(Request $request, ChatConversation $conversation): RedirectResponse
    {
        $this->ensureSupportStaff();
        abort_unless($conversation->canBeAccessedBy($request->user()), 404);

        try {
            $this->lifecycle->deleteConversation($conversation, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('dashboard.support_chat.index')
            ->with('success', __('dashboard.support_chat_deleted'));
    }

    public function block(Request $request, ChatConversation $conversation): RedirectResponse
    {
        $this->ensureSupportStaff();
        abort_unless($conversation->canBeAccessedBy($request->user()), 404);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->lifecycle->block($conversation, $request->user(), $validated['reason'] ?? null);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', __('dashboard.support_chat_user_blocked'));
    }

    public function unblock(Request $request, ChatConversation $conversation): RedirectResponse
    {
        $this->ensureSupportStaff();
        abort_unless($conversation->canBeAccessedBy($request->user()), 404);

        try {
            $this->lifecycle->unblock($conversation, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', __('dashboard.support_chat_user_unblocked'));
    }

    private function ensureSupportStaff(): void
    {
        abort_unless(auth()->user()?->is_admin, 403);
    }
}
