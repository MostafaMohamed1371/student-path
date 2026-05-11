<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Guardian;
use App\Models\HomeLocation;
use App\Models\School;
use App\Models\SosAlert;
use App\Models\Student;
use App\Models\TripHistory;
use App\Models\TripHistoryStudent;
use App\Models\TripRequest;
use App\Models\User;
use App\Services\TransportLines\TransportDriverCardBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverTripModuleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_current_trip_and_update_status_sequence(): void
    {
        $school = School::query()->create([
            'name_ar' => 'TSchool',
            'name_en' => 'TSchool',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G',
            'phone' => '7300000991',
            'status' => 'active',
        ]);

        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Test Student',
            'gender' => 'male',
            'grade' => 'G1',
            'student_phone' => '7400000991',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'Area',
            'nearest_landmark' => 'LM',
            'latitude' => 33.312345,
            'longitude' => 44.361234,
            'status' => 'active',
        ]);

        $driverUser = User::factory()->create(['phone' => '9647909000991']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'T',
            'age' => 30,
            'id_card_number' => 'IDC-DT',
            'license_number' => 'LIC-DT',
            'primary_phone' => '7770000991',
            'emergency_phone' => '7770001991',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => 'MORNING_PICKUP',
            'bus_number' => 'B-99',
            'route_title' => 'Route',
            'location' => 'Loc',
            'students_count' => 1,
            'distance_km' => 1,
            'start_time' => now()->subHour(),
            'end_time' => null,
            'status' => 'ACTIVE',
            'students_preview' => [['id' => (string) $student->id, 'name' => $student->full_name]],
        ]);

        TripHistoryStudent::query()->create([
            'trip_history_id' => $trip->id,
            'student_id' => $student->id,
            'sort_order' => 0,
            'status' => 'IDLE',
        ]);

        Sanctum::actingAs($driverUser);

        $sid = 'ST-'.str_pad((string) $student->id, 3, '0', STR_PAD_LEFT);

        $this->getJson('/api/trips/current-trip')
            ->assertOk()
            ->assertJsonPath('data.trip_id', 'TRP-'.$trip->id)
            ->assertJsonPath('data.trip_type', 'MORNING_PICKUP')
            ->assertJsonPath('data.pending_students', 1)
            ->assertJsonPath('data.boarding_students', 0)
            ->assertJsonPath('data.current_active_student_id', $sid)
            ->assertJsonPath('data.students.0.status', 'IDLE')
            ->assertJsonPath('data.students.0.can_action', true);

        $this->putJson('/api/update-status', [
            'student_id' => $sid,
            'new_status' => 'ON_WAY',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->putJson('/api/update-status', [
            'student_id' => $sid,
            'new_status' => 'ARRIVED',
            'driver_lat' => 33.3124,
            'driver_lng' => 44.361234,
        ])
            ->assertOk()
            ->assertJsonPath('data.waiting_min', 5)
            ->assertJsonPath('data.less_than_50_meter', true)
            ->assertJsonPath('msg', 'تم تحديث الحالة بنجاح وبدأ وقت الانتظار');

        $this->putJson('/api/update-status', [
            'student_id' => $sid,
            'new_status' => 'BOARDED',
        ])
            ->assertOk();

        $this->assertDatabaseHas('trip_history_students', [
            'trip_history_id' => $trip->id,
            'student_id' => $student->id,
            'status' => 'BOARDED',
        ]);
    }

    public function test_driver_overview_endpoint_returns_dashboard_metrics(): void
    {
        $school = School::query()->create([
            'name_ar' => 'OV',
            'name_en' => 'OV',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);

        $driverUser = User::factory()->create(['phone' => '9647909000914']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'OV',
            'age' => 30,
            'id_card_number' => 'IDC-OV',
            'license_number' => 'LIC-OV',
            'primary_phone' => '7770000914',
            'emergency_phone' => '7770001914',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        Bus::query()->create([
            'user_id' => $driverUser->id,
            'driver_id' => $driver->id,
            'name' => 'B',
            'number' => 'B-OV',
            'type' => 'Bus',
            'city' => 'Baghdad',
            'capacity' => 20,
            'color' => 'yellow',
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => 'MORNING_PICKUP',
            'bus_number' => 'B-OV',
            'route_title' => 'OV',
            'location' => 'L',
            'students_count' => 2,
            'distance_km' => 2,
            'start_time' => now()->subMinutes(30),
            'end_time' => null,
            'status' => 'ACTIVE',
            'students_preview' => [],
        ]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G OV',
            'phone' => '7300000914',
            'status' => 'active',
        ]);
        $studentA = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Student A',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000914',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);
        $studentB = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Student B',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000915',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);

        TripHistoryStudent::query()->create([
            'trip_history_id' => $trip->id,
            'student_id' => $studentA->id,
            'sort_order' => 0,
            'status' => 'BOARDED',
        ]);
        TripHistoryStudent::query()->create([
            'trip_history_id' => $trip->id,
            'student_id' => $studentB->id,
            'sort_order' => 1,
            'status' => 'ON_WAY',
        ]);

        Sanctum::actingAs($driverUser);

        $this->getJson('/api/driver-overview')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('msg', 'Retrieve driver dashboard')
            ->assertJsonPath('data.all_students', 2)
            ->assertJsonPath('data.all_available_seats', 19)
            ->assertJsonPath('data.all_unavailable_seats', 1)
            ->assertJsonPath('data.available_morning_seats', 18)
            ->assertJsonPath('data.available_evening_seats', 20);
    }

    public function test_arrived_rejected_when_driver_too_far(): void
    {
        $school = School::query()->create([
            'name_ar' => 'TS2',
            'name_en' => 'TS2',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G2',
            'phone' => '7300000992',
            'status' => 'active',
        ]);

        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Far Student',
            'gender' => 'male',
            'grade' => 'G1',
            'student_phone' => '7400000992',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'A',
            'nearest_landmark' => 'B',
            'latitude' => 33.312345,
            'longitude' => 44.361234,
            'status' => 'active',
        ]);

        $driverUser = User::factory()->create(['phone' => '9647909000992']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'F',
            'age' => 30,
            'id_card_number' => 'IDC-DF',
            'license_number' => 'LIC-DF',
            'primary_phone' => '7770000992',
            'emergency_phone' => '7770001992',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'bus_number' => 'B',
            'route_title' => 'R',
            'location' => 'L',
            'students_count' => 1,
            'distance_km' => 1,
            'start_time' => now()->subHour(),
            'end_time' => null,
            'status' => 'ACTIVE',
            'students_preview' => [['id' => (string) $student->id, 'name' => $student->full_name]],
        ]);

        TripHistoryStudent::query()->create([
            'trip_history_id' => $trip->id,
            'student_id' => $student->id,
            'sort_order' => 0,
            'status' => 'ON_WAY',
        ]);

        Sanctum::actingAs($driverUser);

        $sid = 'ST-'.str_pad((string) $student->id, 3, '0', STR_PAD_LEFT);

        $this->putJson('/api/update-status', [
            'student_id' => $sid,
            'new_status' => 'ARRIVED',
            'driver_lat' => 33.4,
            'driver_lng' => 44.5,
        ])
            ->assertStatus(422)
            ->assertJsonPath('msg', 'عذراً، أنت بعيد جداً عن موقع الطالب')
            ->assertJsonPath('data.less_than_50_meter', false)
            ->assertJsonPath('data.waiting_min', 0);
    }

    public function test_end_trip_sets_end_time_and_completed_status(): void
    {
        $school = School::query()->create([
            'name_ar' => 'TEnd',
            'name_en' => 'TEnd',
            'province' => 'P',
            'district' => '1',
            'address' => 'A',
            'status' => 'active',
        ]);

        $driverUser = User::factory()->create(['phone' => '9647909000993']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'E',
            'father_name' => 'E',
            'grandfather_name' => 'E',
            'last_name' => 'T',
            'age' => 30,
            'id_card_number' => 'IDC-ET',
            'license_number' => 'LIC-ET',
            'primary_phone' => '7770000993',
            'emergency_phone' => '7770001993',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'bus_number' => 'B',
            'route_title' => 'R',
            'location' => 'L',
            'students_count' => 0,
            'distance_km' => 0,
            'start_time' => now()->subMinutes(30),
            'end_time' => null,
            'status' => 'ACTIVE',
            'students_preview' => [],
        ]);

        Sanctum::actingAs($driverUser);

        $this->putJson('/api/trips/end-trip')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('msg', 'تم إنهاء الرحلة بنجاح')
            ->assertJsonPath('data.trip_id', 'TRP-'.$trip->id)
            ->assertJsonPath('data.status', 'COMPLETED');

        $trip->refresh();
        $this->assertSame('COMPLETED', $trip->status);
        $this->assertNotNull($trip->end_time);

        $this->putJson('/api/trips/end-trip')
            ->assertStatus(422)
            ->assertJsonPath('msg', 'No active trip.');
    }

    public function test_driver_get_trip_details_by_public_id(): void
    {
        $school = School::query()->create([
            'name_ar' => 'إعدادية بغداد للبنين',
            'name_en' => 'Baghdad Prep',
            'province' => 'بغداد',
            'district' => 'حي المنصور',
            'address' => 'A',
            'status' => 'active',
        ]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G',
            'phone' => '7300000994',
            'status' => 'active',
        ]);

        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'رودينا علي الدين',
            'gender' => 'female',
            'grade' => 'الصف الرابع',
            'student_phone' => '7400000994',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'النقطة الأولى',
            'nearest_landmark' => 'شارع الربيع',
            'status' => 'active',
        ]);

        $driverUser = User::factory()->create(['phone' => '9647909000994']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'TD',
            'age' => 30,
            'id_card_number' => 'IDC-TD',
            'license_number' => 'LIC-TD',
            'primary_phone' => '7770000994',
            'emergency_phone' => '7770001994',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => 'MORNING_PICKUP',
            'bus_number' => 'B',
            'route_title' => 'رحلة الصباح - إعدادية بغداد للبنين',
            'location' => 'حي المنصور، بغداد',
            'students_count' => 1,
            'distance_km' => 12,
            'start_time' => now()->setDate(2026, 10, 12)->setTime(6, 45, 0),
            'end_time' => null,
            'status' => 'ACTIVE',
            'students_preview' => [['id' => (string) $student->id, 'name' => $student->full_name]],
        ]);

        TripHistoryStudent::query()->create([
            'trip_history_id' => $trip->id,
            'student_id' => $student->id,
            'sort_order' => 0,
            'status' => 'IDLE',
        ]);

        Sanctum::actingAs($driverUser);

        $publicId = 'TRP-'.$trip->id;

        $this->getJson('/api/driver/trips/'.$publicId)
            ->assertOk()
            ->assertJsonPath('msg', 'تم جلب بيانات الرحلة')
            ->assertJsonPath('data.id', $publicId)
            ->assertJsonPath('data.title', 'رحلة الصباح - إعدادية بغداد للبنين')
            ->assertJsonPath('data.location', 'حي المنصور، بغداد')
            ->assertJsonPath('data.distance_km', 12)
            ->assertJsonPath('data.distance_in_km', '12 كم')
            ->assertJsonPath('data.students_number', '1 طالب')
            ->assertJsonPath('data.students.0.name', 'رودينا علي الدين')
            ->assertJsonPath('data.students.0.grade', 'الصف الرابع')
            ->assertJsonPath('data.students.0.pickup_point', 'النقطة الأولى - شارع الربيع');

        $parent = User::factory()->create();
        Sanctum::actingAs($parent);
        $this->getJson('/api/driver/trips/'.$publicId)->assertStatus(403);
    }

    public function test_driver_trip_details_handles_null_distance_and_location_fallback(): void
    {
        $school = School::query()->create([
            'name_ar' => 'مدرسة',
            'name_en' => 'School X',
            'province' => 'بغداد',
            'district' => 'الكرادة',
            'address' => '',
            'status' => 'active',
        ]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'G',
            'phone' => '7300000888',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'طالب',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000888',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'منطقة',
            'nearest_landmark' => 'معلم',
            'status' => 'active',
        ]);

        $driverUser = User::factory()->create(['phone' => '9647909000888']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'W',
            'age' => 30,
            'id_card_number' => 'IDC-NULL-D',
            'license_number' => 'LIC-NULL-D',
            'primary_phone' => '7770000888',
            'emergency_phone' => '7770001888',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => 'MORNING_PICKUP',
            'bus_number' => 'B-NULL',
            'route_title' => 'عنوان',
            'location' => null,
            'students_count' => 1,
            'distance_km' => null,
            'start_time' => now()->addHour(),
            'end_time' => null,
            'status' => 'ACTIVE',
            'students_preview' => [['id' => (string) $student->id, 'name' => $student->full_name]],
        ]);

        TripHistoryStudent::query()->create([
            'trip_history_id' => $trip->id,
            'student_id' => $student->id,
            'sort_order' => 0,
            'status' => 'IDLE',
        ]);

        Sanctum::actingAs($driverUser);

        $this->getJson('/api/driver/trips/TRP-'.$trip->id)
            ->assertOk()
            ->assertJsonPath('data.distance_km', null)
            ->assertJsonPath('data.distance_in_km', null)
            ->assertJsonPath('data.location', 'الكرادة، بغداد');
    }

    public function test_get_scheduled_trips_today_for_driver(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'S',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $driverUser = User::factory()->create(['phone' => '9647909000995']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'Sch',
            'age' => 30,
            'id_card_number' => 'IDC-SCH',
            'license_number' => 'LIC-SCH',
            'primary_phone' => '7770000995',
            'emergency_phone' => '7770001995',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        Bus::query()->create([
            'user_id' => $driverUser->id,
            'driver_id' => $driver->id,
            'name' => 'B',
            'number' => 'B-1',
            'type' => 'Bus',
            'city' => 'Baghdad',
            'capacity' => 20,
            'color' => 'yellow',
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        $today = now()->startOfDay()->addHours(10);

        $tMorningOut = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => 'MORNING_PICKUP',
            'bus_number' => 'B-1',
            'route_title' => null,
            'location' => 'L',
            'students_count' => 0,
            'distance_km' => 0,
            'start_time' => $today->copy()->setTime(7, 0, 0),
            'end_time' => null,
            'status' => 'ACTIVE',
            'students_preview' => [],
        ]);

        $tMorningRet = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => 'MORNING_RETURN',
            'bus_number' => 'B-1',
            'route_title' => null,
            'location' => 'L',
            'students_count' => 0,
            'distance_km' => 0,
            'start_time' => $today->copy()->setTime(12, 30, 0),
            'end_time' => null,
            'status' => 'ACTIVE',
            'students_preview' => [],
        ]);

        $this->travelTo($today->copy()->setTime(8, 0, 0));

        Sanctum::actingAs($driverUser);

        $this->getJson('/api/scheduled-trips')
            ->assertOk()
            ->assertJsonPath('msg', 'all trips')
            ->assertJsonPath('pagination.total', 2)
            ->assertJsonPath('data.0.id', $tMorningOut->id)
            ->assertJsonPath('data.0.title', 'رحلة الصباح - ذهاب')
            ->assertJsonPath('data.0.type', 'MORNING_PICKUP')
            ->assertJsonPath('data.0.status', 'ongoing')
            ->assertJsonPath('data.1.id', $tMorningRet->id)
            ->assertJsonPath('data.1.title', 'رحلة الصباح - عودة')
            ->assertJsonPath('data.1.status', 'upcoming');

        $this->travelBack();
    }

    public function test_current_trip_supports_morning_evening_filter(): void
    {
        $school = School::query()->create([
            'name_ar' => 'FS',
            'name_en' => 'FS',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $driverUser = User::factory()->create(['phone' => '9647909000996']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'F',
            'age' => 30,
            'id_card_number' => 'IDC-FLT',
            'license_number' => 'LIC-FLT',
            'primary_phone' => '7770000996',
            'emergency_phone' => '7770001996',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => 'MORNING_PICKUP',
            'bus_number' => 'B',
            'route_title' => 'Morning',
            'location' => 'L',
            'students_count' => 0,
            'distance_km' => 0,
            'start_time' => now()->subHour(),
            'end_time' => null,
            'status' => 'ACTIVE',
            'students_preview' => [],
        ]);

        TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => 'EVENING_PICKUP',
            'bus_number' => 'B',
            'route_title' => 'Evening',
            'location' => 'L',
            'students_count' => 0,
            'distance_km' => 0,
            'start_time' => now()->subMinutes(30),
            'end_time' => null,
            'status' => 'ACTIVE',
            'students_preview' => [],
        ]);

        Sanctum::actingAs($driverUser);

        $this->getJson('/api/trips/current-trip?shift_period=MORNING')
            ->assertOk()
            ->assertJsonPath('data.trip_type', 'MORNING_PICKUP');

        $this->getJson('/api/trips/current-trip?'.http_build_query(['shift_period' => 'مسائي']))
            ->assertOk()
            ->assertJsonPath('data.trip_type', 'EVENING_PICKUP');

        $this->getJson('/api/trips/current-trip?shift_period=invalid')
            ->assertStatus(422);
    }

    public function test_send_delay_alert_and_notify_guardians(): void
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
            'full_name' => 'Guardian DA',
            'phone' => '7300000910',
            'status' => 'active',
        ]);
        $guardianUser = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);

        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Student DA',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000910',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);

        $driverUser = User::factory()->create(['phone' => '9647909000910']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'DA',
            'age' => 30,
            'id_card_number' => 'IDC-DA',
            'license_number' => 'LIC-DA',
            'primary_phone' => '7770000910',
            'emergency_phone' => '7770001910',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => 'MORNING_PICKUP',
            'bus_number' => 'B-DA',
            'route_title' => 'DA',
            'location' => 'L',
            'students_count' => 1,
            'distance_km' => 2,
            'start_time' => now()->subMinutes(20),
            'end_time' => null,
            'status' => 'ACTIVE',
            'students_preview' => [['id' => (string) $student->id, 'name' => $student->full_name]],
        ]);

        TripHistoryStudent::query()->create([
            'trip_history_id' => $trip->id,
            'student_id' => $student->id,
            'sort_order' => 0,
            'status' => 'ON_WAY',
        ]);

        Sanctum::actingAs($driverUser);

        $this->postJson('/api/delay-alert', [
            'trip_id' => 'TRP-'.$trip->id,
            'reason_type' => 'TRAFFIC',
            'delay_duration_minutes' => 10,
            'note' => 'ازدحام شديد عند جسر الجادرية',
            'driver_lat' => 30.0950,
            'driver_lng' => 31.2000,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('msg', 'تم إرسال بلاغ التأخير وتنبيه أولياء الأمور بنجاح')
            ->assertJsonPath('data', null);

        $this->assertDatabaseHas('delay_alerts', [
            'trip_history_id' => $trip->id,
            'driver_id' => $driver->id,
            'reason_type' => 'TRAFFIC',
            'delay_duration_minutes' => 10,
        ]);

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $guardianUser->id,
            'title' => 'تنبيه تأخير الرحلة',
        ]);

        $this->postJson('/api/delay-alert', [
            'trip_id' => 'TRP-'.$trip->id,
            'reason_type' => 'OTHER',
            'delay_duration_minutes' => 15,
            'driver_lat' => 30.0,
            'driver_lng' => 31.0,
        ])->assertStatus(422);
    }

    public function test_trigger_and_stop_sos_flow(): void
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
            'full_name' => 'Guardian SOS',
            'phone' => '7300000911',
            'status' => 'active',
        ]);
        $guardianUser = User::factory()->create(['guardian_id' => $guardian->id, 'school_id' => $school->id]);
        $adminUser = User::factory()->create(['is_admin' => true, 'school_id' => $school->id]);

        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Student SOS',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000911',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'D',
            'nearest_landmark' => 'L',
            'status' => 'active',
        ]);

        $driverUser = User::factory()->create(['phone' => '9647909000911']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'SOS',
            'age' => 30,
            'id_card_number' => 'IDC-SOS',
            'license_number' => 'LIC-SOS',
            'primary_phone' => '7770000911',
            'emergency_phone' => '7770001911',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => 'MORNING_PICKUP',
            'bus_number' => 'B-SOS',
            'route_title' => 'SOS',
            'location' => 'L',
            'students_count' => 1,
            'distance_km' => 2,
            'start_time' => now()->subMinutes(20),
            'end_time' => null,
            'status' => 'ACTIVE',
            'students_preview' => [['id' => (string) $student->id, 'name' => $student->full_name]],
        ]);

        TripHistoryStudent::query()->create([
            'trip_history_id' => $trip->id,
            'student_id' => $student->id,
            'sort_order' => 0,
            'status' => 'ON_WAY',
        ]);

        Sanctum::actingAs($driverUser);

        $trigger = $this->postJson('/api/driver/sos/trigger', [
            'trip_id' => 'TRP-'.$trip->id,
            'driver_lat' => 33.3128,
            'driver_lng' => 44.3615,
            'emergency_type' => 'SOS',
            'timestamp' => '2026-05-10T17:48:00Z',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('msg', 'تم إرسال نداء الاستغاثة بنجاح، جاري تتبع موقعك')
            ->assertJsonPath('data.tracking_interval_ms', 5000);

        $sosId = (string) $trigger->json('data.sos_id');
        $this->assertStringStartsWith('SOS-', $sosId);

        $this->assertDatabaseHas('sos_alerts', [
            'trip_history_id' => $trip->id,
            'driver_id' => $driver->id,
            'status' => 'TRIGGERED',
            'emergency_type' => 'SOS',
        ]);

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $guardianUser->id,
            'title' => 'نداء استغاثة طارئ',
        ]);
        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $adminUser->id,
            'title' => 'نداء استغاثة طارئ',
        ]);

        $this->postJson('/api/driver/sos/'.$sosId.'/stop', [
            'reason' => 'Resolved',
            'final_lat' => 33.3130,
            'final_lng' => 44.3620,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('msg', 'تم إنهاء حالة الطوارئ بنجاح')
            ->assertJsonPath('data', null);

        $this->assertDatabaseHas('sos_alerts', [
            'status' => 'STOPPED',
            'stop_reason' => 'Resolved',
        ]);

        $row = SosAlert::query()->latest('id')->first();
        $this->assertNotNull($row?->stopped_at);
    }

    public function test_finalize_trip_sets_completed_with_final_meta(): void
    {
        $school = School::query()->create([
            'name_ar' => 'SF',
            'name_en' => 'SF',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $driverUser = User::factory()->create(['phone' => '9647909000912']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'FN',
            'age' => 30,
            'id_card_number' => 'IDC-FN',
            'license_number' => 'LIC-FN',
            'primary_phone' => '7770000912',
            'emergency_phone' => '7770001912',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => 'MORNING_PICKUP',
            'bus_number' => 'B-FN',
            'route_title' => 'Finalize',
            'location' => 'L',
            'students_count' => 0,
            'distance_km' => 5,
            'start_time' => now()->subMinutes(45),
            'end_time' => null,
            'status' => 'ACTIVE',
            'students_preview' => [],
        ]);

        Sanctum::actingAs($driverUser);

        $this->postJson('/api/driver/trips/TRP-'.$trip->id.'/finalize', [
            'trip_id' => 'TRP-'.$trip->id,
            'driver_notes' => 'تمت الرحلة بنجاح، كان هناك تأخير بسيط بسبب الازدحام',
            'final_lat' => 33.3123,
            'final_lng' => 44.3610,
            'end_timestamp' => '2026-05-10T18:05:00Z',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('msg', 'تم إغلاق الرحلة بنجاح، شكراً لك كابتن أحمد');

        $this->assertDatabaseHas('trip_histories', [
            'id' => $trip->id,
            'status' => 'COMPLETED',
            'note' => 'تمت الرحلة بنجاح، كان هناك تأخير بسيط بسبب الازدحام',
            'final_lat' => 33.3123,
            'final_lng' => 44.3610,
        ]);
    }

    public function test_finalize_trip_requires_same_trip_id_in_path_and_body(): void
    {
        $school = School::query()->create([
            'name_ar' => 'SFM',
            'name_en' => 'SFM',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);
        $driverUser = User::factory()->create(['phone' => '9647909000913']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'FM',
            'age' => 30,
            'id_card_number' => 'IDC-FM',
            'license_number' => 'LIC-FM',
            'primary_phone' => '7770000913',
            'emergency_phone' => '7770001913',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);
        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => 'EVENING_RETURN',
            'bus_number' => 'B-FM',
            'route_title' => 'Finalize mismatch',
            'location' => 'L',
            'students_count' => 0,
            'distance_km' => 1,
            'start_time' => now()->subMinutes(20),
            'end_time' => null,
            'status' => 'ACTIVE',
            'students_preview' => [],
        ]);

        Sanctum::actingAs($driverUser);

        $this->postJson('/api/driver/trips/TRP-'.$trip->id.'/finalize', [
            'trip_id' => 'TRP-999999',
            'driver_notes' => 'x',
            'final_lat' => 33.1,
            'final_lng' => 44.1,
            'end_timestamp' => now()->toIso8601String(),
        ])->assertStatus(422);
    }

    public function test_parent_request_to_driver_acceptance_and_full_trip_status_lifecycle(): void
    {
        $school = School::query()->create([
            'name_ar' => 'Lifecycle School',
            'name_en' => 'Lifecycle School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);
        $school->update(['latitude' => 33.3152, 'longitude' => 44.3661]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'Guardian Lifecycle',
            'phone' => '7300000611',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'Student Lifecycle',
            'gender' => 'male',
            'grade' => '3',
            'student_phone' => '7400000611',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'Area',
            'nearest_landmark' => 'Landmark',
            'latitude' => 33.312345,
            'longitude' => 44.361234,
            'status' => 'active',
        ]);

        $parentUser = User::factory()->create([
            'guardian_id' => $guardian->id,
            'school_id' => $school->id,
        ]);
        $driverUser = User::factory()->create(['phone' => '9647909000611']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'Driver',
            'father_name' => 'Lifecycle',
            'grandfather_name' => 'A',
            'last_name' => 'One',
            'age' => 33,
            'id_card_number' => 'IDC-LIFE-1',
            'license_number' => 'LIC-LIFE-1',
            'primary_phone' => '7770000611',
            'emergency_phone' => '7770001611',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        Bus::query()->create([
            'user_id' => $driverUser->id,
            'driver_id' => $driver->id,
            'name' => 'Lifecycle Bus',
            'number' => 'LC-1',
            'type' => 'Bus',
            'city' => 'Baghdad',
            'capacity' => 20,
            'color' => 'yellow',
            'fuel_type' => 'diesel',
            'status' => 'active',
        ]);

        Sanctum::actingAs($parentUser);
        $this->postJson('/api/trip-requests', [
            'student_id' => $student->id,
            'driver_id' => $driver->id,
            'present_type' => 'صباحي',
            'notes' => 'Parent request for lifecycle',
        ])->assertCreated()->assertJsonPath('data.status', 'pending');

        $requestRow = TripRequest::query()->latest('id')->firstOrFail();

        Sanctum::actingAs($driverUser);

        // Driver can see pending order.
        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.id', $requestRow->id)
            ->assertJsonPath('data.0.status', 'pending');

        // Driver accepts request -> trip is scheduled/created as ACTIVE.
        $this->putJson('/api/orders/'.$requestRow->id, [
            'status' => 'accepted',
            'order_id' => (string) $requestRow->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $accepted = $requestRow->fresh();
        $this->assertNotNull($accepted->trip_history_id);
        $trip = TripHistory::query()->findOrFail((int) $accepted->trip_history_id);
        $tripPublicId = 'TRP-'.$trip->id;
        $studentPublicId = 'ST-'.str_pad((string) $student->id, 3, '0', STR_PAD_LEFT);

        // Scheduled trips API now includes the created trip.
        $this->getJson('/api/scheduled-trips')
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.id', $trip->id);

        // Current trip is ACTIVE and student starts at IDLE.
        $this->getJson('/api/trips/current-trip')
            ->assertOk()
            ->assertJsonPath('data.trip_id', $tripPublicId)
            ->assertJsonPath('data.students.0.id', $studentPublicId)
            ->assertJsonPath('data.students.0.status', 'IDLE');

        // Status lifecycle: IDLE -> ON_WAY -> ARRIVED -> BOARDED.
        $this->putJson('/api/update-status', [
            'student_id' => $studentPublicId,
            'new_status' => 'ON_WAY',
        ])->assertOk()->assertJsonPath('success', true);

        $this->putJson('/api/update-status', [
            'student_id' => $studentPublicId,
            'new_status' => 'ARRIVED',
            'driver_lat' => 33.3124,
            'driver_lng' => 44.3612,
        ])
            ->assertOk()
            ->assertJsonPath('data.less_than_50_meter', true);

        $this->putJson('/api/update-status', [
            'student_id' => $studentPublicId,
            'new_status' => 'BOARDED',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('trip_history_students', [
            'trip_history_id' => $trip->id,
            'student_id' => $student->id,
            'status' => 'BOARDED',
        ]);

        // Driver can fetch trip details endpoint.
        $this->getJson('/api/driver/trips/'.$tripPublicId)
            ->assertOk()
            ->assertJsonPath('data.id', $tripPublicId);

        // End trip -> COMPLETED.
        $this->putJson('/api/trips/end-trip')
            ->assertOk()
            ->assertJsonPath('data.trip_id', $tripPublicId)
            ->assertJsonPath('data.status', 'COMPLETED');

        $trip->refresh();
        $this->assertSame('COMPLETED', $trip->status);
        $this->assertNotNull($trip->end_time);

        // No active trip after completion.
        $this->getJson('/api/trips/current-trip')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_driver_trip_details_distance_matches_transport_resolver_and_guardian_location(): void
    {
        $school = School::query()->create([
            'name_ar' => 'المدرسة',
            'name_en' => 'The School',
            'province' => 'بغداد',
            'district' => 'D',
            'address' => 'A',
            'latitude' => 33.3152,
            'longitude' => 44.3661,
            'status' => 'active',
        ]);

        $guardian = Guardian::query()->create([
            'school_id' => $school->id,
            'full_name' => 'ولي',
            'phone' => '7300000777',
            'status' => 'active',
        ]);

        $parentUser = User::factory()->create([
            'guardian_id' => $guardian->id,
            'school_id' => $school->id,
        ]);

        HomeLocation::query()->create([
            'user_id' => $parentUser->id,
            'latitude' => 33.325,
            'longitude' => 44.376,
            'formatted_address' => 'House A, Baghdad',
        ]);

        $student = Student::query()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'full_name' => 'طفل',
            'gender' => 'male',
            'grade' => '1',
            'student_phone' => '7400000777',
            'guardian_name' => $guardian->full_name,
            'guardian_primary_phone' => $guardian->phone,
            'relationship' => 'father',
            'district_area' => 'منطقة',
            'nearest_landmark' => 'معلم',
            'status' => 'active',
        ]);

        $driverUser = User::factory()->create(['phone' => '9647909000777']);
        $driver = Driver::query()->create([
            'user_id' => $driverUser->id,
            'school_id' => $school->id,
            'first_name' => 'D',
            'father_name' => 'D',
            'grandfather_name' => 'D',
            'last_name' => 'W',
            'age' => 30,
            'id_card_number' => 'IDC-GH',
            'license_number' => 'LIC-GH',
            'primary_phone' => '7770000777',
            'emergency_phone' => '7770001777',
            'residential_address' => 'Addr',
            'status' => 'active',
        ]);

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => 'MORNING_PICKUP',
            'bus_number' => 'B-GH',
            'route_title' => 'صباح',
            'location' => 'Legacy trip text',
            'students_count' => 1,
            'distance_km' => 50,
            'start_time' => now()->addHour(),
            'end_time' => null,
            'status' => 'ACTIVE',
            'students_preview' => [['id' => (string) $student->id, 'name' => $student->full_name]],
        ]);

        TripHistoryStudent::query()->create([
            'trip_history_id' => $trip->id,
            'student_id' => $student->id,
            'sort_order' => 0,
            'status' => 'IDLE',
        ]);

        $expectedKm = app(TransportDriverCardBuilder::class)->resolveDistanceKmToSchool(
            null,
            null,
            $student->fresh(),
            $parentUser->fresh(['homeLocation']),
            $school->fresh(),
        );
        $this->assertNotNull($expectedKm);

        Sanctum::actingAs($driverUser);

        $data = $this->getJson('/api/driver/trips/TRP-'.$trip->id)
            ->assertOk()
            ->json('data');

        $this->assertEqualsWithDelta((float) $expectedKm, (float) $data['distance_km'], 0.05);
        $this->assertEqualsWithDelta((float) $expectedKm, (float) $data['distanceKm'], 0.05);
        $this->assertStringContainsString('Guardian home to school', (string) $data['location']);
        $this->assertStringContainsString('House A, Baghdad', (string) $data['location']);
        $this->assertStringContainsString('المدرسة', (string) $data['location']);
    }
}
