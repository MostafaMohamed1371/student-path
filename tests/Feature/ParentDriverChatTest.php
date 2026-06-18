<?php

namespace Tests\Feature;

use App\Enums\PhoneAccountType;
use App\Enums\TripType;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\TripHistory;
use App\Models\TripRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParentDriverChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_can_manually_start_parent_driver_chat(): void
    {
        [$school, $parent, $student, $driver, $driverUser, $trip] = $this->seedAcceptedTripContext();

        $tripRequest = TripRequest::query()->create([
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'driver_id' => $driver->id,
            'trip_history_id' => $trip->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($parent);

        $this->postJson('/api/user/chats/start-parent-driver', [
            'trip_request_id' => $tripRequest->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.conversation_type', 'parent_driver')
            ->assertJsonPath('data.trip_request_id', $tripRequest->id);

        $this->postJson('/api/user/chats/start-parent-driver', [
            'driver_id' => $driver->id,
        ])
            ->assertOk()
            ->assertJsonPath('existing', true);
    }

    public function test_driver_can_manually_start_parent_driver_chat(): void
    {
        [$school, $parent, $student, $driver, $driverUser, $trip] = $this->seedAcceptedTripContext();

        TripRequest::query()->create([
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'driver_id' => $driver->id,
            'trip_history_id' => $trip->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($driverUser);

        $this->postJson('/api/user/chats/start-parent-driver', [
            'parent_user_id' => $parent->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.conversation_type', 'parent_driver')
            ->assertJsonPath('data.other_user.type', 'parent');
    }

    public function test_accepting_trip_request_opens_parent_driver_chat(): void
    {
        [$school, $parent, $student, $driver, $driverUser, $trip] = $this->seedAcceptedTripContext();

        $tripRequest = TripRequest::query()->create([
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'driver_id' => $driver->id,
            'trip_history_id' => $trip->id,
            'status' => 'pending',
            'present_type' => 'صباحي',
        ]);

        Sanctum::actingAs($driverUser);

        $this->putJson('/api/trip-requests/'.$tripRequest->id, [
            'status' => 'accepted',
        ])->assertOk();

        $this->assertDatabaseHas('chat_conversations', [
            'conversation_type' => 'parent_driver',
            'user_id' => $parent->id,
            'participant_id' => $driverUser->id,
            'trip_request_id' => $tripRequest->id,
            'status' => 'open',
        ]);

        $chatId = (int) \App\Models\ChatConversation::query()->value('id');

        Sanctum::actingAs($parent);
        $this->getJson('/api/user/chats')
            ->assertOk()
            ->assertJsonPath('data.0.conversation_type', 'parent_driver')
            ->assertJsonPath('data.0.other_user.type', 'driver');

        $this->postJson("/api/user/chats/{$chatId}/messages", [
            'message_type' => 'text',
            'body' => 'Hello driver',
        ])->assertCreated();

        Sanctum::actingAs($driverUser);
        $this->getJson('/api/user/chats')
            ->assertOk()
            ->assertJsonPath('data.0.conversation_type', 'parent_driver')
            ->assertJsonPath('data.0.other_user.type', 'parent');

        $this->getJson("/api/user/chats/{$chatId}/messages")
            ->assertOk()
            ->assertJsonPath('data.0.body', 'Hello driver');
    }

    public function test_school_staff_cannot_see_parent_driver_chat(): void
    {
        [$school, $parent, $student, $driver, $driverUser] = $this->seedAcceptedTripContext();
        $schoolStaff = User::factory()->create([
            'is_admin' => false,
            'school_id' => $school->id,
            'phone_account_type' => PhoneAccountType::School->value,
        ]);

        \App\Models\ChatConversation::query()->create([
            'conversation_type' => 'parent_driver',
            'user_id' => $parent->id,
            'participant_id' => $driverUser->id,
            'school_id' => $school->id,
            'status' => 'open',
        ]);

        Sanctum::actingAs($schoolStaff);

        $this->getJson('/api/user/chats')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * @return array{0: School, 1: User, 2: Student, 3: Driver, 4: User, 5: TripHistory}
     */
    private function seedAcceptedTripContext(): array
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'Chat School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'Campus',
            'status' => 'active',
        ]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Parent G',
            'phone' => '7300000300',
            'status' => 'active',
        ]);

        $parent = User::factory()->create([
            'guardian_id' => $guardian->id,
            'school_id' => $school->id,
            'name' => 'Parent User',
        ]);

        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Child',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000300',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        $driverUser = User::factory()->create(['name' => 'Driver User']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'Ali',
            'father_name' => 'Hassan',
            'grandfather_name' => 'Omar',
            'last_name' => 'Karim',
            'age' => 35,
            'id_card_number' => 'IDC-CHAT',
            'license_number' => 'LIC-CHAT',
            'primary_phone' => '7770000300',
            'emergency_phone' => '7770001300',
            'residential_address' => 'Addr',
            'status' => 'active',
            'shift_period' => 'MORNING',
        ]);

        Bus::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'user_id' => $driverUser->id,
            'name' => 'Bus 1',
            'type' => 'Coach',
            'city' => 'Baghdad',
            'number' => 'B-CHAT',
            'color' => 'Yellow',
            'capacity' => 40,
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'bus_number' => 'B-CHAT',
            'route_title' => 'Morning route',
            'location' => 'Route',
            'students_count' => 0,
            'distance_km' => 1,
            'start_time' => now()->addHour(),
            'status' => 'ACTIVE',
            'students_preview' => [],
        ]);

        return [$school, $parent, $student, $driver, $driverUser, $trip];
    }
}
