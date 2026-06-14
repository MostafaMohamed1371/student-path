<?php

namespace App\Services\Chat;

use App\Events\ChatOfferUpdated;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ChatOfferActions
{
    public function __construct(
        private readonly ChatMessenger $messenger,
    ) {}

    public function accept(ChatConversation $conversation, ChatMessage $offer, User $actor): ChatMessage
    {
        $this->assertCanProcessOffer($offer, $actor);

        $meta = is_array($offer->meta) ? $offer->meta : [];
        $meta['status'] = 'accepted';
        $meta['action_by'] = $actor->id;
        $meta['action_at'] = now()->toIso8601String();

        $offer->update(['meta' => $meta]);
        $offer->refresh()->load('sender:id,name,is_admin,school_id,phone_account_type,image');

        ChatOfferUpdated::dispatch($offer);

        return $offer;
    }

    public function reject(ChatConversation $conversation, ChatMessage $offer, User $actor, ?string $reason): ChatMessage
    {
        $this->assertCanProcessOffer($offer, $actor);

        $meta = is_array($offer->meta) ? $offer->meta : [];
        $meta['status'] = 'rejected';
        $meta['action_by'] = $actor->id;
        $meta['action_at'] = now()->toIso8601String();
        $meta['reason'] = $reason;

        $offer->update(['meta' => $meta]);
        $offer->refresh()->load('sender:id,name,is_admin,school_id,phone_account_type,image');

        ChatOfferUpdated::dispatch($offer);

        return $offer;
    }

    /**
     * @return array{original: ChatMessage, counter: ChatMessage}
     */
    public function counter(ChatConversation $conversation, ChatMessage $offer, User $actor, Request $request): array
    {
        $this->assertCanProcessOffer($offer, $actor);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
            'title' => ['nullable', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:4000'],
            'valid_until' => ['nullable', 'date', 'after:now'],
            'body' => ['nullable', 'string', 'max:4000'],
        ]);

        $meta = is_array($offer->meta) ? $offer->meta : [];
        $meta['status'] = 'countered';
        $meta['action_by'] = $actor->id;
        $meta['action_at'] = now()->toIso8601String();
        $offer->update(['meta' => $meta]);
        $offer->refresh()->load('sender:id,name,is_admin,school_id,phone_account_type,image');

        ChatOfferUpdated::dispatch($offer);

        $counter = $this->messenger->send(
            $conversation,
            $actor,
            'offer',
            $validated['body'] ?? 'Counter offer submitted',
            [
                'amount' => (float) $validated['amount'],
                'currency' => strtoupper((string) ($validated['currency'] ?? config('chat.default_currency', 'IQD'))),
                'title' => $validated['title'] ?? 'Counter Offer',
                'details' => $validated['details'] ?? null,
                'valid_until' => $validated['valid_until'] ?? null,
                'status' => 'pending',
                'parent_offer_id' => $offer->id,
                'is_counter' => true,
            ],
        );

        return ['original' => $offer, 'counter' => $counter];
    }

    /**
     * @return Collection<int, ChatMessage>
     */
    public function thread(ChatConversation $conversation, ChatMessage $root): Collection
    {
        $ids = [$root->id];
        $queue = [$root->id];

        while ($queue !== []) {
            $current = array_shift($queue);
            $children = ChatMessage::query()
                ->where('chat_conversation_id', $conversation->id)
                ->where('message_type', 'offer')
                ->where('meta->parent_offer_id', $current)
                ->pluck('id')
                ->all();

            foreach ($children as $childId) {
                if (! in_array($childId, $ids, true)) {
                    $ids[] = $childId;
                    $queue[] = $childId;
                }
            }
        }

        return ChatMessage::query()
            ->whereIn('id', $ids)
            ->with('sender:id,name,is_admin,school_id,phone_account_type,image')
            ->orderBy('id')
            ->get();
    }

    private function assertCanProcessOffer(ChatMessage $offer, User $actor): void
    {
        if ($offer->message_type !== 'offer') {
            throw ValidationException::withMessages([
                'message' => ['Offer message not found.'],
            ]);
        }

        if ((int) $offer->user_id === (int) $actor->id) {
            throw ValidationException::withMessages([
                'message' => ['You cannot process your own offer.'],
            ]);
        }

        $meta = is_array($offer->meta) ? $offer->meta : [];
        if (($meta['status'] ?? 'pending') !== 'pending') {
            throw ValidationException::withMessages([
                'message' => ['Offer is already processed.'],
            ]);
        }
    }
}
