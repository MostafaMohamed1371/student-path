<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardGuardianIndexGroupingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardians_index_groups_same_parent_once(): void
    {
        $schoolA = School::query()->create([
            'name_ar' => 'A',
            'name_en' => 'taghreed',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);
        $schoolB = School::query()->create([
            'name_ar' => 'B',
            'name_en' => 'dkejniodjei',
            'province' => 'P',
            'district' => 'D',
            'address' => 'B',
            'status' => 'active',
        ]);

        Guardian::query()->create([
            'school_id' => $schoolA->id,
            'full_name' => 'raafat gyg yuy',
            'phone' => '1234123412',
            'id_card_number' => '12341234123412',
            'status' => 'active',
        ]);
        Guardian::query()->create([
            'school_id' => $schoolB->id,
            'full_name' => 'raafat gyg yuy',
            'phone' => '1234123412',
            'id_card_number' => '12341234123412',
            'status' => 'active',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->get(route('dashboard.guardians.index'))
            ->assertOk()
            ->assertSee('raafat gyg yuy', false)
            ->assertSee('taghreed, dkejniodjei', false)
            ->assertSee('2 schools', false);
    }
}
