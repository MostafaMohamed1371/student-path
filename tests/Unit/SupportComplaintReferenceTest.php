<?php

namespace Tests\Unit;

use App\Support\SupportComplaintReference;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SupportComplaintReferenceTest extends TestCase
{
    public function test_complaint_reference_uses_six_digit_sequence(): void
    {
        $reference = SupportComplaintReference::format(
            42,
            Carbon::parse('2026-06-14 10:00:00'),
        );

        $this->assertSame('#CMP-2026-000042', $reference);
    }
}
