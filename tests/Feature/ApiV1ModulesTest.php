<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\IraqLocationsSeeder;
use Database\Seeders\LocationMetaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiV1ModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_v1_wallet_and_meta_endpoints(): void
    {
        $this->seed(IraqLocationsSeeder::class);
        $this->seed(LocationMetaSeeder::class);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/wallet')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.balance', '0.00');

        $this->postJson('/api/wallet/recharge', ['amount' => 10.5])
            ->assertCreated()
            ->assertJsonPath('data.balance', '10.50');

        $this->getJson('/api/wallet/transactions')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/meta/grades')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/locations/districts')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/trip-tracking/config')
            ->assertOk()
            ->assertJsonPath('data.channel_prefix', 'trip_');
    }

    public function test_v1_places_without_key_returns_503(): void
    {
        config(['google.places_api_key' => '']);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/places/autocomplete?input=bag')
            ->assertStatus(503);
    }

    public function test_v1_places_autocomplete_maps_google_request_denied_to_503(): void
    {
        config(['google.places_api_key' => 'test-key']);

        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'predictions' => [],
                'status' => 'REQUEST_DENIED',
                'error_message' => 'The provided API key is invalid.',
            ], 200),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/places/autocomplete?input=test')
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.google_status', 'REQUEST_DENIED');
    }

    public function test_v1_places_autocomplete_ok_returns_wrapped_payload(): void
    {
        config(['google.places_api_key' => 'test-key']);

        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'predictions' => [
                    ['description' => 'Baghdad, Iraq', 'place_id' => 'ChIJ...'],
                ],
                'status' => 'OK',
            ], 200),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/places/autocomplete?input=bag')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'OK')
            ->assertJsonPath('data.predictions.0.place_id', 'ChIJ...');
    }

    public function test_v1_places_details_not_found_returns_404(): void
    {
        config(['google.places_api_key' => 'test-key']);

        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'NOT_FOUND',
            ], 200),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/places/ChIJ_missing')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.google_status', 'NOT_FOUND');
    }

    public function test_v1_directions_without_key_returns_503(): void
    {
        config([
            'google.places_api_key' => '',
            'google.directions_api_key' => '',
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/directions/flat', [
            'origin' => ['latitude' => 33.3152, 'longitude' => 44.3661],
            'destination' => ['latitude' => 33.3120, 'longitude' => 44.3620],
        ])->assertStatus(503);
    }

    public function test_v1_directions_flat_returns_mobile_friendly_payload(): void
    {
        config(['google.directions_api_key' => 'test-key']);

        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'routes' => [[
                    'overview_polyline' => ['points' => 'abc123'],
                    'bounds' => [
                        'northeast' => ['lat' => 33.32, 'lng' => 44.37],
                        'southwest' => ['lat' => 33.31, 'lng' => 44.36],
                    ],
                    'legs' => [[
                        'start_address' => 'Driver location',
                        'end_address' => 'Student pickup',
                        'distance' => ['text' => '1.2 km', 'value' => 1200],
                        'duration' => ['text' => '4 mins', 'value' => 240],
                        'steps' => [[
                            'html_instructions' => 'Head <b>east</b>',
                            'distance' => ['text' => '0.5 km', 'value' => 500],
                            'duration' => ['text' => '2 mins', 'value' => 120],
                            'start_location' => ['lat' => 33.3152, 'lng' => 44.3661],
                            'end_location' => ['lat' => 33.3160, 'lng' => 44.3670],
                            'polyline' => ['points' => 'step-polyline'],
                            'travel_mode' => 'DRIVING',
                        ]],
                    ]],
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/directions/flat', [
            'origin' => ['latitude' => 33.3152, 'longitude' => 44.3661],
            'destination' => ['latitude' => 33.3120, 'longitude' => 44.3620],
            'mode' => 'driving',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.route_found', true)
            ->assertJsonPath('data.distance.value', 1200)
            ->assertJsonPath('data.duration.value', 240)
            ->assertJsonPath('data.overview_polyline', 'abc123')
            ->assertJsonPath('data.steps.0.instruction', 'Head east');
    }

    public function test_v1_directions_request_denied_returns_503(): void
    {
        config(['google.directions_api_key' => 'test-key']);

        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'REQUEST_DENIED',
                'error_message' => 'Directions API not enabled.',
            ], 200),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/directions/flat', [
            'origin' => ['latitude' => 33.3152, 'longitude' => 44.3661],
            'destination' => ['latitude' => 33.3120, 'longitude' => 44.3620],
        ])
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.google_status', 'REQUEST_DENIED');
    }

    public function test_v1_profile_put_delegates(): void
    {
        $user = User::factory()->create(['name' => 'Old']);
        Sanctum::actingAs($user);

        $this->putJson('/api/profile', ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');
    }
}
