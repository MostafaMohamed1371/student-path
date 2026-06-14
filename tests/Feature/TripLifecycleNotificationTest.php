<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Guardian;
use App\Models\InAppNotification;
use App\Models\School;
use App\Models\Student;
use App\Models\TripHistory;
use App\Models\TripHistoryStudent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TripLifecycleNotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{school: School, guardian: Guardian, guardianUser: User, student: Student, driverUser: User, driver: Driver}
     */
    private function seedDriverTripFixture(string $tripType = 'MORNING_PICKUP'): array
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'S',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Guardian',
            'phone' => '7300000888',
            'status' => 'active',
        ]);

        $guardianUser = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);

        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Student One',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000888',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
            'latitude' => 33.31,
            'longitude' => 44.36,
        ]);

        $driverUser = User::factory()->create(['phone' => '9647909000888']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'T',
            'age' => 30,
            'id_card_number' => 'IDC-NOTIF',
            'license_number' => 'LIC-NOTIF',
            'primary_phone' => '7770000888',
            'emergency_phone' => '7770001888',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        $formStart = now();
        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => $tripType,
            'bus_number' => 'B-1',
            'route_title' => 'Route',
            'location' => 'L',
            'students_count' => 1,
            'distance_km' => 1,
            'start_time' => $formStart,
            'end_time' => $formStart->copy()->addHour(),
            'status' => 'PRESENT',
        ]);

        TripHistoryStudent::query()->create([
            'trip_history_id' => $trip->id,
            'student_id' => $student->id,
            'sort_order' => 0,
            'status' => 'IDLE',
        ]);

        return [
            'school' => $school,
            'guardian' => $guardian,
            'guardianUser' => $guardianUser,
            'student' => $student,
            'driverUser' => $driverUser,
            'driver' => $driver,
            'trip' => $trip,
        ];
    }

    public function test_pickup_trip_start_notifies_guardian_with_trip_started(): void
    {
        config(['trips.driver_start_early_minutes' => 10, 'trips.driver_start_late_minutes' => 10]);

        $fixture = $this->seedDriverTripFixture('MORNING_PICKUP');
        Sanctum::actingAs($fixture['driverUser']);

        $this->postJson('/api/trips/TRP-'.$fixture['trip']->id.'/start')->assertOk();

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $fixture['guardianUser']->id,
            'title' => 'بدء تحرك الحافلة',
        ]);

        $row = InAppNotification::query()
            ->where('user_id', $fixture['guardianUser']->id)
            ->latest('id')
            ->first();

        $this->assertSame('TRIP_STARTED', $row?->data['type'] ?? null);
    }

    public function test_return_trip_start_uses_return_trip_started_type(): void
    {
        config(['trips.driver_start_early_minutes' => 10, 'trips.driver_start_late_minutes' => 10]);

        $fixture = $this->seedDriverTripFixture('MORNING_RETURN');
        Sanctum::actingAs($fixture['driverUser']);

        $this->postJson('/api/trips/TRP-'.$fixture['trip']->id.'/start')->assertOk();

        $row = InAppNotification::query()
            ->where('user_id', $fixture['guardianUser']->id)
            ->latest('id')
            ->first();

        $this->assertSame('RETURN_TRIP_STARTED', $row?->data['type'] ?? null);
    }

    public function test_student_arrived_notifies_guardian(): void
    {
        $fixture = $this->seedDriverTripFixture();
        $trip = $fixture['trip'];
        $trip->forceFill(['driver_started_at' => now(), 'status' => 'ACTIVE'])->save();

        TripHistoryStudent::query()
            ->where('trip_history_id', $trip->id)
            ->update(['status' => 'ON_WAY']);

        Sanctum::actingAs($fixture['driverUser']);

        $this->putJson('/api/update-status', [
            'student_id' => 'ST-'.str_pad((string) $fixture['student']->id, 3, '0', STR_PAD_LEFT),
            'new_status' => 'ARRIVED',
            'driver_lat' => 33.31,
            'driver_lng' => 44.36,
        ])->assertOk();

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $fixture['guardianUser']->id,
            'title' => 'وصول الحافلة',
        ]);

        $row = InAppNotification::query()
            ->where('user_id', $fixture['guardianUser']->id)
            ->latest('id')
            ->first();

        $this->assertSame('TRIP_STUDENT_ARRIVED', $row?->data['type'] ?? null);
    }

    public function test_student_on_way_notifies_guardian(): void
    {
        $fixture = $this->seedDriverTripFixture();
        $trip = $fixture['trip'];
        $trip->forceFill(['driver_started_at' => now(), 'status' => 'ACTIVE'])->save();

        Sanctum::actingAs($fixture['driverUser']);

        $this->putJson('/api/update-status', [
            'student_id' => 'ST-'.str_pad((string) $fixture['student']->id, 3, '0', STR_PAD_LEFT),
            'new_status' => 'ON_WAY',
            'driver_lat' => 33.31,
            'driver_lng' => 44.36,
        ])->assertOk();

        $row = InAppNotification::query()
            ->where('user_id', $fixture['guardianUser']->id)
            ->latest('id')
            ->first();

        $this->assertSame('TRIP_STUDENT_ON_WAY', $row?->data['type'] ?? null);
    }

    public function test_student_boarded_notifies_guardian(): void
    {
        $fixture = $this->seedDriverTripFixture();
        $trip = $fixture['trip'];
        $trip->forceFill(['driver_started_at' => now(), 'status' => 'ACTIVE'])->save();

        TripHistoryStudent::query()
            ->where('trip_history_id', $trip->id)
            ->update(['status' => 'ARRIVED']);

        Sanctum::actingAs($fixture['driverUser']);

        $this->putJson('/api/update-status', [
            'student_id' => 'ST-'.str_pad((string) $fixture['student']->id, 3, '0', STR_PAD_LEFT),
            'new_status' => 'BOARDED',
            'driver_lat' => 33.31,
            'driver_lng' => 44.36,
        ])->assertOk();

        $row = InAppNotification::query()
            ->where('user_id', $fixture['guardianUser']->id)
            ->latest('id')
            ->first();

        $this->assertSame('TRIP_STUDENT_BOARDED', $row?->data['type'] ?? null);
    }

    public function test_end_trip_notifies_guardian_with_trip_completed(): void
    {
        $fixture = $this->seedDriverTripFixture();
        $trip = $fixture['trip'];
        $trip->forceFill(['driver_started_at' => now(), 'status' => 'ACTIVE'])->save();

        Sanctum::actingAs($fixture['driverUser']);

        $this->putJson('/api/trips/end-trip')->assertOk();

        $row = InAppNotification::query()
            ->where('user_id', $fixture['guardianUser']->id)
            ->where('title', 'انتهاء الرحلة')
            ->latest('id')
            ->first();

        $this->assertSame('TRIP_COMPLETED', $row?->data['type'] ?? null);
    }

    public function test_finalize_trip_notifies_guardian_with_trip_completed(): void
    {
        $fixture = $this->seedDriverTripFixture();
        $trip = $fixture['trip'];
        $trip->forceFill(['driver_started_at' => now(), 'status' => 'ACTIVE'])->save();

        Sanctum::actingAs($fixture['driverUser']);

        $this->postJson('/api/driver/trips/TRP-'.$trip->id.'/finalize', [
            'trip_id' => 'TRP-'.$trip->id,
            'driver_notes' => 'تمت الرحلة',
            'final_lat' => 33.3123,
            'final_lng' => 44.361,
            'end_timestamp' => now()->toIso8601String(),
        ])->assertOk();

        $row = InAppNotification::query()
            ->where('user_id', $fixture['guardianUser']->id)
            ->where('title', 'انتهاء الرحلة')
            ->latest('id')
            ->first();

        $this->assertSame('TRIP_COMPLETED', $row?->data['type'] ?? null);
        $this->assertSame('TRP-'.$trip->id, $row?->data['trip_id'] ?? null);
    }

    public function test_finalize_does_not_duplicate_notification_if_already_completed(): void
    {
        $fixture = $this->seedDriverTripFixture();
        $trip = $fixture['trip'];
        $trip->forceFill([
            'driver_started_at' => now(),
            'status' => 'COMPLETED',
            'end_time' => now(),
        ])->save();

        Sanctum::actingAs($fixture['driverUser']);

        $this->postJson('/api/driver/trips/TRP-'.$trip->id.'/finalize', [
            'trip_id' => 'TRP-'.$trip->id,
            'final_lat' => 33.31,
            'final_lng' => 44.36,
            'end_timestamp' => now()->toIso8601String(),
        ])->assertOk();

        $this->assertSame(
            0,
            InAppNotification::query()
                ->where('user_id', $fixture['guardianUser']->id)
                ->where('title', 'انتهاء الرحلة')
                ->count()
        );
    }
}
