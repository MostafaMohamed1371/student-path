<?php

namespace Tests\Unit;

use App\Support\StudentNameComposer;
use Tests\TestCase;

class StudentNameComposerTest extends TestCase
{
    public function test_family_suffix_uses_last_two_words_when_guardian_has_four_parts(): void
    {
        $this->assertSame(
            'Mohammed Jawad',
            StudentNameComposer::familySuffixFromGuardianName('Hassan Ali Mohammed Jawad'),
        );
    }

    public function test_family_suffix_uses_last_word_when_guardian_has_three_parts(): void
    {
        $this->assertSame(
            'Jawad',
            StudentNameComposer::familySuffixFromGuardianName('Hassan Ali Jawad'),
        );
    }

    public function test_compose_with_guardian_parent_name_appends_guardian_full_name(): void
    {
        $this->assertSame(
            'Ahmed Hassan Ali Jawad',
            StudentNameComposer::composeWithGuardianParentName(
                'Ahmed',
                'Hassan Ali Jawad',
            ),
        );
    }

    public function test_compose_builds_three_or_four_word_full_name(): void
    {
        $this->assertSame(
            'Ahmed Hassan Mohammed Jawad',
            StudentNameComposer::compose('Ahmed', 'Hassan', 'Mohammed Jawad'),
        );
    }

    public function test_split_extracts_first_second_and_suffix(): void
    {
        $this->assertSame([
            'first' => 'Ahmed',
            'second' => 'Hassan',
            'family_suffix' => 'Mohammed Jawad',
        ], StudentNameComposer::split('Ahmed Hassan Mohammed Jawad'));
    }
}
