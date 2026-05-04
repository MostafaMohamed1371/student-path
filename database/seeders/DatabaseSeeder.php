<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Phone\PhoneNormalizer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $national = (string) config('dashboard.seed_phone_national');
        $phone = app(PhoneNormalizer::class)->normalize($national);

        User::query()->updateOrCreate(
            ['phone' => $phone],
            [
                'name' => 'Dashboard Admin',
                'password' => (string) config('dashboard.seed_password'),
                'phone_verified_at' => now(),
                'is_active' => true,
                'is_admin' => true,
            ]
        );

        $this->call(LocationMetaSeeder::class);
        $this->call(NeighborhoodSeeder::class);
    }
}
