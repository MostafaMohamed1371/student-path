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
                'tracking_path_template' => (string) config('trips.location_firebase_path', 'trips/{tripId}/tracking'),
                'location_path_template' => (string) config('trips.location_firebase_path', 'trips/{tripId}/tracking').'/location',
            ],
            'location_post_endpoint' => url('/api/driver/trips/{tripId}/location'),
            'location_get_endpoint' => url('/api/trips/{tripId}/tracking'),
            'topic_subscribe_endpoint' => url('/api/trip-tracking/topics/subscribe'),
            'hint' => 'Subscribe server-side via POST '.$prefix.'<tripId> at topic_subscribe_endpoint (trip_id = TRP-{id} or numeric id), or client-side on Firebase topic '.$prefix.'<tripHistoryId>. Laravel Echo: private-trip.<tripHistoryId> at auth_endpoint.',
        ], 'success');
    }
}
