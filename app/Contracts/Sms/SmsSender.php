<?php

namespace App\Contracts\Sms;

/**
 * Abstraction for SMS delivery. Swap the binding in AppServiceProvider
 * to integrate Twilio, Unifonic, Msegat, etc.
 */
interface SmsSender
{
    /**
     * @param  array<string, mixed>  $context  Optional metadata (locale, template, etc.)
     */
    public function send(string $phone, string $message, array $context = []): void;
}
