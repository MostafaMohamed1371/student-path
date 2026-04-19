<?php

namespace App\Services\Sms;

use App\Contracts\Sms\SmsSender;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Standing Tech Gateway v4 — SMS / WhatsApp send.
 *
 * @see https://gateway.standingtech.com/api/v4/sms/send
 */
final class StandingTechSmsSender implements SmsSender
{
    public function __construct(
        private readonly StandingTechRecipientFormatter $recipientFormatter,
    ) {}

    public function send(string $phone, string $message, array $context = []): void
    {
        $token = (string) config('standingtech.bearer_token');
        if ($token === '') {
            throw new RuntimeException('Standing Tech is enabled but STANDINGTECH_BEARER_TOKEN is empty.');
        }

        $url = (string) config('standingtech.url');
        $recipient = $this->recipientFormatter->format($phone);

        $payload = [
            'recipient' => $recipient,
            'sender_id' => (string) config('standingtech.sender_id'),
            'type' => (string) config('standingtech.type'),
            'message' => $message,
        ];

        $lang = (string) config('standingtech.lang', '');
        if ($lang !== '') {
            $payload['lang'] = $lang;
        }

        // Forward optional metadata for logging / future template routing (not sent unless API supports it).
        if ($context !== []) {
            Log::debug('Standing Tech SMS context', ['context' => $context]);
        }

        $timeout = (int) config('standingtech.timeout', 15);

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->withToken($token)
            ->post($url, $payload);

        if (! $response->successful()) {
            Log::error('Standing Tech SMS HTTP error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException(
                'Standing Tech SMS request failed (HTTP '.$response->status().').'
            );
        }

        $data = $response->json();
        if (is_array($data) && array_key_exists('success', $data) && $data['success'] === false) {
            $msg = is_string($data['message'] ?? null) ? $data['message'] : 'Unknown error';
            Log::error('Standing Tech SMS logical failure', ['response' => $data]);

            throw new RuntimeException('Standing Tech SMS failed: '.$msg);
        }
    }
}
