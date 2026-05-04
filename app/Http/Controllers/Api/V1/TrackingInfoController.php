<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * PDF: Realtime via Firebase or WebSocket; channel/topic trip_{id}.
 */
class TrackingInfoController extends Controller
{
    use FormatsParentApiResponse;

    public function __invoke(): JsonResponse
    {
        $prefix = (string) config('realtime.channel_prefix', 'trip_');
        $echo = config('realtime.laravel_echo', []);
        $firebase = config('realtime.firebase', []);

        return $this->parentSuccess([
            'channel_prefix' => $prefix,
            'topic_template' => $prefix.'{tripId}',
            'laravel_echo' => [
                'private_channel_template' => $echo['private_channel_template'] ?? 'trip.{tripHistoryId}',
                'broadcaster' => $echo['broadcaster'] ?? 'null',
                'key' => $echo['key'] ?? null,
                'cluster' => $echo['cluster'] ?? null,
                'ws_host' => $echo['ws_host'] ?? null,
                'ws_port' => $echo['ws_port'] ?? null,
                'auth_endpoint' => url($echo['auth_endpoint'] ?? '/broadcasting/auth'),
            ],
            'firebase' => [
                'project_id' => $firebase['project_id'] ?? null,
                'database_url' => $firebase['database_url'] ?? null,
            ],
            'hint' => 'Use topic '.$prefix.'<tripId> on Firebase, or subscribe to private-trip.<tripId> via Laravel Echo after authenticating at the auth_endpoint.',
        ], 'success');
    }
}
