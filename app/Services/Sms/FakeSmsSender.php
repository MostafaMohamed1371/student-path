<?php

namespace App\Services\Sms;

use App\Contracts\Sms\SmsSender;
use Illuminate\Support\Facades\Log;

/**
 * Logs-only SMS implementation (used when STANDINGTECH_MOCK=true or no provider is wired).
 *
 * For production, set STANDINGTECH_MOCK=false and configure Standing Tech in .env.
 */
final class FakeSmsSender implements SmsSender
{
    public function send(string $phone, string $message, array $context = []): void
    {
        // Integrate real SMS provider here (HTTP client to vendor, templates, Arabic copy, etc.).
        Log::info('SMS (fake)', [
            'phone' => $phone,
            'message' => $message,
            'context' => $context,
        ]);
    }
}
