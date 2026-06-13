<?php

namespace Tests\Unit;

use App\Models\Guardian;
use App\Models\School;
use App\Services\Guardian\GuardianIndexGrouper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuardianIndexGrouperTest extends TestCase
{
    use RefreshDatabase;

    public function test_groups_same_parent_id_card_across_schools(): void
    {
        $schoolA = School::query()->create([
            'name_ar' => 'A',
            'name_en' => 'School A',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);
        $schoolB = School::query()->create([
            'name_ar' => 'B',
            'name_en' => 'School B',
            'province' => 'P',
            'district' => 'D',
            'address' => 'B',
            'status' => 'active',
        ]);

        $guardianA = Guardian::query()->create([
            'school_id' => $schoolA->id,
            'full_name' => 'Same Parent',
            'phone' => '7900111222',
            'id_card_number' => 'PARENT-001',
            'status' => 'active',
        ]);
        $guardianB = Guardian::query()->create([
            'school_id' => $schoolB->id,
            'full_name' => 'Same Parent',
            'phone' => '7900111222',
            'id_card_number' => 'PARENT-001',
            'status' => 'active',
        ]);

        $groups = app(GuardianIndexGrouper::class)->group(collect([$guardianA, $guardianB]));

        $this->assertCount(1, $groups);
        $this->assertSame(['School A', 'School B'], $groups->first()->schoolLabels);
        $this->assertTrue($groups->first()->isMultiSchool());
    }
}
