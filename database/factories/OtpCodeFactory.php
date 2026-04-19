<?php

namespace Database\Factories;

use App\Enums\OtpPurpose;
use App\Models\OtpCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<OtpCode>
 */
class OtpCodeFactory extends Factory
{
    protected $model = OtpCode::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plain = str_pad((string) fake()->numberBetween(0, 9999), 4, '0', STR_PAD_LEFT);

        return [
            'phone' => '9647'.fake()->unique()->numerify('#########'),
            'code' => Hash::make($plain),
            'purpose' => OtpPurpose::Login,
            'expires_at' => now()->addMinutes(5),
            'resend_available_at' => now()->addSeconds(30),
            'verified_at' => null,
            'attempts' => 0,
            'max_attempts' => 5,
        ];
    }
}
