<?php

namespace Tests\Unit;

use App\Rules\FullNameWordCount;
use Tests\TestCase;

class FullNameWordCountTest extends TestCase
{
    public function test_accepts_three_and_four_words(): void
    {
        $rule = new FullNameWordCount(3, 4);
        $failures = [];

        $rule->validate('full_name', 'Ali Hassan Karim', function (string $message) use (&$failures): void {
            $failures[] = $message;
        });
        $this->assertSame([], $failures);

        $failures = [];
        $rule->validate('full_name', 'Ali Hassan Karim Saleh', function (string $message) use (&$failures): void {
            $failures[] = $message;
        });
        $this->assertSame([], $failures);
    }

    public function test_rejects_two_or_five_words(): void
    {
        $rule = new FullNameWordCount(3, 4);
        $failures = [];

        $rule->validate('full_name', 'Ali Hassan', function (string $message) use (&$failures): void {
            $failures[] = $message;
        });
        $this->assertCount(1, $failures);

        $failures = [];
        $rule->validate('full_name', 'One Two Three Four Five', function (string $message) use (&$failures): void {
            $failures[] = $message;
        });
        $this->assertCount(1, $failures);
    }
}
