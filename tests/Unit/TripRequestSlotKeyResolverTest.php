<?php

namespace Tests\Unit;

use App\Enums\TripType;
use App\Models\Driver;
use App\Models\School;
use App\Models\TripHistory;
use App\Models\TripRequest;
use App\Models\User;
use App\Services\Trips\TripRequestSlotKeyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripRequestSlotKeyResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_slot_from_trip_history_trip_type(): void
    {
        $school = School::query()->create([
            'name_ar' => 'مدرسة',
            'name_en' => 'Resolver School',
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'School Ave',
            'status' => 'active',
        ]);

        $driver = Driver::query()->create([
            'school_id' => $school->id,
            'user_id' => User::factory()->create()->id,
            'first_name' => 'D',
            'father_name' => 'T',
            'grandfather_name' => 'X',
            'last_name' => 'One',
            'age' => 35,
            'id_card_number' => 'RES-DRV',
            'license_number' => 'RES-LIC',
            'primary_phone' => '7900111100',
            'emergency_phone' => '7900111101',
            'residential_address' => 'Baghdad',
            'status' => 'active',
        ]);

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => TripType::EVENING_RETURN->value,
            'bus_number' => '1',
            'route_title' => 'Route',
            'location' => 'Depot',
            'students_count' => 0,
            'distance_km' => 1,
            'start_time' => now(),
            'end_time' => now()->addHour(),
            'status' => 'ACTIVE',
            'students_preview' => [],
        ]);

        $resolver = new TripRequestSlotKeyResolver;

        $this->assertSame(
            TripType::EVENING_RETURN->value,
            $resolver->slotKeyForTripHistoryId((int) $trip->id),
        );
    }

    public function test_infers_return_from_present_type(): void
    {
        $resolver = new TripRequestSlotKeyResolver;

        $this->assertSame(
            TripType::MORNING_RETURN->value,
            $resolver->inferTripTypeFromPresentType('صباحي - عودة'),
        );
        $this->assertSame(
            TripType::MORNING_PICKUP->value,
            $resolver->inferTripTypeFromPresentType('صباحي'),
        );
    }

    public function test_slot_key_for_request_prefers_linked_trip(): void
    {
        $school = School::query()->create([
            'name_ar' => 'مدرسة',
            'name_en' => 'Resolver School 2',
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'School Ave',
            'status' => 'active',
        ]);

        $driver = Driver::query()->create([
            'school_id' => $school->id,
            'user_id' => User::factory()->create()->id,
            'first_name' => 'D',
            'father_name' => 'T',
            'grandfather_name' => 'X',
            'last_name' => 'Two',
            'age' => 35,
            'id_card_number' => 'RES-DRV2',
            'license_number' => 'RES-LIC2',
            'primary_phone' => '7900222200',
            'emergency_phone' => '7900222201',
            'residential_address' => 'Baghdad',
            'status' => 'active',
        ]);

        $trip = TripHistory::query()->create([
            'school_id' => $school->id,
            'driver_id' => $driver->id,
            'trip_type' => TripType::MORNING_PICKUP->value,
            'bus_number' => '1',
            'route_title' => 'Route',
            'location' => 'Depot',
            'students_count' => 0,
            'distance_km' => 1,
            'start_time' => now(),
            'end_time' => now()->addHour(),
            'status' => 'ACTIVE',
            'students_preview' => [],
        ]);

        $request = TripRequest::query()->make([
            'trip_history_id' => $trip->id,
            'present_type' => 'مسائي',
        ]);
        $request->setRelation('tripHistory', $trip);

        $resolver = new TripRequestSlotKeyResolver;

        $this->assertSame(
            TripType::MORNING_PICKUP->value,
            $resolver->slotKeyForRequest($request),
        );
    }
}
