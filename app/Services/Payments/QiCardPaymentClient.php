<?php

namespace App\Services\Payments;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class QiCardPaymentClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createPayment(array $payload): array
    {
        $response = $this->request()->post($this->apiHost().'/payment', $payload);

        return $this->decode($response);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPaymentStatus(string $paymentId): array
    {
        $response = $this->request()->get($this->apiHost().'/payment/'.rawurlencode($paymentId).'/status');

        return $this->decode($response);
    }

    private function request(): PendingRequest
    {
        return Http::timeout(config('qicard.timeout'))
            ->withBasicAuth(config('qicard.username'), config('qicard.password'))
            ->withHeaders([
                'X-Terminal-Id' => config('qicard.terminal_id'),
                'Accept' => 'application/json',
            ]);
    }

    private function apiHost(): string
    {
        return config('qicard.api_host');
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        $json = $response->json();
        if (is_array($json)) {
            return $json;
        }

        return [
            '_http_status' => $response->status(),
            '_raw' => $response->body(),
        ];
    }
}
