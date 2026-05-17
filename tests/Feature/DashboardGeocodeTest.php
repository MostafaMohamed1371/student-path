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
            ->assertJsonPath('data.address', 'Example Road, Karrada')
            ->assertJsonPath('data.province', 'Baghdad')
            ->assertJsonPath('data.district', 'Karrada');
    }
}
