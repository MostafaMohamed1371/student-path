<?php

namespace Database\Seeders;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\School;
use App\Models\TripHistory;
use App\Models\User;
use App\Services\Phone\PhoneNormalizer;
use Illuminate\Database\Seeder;

/**
 * Creates today's demo {@see TripHistory} rows for the seeded dashboard user so
 * {@code GET /api/scheduled-trips} returns data after {@code php artisan db:seed}.
 *
 * Idempotent: removes previous seed rows (same {@see SEED_TRIP_NOTE}) for that driver, then reinserts.
 */
class DriverScheduledTripsSeeder extends Seeder
{
    public const SEED_TRIP_NOTE = 'seed:driver_scheduled_trips_demo';

    public function run(): void
    {
        $national = trim((string) config('dashboard.seed_phone_national'));
        if ($national === '') {
            $this->command?->warn('dashboard.seed_phone_national empty; skip DriverScheduledTripsSeeder.');

            return;
        }

        $phone = app(PhoneNormalizer::class)->normalize($national);
        if ($phone === '') {
            $this->command?->warn('Phone normalizer returned empty for dashboard seed phone; skip DriverScheduledTripsSeeder.');

            return;
        }

        // Same user as DatabaseSeeder so this class works alone: `php artisan db:seed --class=DriverScheduledTripsSeeder`
        $user = User::query()->updateOrCreate(
            ['phone' => $phone],
            [
                'name' => 'Dashboard Admin',
                'password' => (string) config('dashboard.seed_password'),
                'phone_verified_at' => now(),
                'is_active' => true,
                'is_admin' => true,
            ]
        );

        $school = School::query()->firstOrCreate(
            ['name_en' => 'Student-Path Demo School (driver trips seed)'],
            [
                'name_ar' => 'مدرسة عينة — رحلات السائق',
                'province' => 'Baghdad',
                'district' => 'Rusafa',
                'address' => 'Seed — driver scheduled trips',
                'status' => 'active',
            ]
        );

        $driver = Driver::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'school_id' => $school->id,
                'first_name' => 'Seed',
                'father_name' => 'Driver',
                'grandfather_name' => 'X',
                'last_name' => 'Demo',
                'age' => 30,
                'id_card_number' => 'SEED-IDC-DRIVER',
                'license_number' => 'SEED-LIC-DRIVER',
                'primary_phone' => $phone,
                'emergency_phone' => $phone,
                'residential_address' => 'Seed',
                'status' => 'active',
            ]
        );

        if ((int) $driver->school_id !== (int) $school->id) {
            $driver->forceFill(['school_id' => $school->id])->save();
        }

        $busNumber = 'SEED-BUS-U'.$user->id;
        Bus::query()->firstOrCreate(
            ['driver_id' => $driver->id],
            [
                'user_id' => $user->id,
                'name' => 'Seed Bus',
                'number' => $busNumber,
                'type' => 'Bus',
                'city' => 'Baghdad',
                'capacity' => 20,
                'color' => 'yellow',
                'fuel_type' => 'diesel',
                'status' => 'active',
            ]
        );

        TripHistory::query()
            ->where('driver_id', $driver->id)
            ->where('note', self::SEED_TRIP_NOTE)
            ->delete();

        $tz = config('app.timezone') ?: 'UTC';
        // Match DriverTripModuleService::scheduledTripsForDriverList (uses now() in app TZ for "today").
        $day = now()->timezone($tz)->startOfDay();

        foreach (
            [
                ['trip_type' => 'MORNING_PICKUP', 'hour' => 7, 'minute' => 0],
                ['trip_type' => 'MORNING_RETURN', 'hour' => 12, 'minute' => 30],
                ['trip_type' => 'EVENING_PICKUP', 'hour' => 14, 'minute' => 0],
                ['trip_type' => 'EVENING_RETURN', 'hour' => 18, 'minute' => 30],
            ] as $slot
        ) {
            TripHistory::query()->create([
                'school_id' => $school->id,
                'driver_id' => $driver->id,
                'trip_type' => $slot['trip_type'],
                'bus_number' => $busNumber,
                'route_title' => 'Seed '.$slot['trip_type'],
                'location' => 'Baghdad',
                'students_count' => 0,
                'distance_km' => 0,
                'start_time' => $day->copy()->setTime((int) $slot['hour'], (int) $slot['minute'], 0),
                'end_time' => null,
                'status' => 'ACTIVE',
                'note' => self::SEED_TRIP_NOTE,
                'students_preview' => [],
            ]);
        }

        $this->command?->info('DriverScheduledTripsSeeder: demo trips for driver '.$driver->id.' (user '.$user->id.').');
        $this->command?->info('Call GET /api/scheduled-trips as this driver: Bearer token for user with phone '.$phone.' (DASHBOARD_SEED_PHONE national digits → 964…).');
    }
}
