<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DashboardGeocodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_reverse_geocode_for_school_form(): void
    {
        config(['google.geocoding_api_key' => 'test-key']);

        Http::fake([
            'maps.googleapis.com/maps/api/geocode/json*' => Http::response([
                'status' => 'OK',
                'results' => [[
                    'formatted_address' => 'Example Road, Karrada, Baghdad, Iraq',
                    'address_components' => [
                        [
                            'long_name' => 'Baghdad',
                            'types' => ['administrative_area_level_1'],
                        ],
                        [
                            'long_name' => 'Karrada',
                            'types' => ['sublocality'],
                        ],
                    ],
                ]],
            ]),
            'nominatim.openstreetmap.org/reverse*' => Http::response([
                'display_name' => 'Fallback Road, Baghdad',
                'address' => [
                    'road' => 'Fallback Road',
                    'state' => 'Baghdad',
                    'suburb' => 'Karrada',
                ],
            ]),
        ]);

        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user);

        $this->getJson(route('dashboard.geocode.reverse', [
            'latitude' => 33.3128,
            'longitude' => 44.3615,
        ]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.address', 'Example Road, Karrada, Baghdad, Iraq')
            ->assertJsonPath('data.province', 'Baghdad')
            ->assertJsonPath('data.district', 'Karrada');
    }

    public function test_reverse_geocode_falls_back_to_nominatim_when_google_unavailable(): void
    {
        config(['google.geocoding_api_key' => '']);

        Http::fake([
            'nominatim.openstreetmap.org/reverse*' => Http::response([
                'display_name' => 'Example Road, Baghdad',
                'address' => [
                    'road' => 'Example Road',
                    'state' => 'Baghdad',
                    'suburb' => 'Karrada',
                ],
            ]),
        ]);

        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user);

        $this->getJson(route('dashboard.geocode.reverse', [
            'latitude' => 33.3128,
            'longitude' => 44.3615,
        ]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.address', 'Example Road, Karrada');
    }
}
