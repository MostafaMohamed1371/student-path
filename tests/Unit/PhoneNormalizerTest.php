<?php

namespace Tests\Unit;

use App\Services\Phone\DashboardPhoneRegistry;
use App\Services\Phone\PhoneNormalizer;
use Tests\TestCase;

class PhoneNormalizerTest extends TestCase
{
    public function test_normalize_accepts_ten_digit_national_number(): void
    {
        $normalizer = new PhoneNormalizer();

        $this->assertSame('9647901234567', $normalizer->normalize('7901234567'));
    }

    public function test_normalize_accepts_existing_canonical_number(): void
    {
        $normalizer = new PhoneNormalizer();

        $this->assertSame('9647901234567', $normalizer->normalize('9647901234567'));
    }

    public function test_phones_match_across_national_and_canonical_formats(): void
    {
        $registry = app(DashboardPhoneRegistry::class);

        $this->assertTrue($registry->phonesMatch('9647901234567', '7901234567'));
        $this->assertTrue($registry->phonesMatch('7901234567', '9647901234567'));
    }
}
