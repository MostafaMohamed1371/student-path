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
 * Creates today's demo {@see TripHistory} rows for **every** {@see Driver} (each linked user sees them on
 * {@code GET /api/scheduled-trips}).
 *
 * Idempotent per driver: deletes previous seed rows (same {@see SEED_TRIP_NOTE}) then inserts four slots.
 *
 * When {@see config('dashboard.seed_phone_national')} is set, also ensures that dashboard user exists with a
 * {@see Driver} row so fresh installs still have one dev driver before the loop.
 */
class DriverScheduledTripsSeeder extends Seeder
{
    public const SEED_TRIP_NOTE = 'seed:driver_scheduled_trips_demo';

    public function run(): void
    {
        $this->bootstrapDashboardDriverIfConfigured();

        $drivers = Driver::query()
            ->whereNotNull('user_id')
            ->orderBy('id');

        $count = 0;
        foreach ($drivers->cursor() as $driver) {
            $this->seedDemoTripsForDriver($driver);
            $count++;
        }

        if ($count === 0) {
            $this->command?->warn('DriverScheduledTripsSeeder: no drivers in database (add drivers, or set DASHBOARD_SEED_PHONE for bootstrap).');

            return;
        }

        $this->command?->info("DriverScheduledTripsSeeder: seeded today's demo trips for {$count} driver(s). Authenticate as each driver's user for GET /api/scheduled-trips.");
    }

    /**
     * Optional: one dev driver tied to {@code DASHBOARD_SEED_PHONE} so {@code php artisan db:seed} on empty DB still has a driver.
     */
    private function bootstrapDashboardDriverIfConfigured(): void
    {
        $national = trim((string) config('dashboard.seed_phone_national'));
        if ($national === '') {
            return;
        }

        $phone = app(PhoneNormalizer::class)->normalize($national);
        if ($phone === '') {
            $this->command?->warn('DriverScheduledTripsSeeder: dashboard seed phone normalizes to empty; skip bootstrap.');

            return;
        }

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

        Driver::query()->firstOrCreate(
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
    }

    private function seedDemoTripsForDriver(Driver $driver): void
    {
        $driver->loadMissing('user');
        if ($driver->user === null) {
            $this->command?->warn('DriverScheduledTripsSeeder: driver '.$driver->id.' has no user_id; skip.');

            return;
        }

        $school = School::query()->find($driver->school_id);
        if ($school === null) {
            $this->command?->warn('DriverScheduledTripsSeeder: driver '.$driver->id.' has missing school_id '.$driver->school_id.'; skip.');

            return;
        }

        $busNumber = 'SEED-BUS-D'.$driver->id;
        Bus::query()->firstOrCreate(
            ['driver_id' => $driver->id],
            [
                'user_id' => $driver->user_id,
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
    }
}
