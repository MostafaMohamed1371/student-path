<?php

namespace Tests\Unit;

use App\Models\Driver;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDriverProfileSynchronizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_changing_user_city_only_updates_driver_address(): void
    {
        $school = School::query()->create([
            'name_ar' => 'S',
            'name_en' => 'School',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'school_id' => $school->id,
            'name' => 'Ali Hassan Karim',
            'phone' => '9647901234567',
            'city' => 'Old City',
            'licence_number' => 'LIC-OLD',
        ]);

        $driver = Driver::query()->create([
            'user_id' => $user->id,
            'school_id' => $school->id,
            'first_name' => 'Ali',
            'father_name' => 'Hassan',
            'grandfather_name' => 'Omar',
            'last_name' => 'Karim',
            'age' => 30,
            'id_card_number' => 'DRV-1',
            'license_number' => 'LIC-OLD',
            'primary_phone' => '7901234567',
            'emergency_phone' => '7901234568',
            'residential_address' => 'Old City',
            'status' => 'active',
        ]);

        $user->city = 'New City';
        $user->save();

        $driver->refresh();

        $this->assertSame('New City', $driver->residential_address);
        $this->assertSame('Ali', $driver->first_name);
        $this->assertSame('Omar', $driver->grandfather_name);
    }
}
