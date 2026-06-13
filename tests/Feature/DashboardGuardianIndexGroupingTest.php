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
            ->assertSee('2 schools', false)
            ->assertDontSee('Edit (taghreed)', false);

        $primary = Guardian::query()->where('school_id', $schoolA->id)->first();
        $this->get(route('dashboard.guardians.choose_edit', $primary))
            ->assertOk()
            ->assertSee('Choose school to edit', false)
            ->assertSee('taghreed', false)
            ->assertSee('dkejniodjei', false);

        $this->get(route('dashboard.guardians.choose_delete', $primary))
            ->assertOk()
            ->assertSee('Choose school to delete', false)
            ->assertSee('taghreed', false)
            ->assertSee('dkejniodjei', false);
    }

    public function test_guardian_edit_shows_only_assigned_school_not_all_schools(): void
    {
        $schoolA = School::query()->create([
            'name_ar' => 'A',
            'name_en' => 'Assigned School Only',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);
        School::query()->create([
            'name_ar' => 'B',
            'name_en' => 'Other School Hidden',
            'province' => 'P',
            'district' => 'D',
            'address' => 'B',
            'status' => 'active',
        ]);

        $guardian = Guardian::query()->create([
            'school_id' => $schoolA->id,
            'full_name' => 'Locked School Parent',
            'phone' => '7900888001',
            'id_card_number' => 'LOCK-SCHOOL-1',
            'status' => 'active',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->get(route('dashboard.guardians.edit', $guardian))
            ->assertOk()
            ->assertSee('Assigned School Only', false)
            ->assertDontSee('Other School Hidden', false)
            ->assertSee('id="guardian_form_school_id"', false);
    }

    public function test_guardian_edit_shows_assigned_schools_dropdown_for_multi_school_parent(): void
    {
        $schoolA = School::query()->create([
            'name_ar' => 'A',
            'name_en' => 'School Alpha',
            'province' => 'P',
            'district' => 'D',
            'address' => 'A',
            'status' => 'active',
        ]);
        $schoolB = School::query()->create([
            'name_ar' => 'B',
            'name_en' => 'School Beta',
            'province' => 'P',
            'district' => 'D',
            'address' => 'B',
            'status' => 'active',
        ]);

        $guardianA = Guardian::query()->create([
            'school_id' => $schoolA->id,
            'full_name' => 'Multi Dropdown Parent',
            'phone' => '7900999001',
            'id_card_number' => 'MULTI-DD-1',
            'status' => 'active',
        ]);
        Guardian::query()->create([
            'school_id' => $schoolB->id,
            'full_name' => 'Multi Dropdown Parent',
            'phone' => '7900999001',
            'id_card_number' => 'MULTI-DD-1',
            'status' => 'active',
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->get(route('dashboard.guardians.edit', $guardianA))
            ->assertOk()
            ->assertSee('School Alpha', false)
            ->assertSee('School Beta', false)
            ->assertSee('data-guardian-school-switcher', false)
            ->assertDontSee('Other School Hidden', false);
    }
}
