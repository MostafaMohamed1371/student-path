<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserBusServicesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_user_profile_endpoint_returns_contract_shape(): void
    {
        $user = User::factory()->create([
            'name' => 'Driver One',
            'phone' => '9647701234567',
            'city' => 'Baghdad',
            'licence_number' => 'ABC123',
            'votes' => 3,
            'rate' => 4.5,
            'is_verified' => true,
            'image' => null,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/user/profile')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Driver One')
            ->assertJsonPath('data.licenceNumber', 'ABC123')
            ->assertJsonPath('data.isVerified', true)
            ->assertJsonPath('data.image', null)
            ->assertJsonStructure(['success', 'data', 'msg']);
    }

    public function test_change_language_endpoint_updates_preferred_language(): void
    {
        $user = User::factory()->create(['preferred_language' => 'en']);

        Sanctum::actingAs($user);

        $this->postJson('/api/user/language', ['language' => 'ar'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('msg', 'language updated successfully');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'preferred_language' => 'ar',
        ]);
    }

    public function test_bus_crud_flow_for_authenticated_user(): void
    {
        $user = User::factory()->create(['phone' => '9647901234567']);
        $driver = Driver::query()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/bus/my-bus')->assertStatus(404);

        $this->postJson('/api/bus/my-bus', [
            'busName' => 'Toyota Coaster',
            'busType' => 'School Bus',
            'busCity' => 'Baghdad',
            'busNumber' => 'BGD-1234',
            'busColor' => '#facc15',
            'busCapacity' => 22,
            'fuelType' => 'Diesel',
            'busStatus' => 'Excellent',
            'busAnnualStatus' => true,
            'busInsurance' => true,
            'busModelYear' => 2021,
            'busAcStatus' => 'no',
        ])->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.busName', 'Toyota Coaster')
            ->assertJsonPath('data.busModelYear', 2021)
            ->assertJsonPath('data.busAcStatus', 'no');

        $this->putJson('/api/bus/my-bus', [
            'busStatus' => 'Good',
            'busCapacity' => 24,
            'busModelYear' => 2022,
            'busAcStatus' => 'yes',
        ])->assertOk()
            ->assertJsonPath('data.busStatus', 'Good')
            ->assertJsonPath('data.busCapacity', 24)
            ->assertJsonPath('data.busModelYear', 2022)
            ->assertJsonPath('data.busAcStatus', 'yes');

        $this->getJson('/api/bus/my-bus')
            ->assertOk()
            ->assertJsonPath('data.busStatus', 'Good');

        $this->deleteJson('/api/bus/my-bus')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('buses', ['driver_id' => $driver->id]);
    }
}
