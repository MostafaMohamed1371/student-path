<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\TripHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TripHistoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_trips_history_sorted_desc_with_expected_shape(): void
    {
        $school = $this->makeSchool('School A');
        $user = User::factory()->create([
            'phone' => '9647901111111',
            'school_id' => $school->id,
            'is_admin' => false,
        ]);
        Sanctum::actingAs($user);

        TripHistory::query()->create([
            'school_id' => $school->id,
            'bus_number' => '40',
            'route_title' => null,
            'location' => null,
            'students_count' => 2,
            'distance_km' => 9.5,
            'start_time' => '2026-04-28 07:30:00',
            'end_time' => '2026-04-28 08:15:00',
            'status' => 'PRESENT',
            'note' => null,
            'students_preview' => [['id' => 's1', 'name' => 'A']],
        ]);

        TripHistory::query()->create([
            'school_id' => $school->id,
            'bus_number' => '50',
            'route_title' => 'Evening Route',
            'location' => 'Baghdad',
            'students_count' => 1,
            'distance_km' => 3,
            'start_time' => '2026-04-28 18:30:00',
            'end_time' => '2026-04-28 19:00:00',
            'status' => 'ABSENT',
            'note' => 'Late',
            'students_preview' => [],
        ]);

        $this->getJson('/api/trips/history')
            ->assertOk()
            ->assertJsonPath('status', 200)
            ->assertJsonPath('message', 'Success')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.bus_number', '50')
            ->assertJsonPath('data.0.period', 'EVENING')
            ->assertJsonPath('data.1.period', 'MORNING')
            ->assertJsonPath('data.1.route_title', '')
            ->assertJsonPath('data.1.location', '')
            ->assertJsonPath('data.1.note', '')
            ->assertJsonPath('data.1.students_preview.0.id', 's1');
    }

    public function test_it_filters_by_period_and_date_range(): void
    {
        $school = $this->makeSchool('School B');
        $user = User::factory()->create([
            'phone' => '9647902222222',
            'school_id' => $school->id,
            'is_admin' => false,
        ]);
        Sanctum::actingAs($user);

        TripHistory::query()->create([
            'school_id' => $school->id,
            'start_time' => '2026-04-27 07:30:00',
            'end_time' => '2026-04-27 08:00:00',
            'status' => 'PRESENT',
        ]);
        TripHistory::query()->create([
            'school_id' => $school->id,
            'start_time' => '2026-04-28 18:30:00',
            'end_time' => '2026-04-28 19:00:00',
            'status' => 'PRESENT',
        ]);

        $this->getJson('/api/trips/history?period=EVENING&from=2026-04-28&to=2026-04-28')
            ->assertOk()
            ->assertJsonPath('status', 200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.period', 'EVENING')
            ->assertJsonPath('data.0.date', '2026-04-28');
    }

    public function test_it_returns_400_for_invalid_period(): void
    {
        $school = $this->makeSchool('School C');
        $user = User::factory()->create([
            'phone' => '9647903333333',
            'school_id' => $school->id,
            'is_admin' => false,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/trips/history?period=NIGHT')
            ->assertStatus(400)
            ->assertJsonPath('status', 400)
            ->assertJsonPath('message', 'Invalid period value. Expected MORNING or EVENING.')
            ->assertJsonPath('data', null);
    }

    public function test_it_returns_no_data_message_when_empty(): void
    {
        $school = $this->makeSchool('School D');
        $user = User::factory()->create([
            'phone' => '9647904444444',
            'school_id' => $school->id,
            'is_admin' => false,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/trips/history')
            ->assertOk()
            ->assertJsonPath('status', 200)
            ->assertJsonPath('message', 'No trips found')
            ->assertJsonPath('data', []);
    }

    public function test_admin_can_crud_trips_resource_and_staff_cannot_write(): void
    {
        $school = $this->makeSchool('School E');
        $admin = User::factory()->create([
            'phone' => '9647905555555',
            'is_admin' => true,
        ]);
        $staff = User::factory()->create([
            'phone' => '9647906666666',
            'school_id' => $school->id,
            'is_admin' => false,
        ]);

        Sanctum::actingAs($admin);
        $create = $this->postJson('/api/trips', [
            'school_id' => $school->id,
            'bus_number' => '77',
            'route_title' => 'R',
            'location' => 'Baghdad',
            'students_count' => 3,
            'distance_km' => 10,
            'start_time' => '2026-04-28 07:30:00',
            'end_time' => '2026-04-28 08:00:00',
            'status' => 'PRESENT',
            'note' => 'ok',
            'students_preview' => [],
        ])->assertStatus(201)->assertJsonPath('success', true);

        $tripId = (int) $create->json('data.id');
        $this->getJson('/api/trips')->assertOk()->assertJsonPath('success', true);
        $this->getJson('/api/trips/'.$tripId)->assertOk()->assertJsonPath('success', true);
        $this->putJson('/api/trips/'.$tripId, ['status' => 'ABSENT'])->assertOk()->assertJsonPath('data.status', 'ABSENT');

        Sanctum::actingAs($staff);
        $this->postJson('/api/trips', [
            'school_id' => $school->id,
            'bus_number' => '88',
            'students_count' => 0,
            'distance_km' => 1,
            'start_time' => '2026-04-28 09:00:00',
            'status' => 'PRESENT',
        ])->assertStatus(403);
        $this->putJson('/api/trips/'.$tripId, ['status' => 'CANCELLED'])->assertStatus(403);
        $this->deleteJson('/api/trips/'.$tripId)->assertStatus(403);

        Sanctum::actingAs($admin);
        $this->deleteJson('/api/trips/'.$tripId)->assertOk()->assertJsonPath('success', true);
    }

    private function makeSchool(string $nameEn): School
    {
        return School::query()->create([
            'name_ar' => $nameEn,
            'name_en' => $nameEn,
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);
    }
}

