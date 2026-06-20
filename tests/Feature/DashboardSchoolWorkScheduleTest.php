<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ProvidesDashboardIraqLocationFields;
use Tests\TestCase;

class DashboardSchoolWorkScheduleTest extends TestCase
{
    use ProvidesDashboardIraqLocationFields;
    use RefreshDatabase;

    public function test_school_staff_can_update_work_schedule_on_dedicated_report(): void
    {
        $school = School::query()->create([
            'name_ar' => 'مدرسة',
            'name_en' => 'Schedule School',
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'Street 1',
            'status' => 'active',
        ]);

        $schoolStaff = User::factory()->create([
            'school_id' => $school->id,
            'is_admin' => false,
        ]);

        $this->actingAs($schoolStaff);

        $this->get(route('dashboard.school_work_schedule.show'))
            ->assertOk()
            ->assertSee('Schedule School', false)
            ->assertSee(__('dashboard.work_days'), false);

        $this->put(route('dashboard.school_work_schedule.update'), [
            'shift_period' => 'BOTH',
            'work_days' => ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'],
            'work_time_from' => '07:30',
            'work_time_to' => '14:00',
            'evening_work_time_from' => '15:00',
            'evening_work_time_to' => '18:00',
        ])->assertRedirect(route('dashboard.school_work_schedule.show'));

        $school->refresh();

        $this->assertSame('BOTH', $school->shift_period);
        $this->assertSame(
            ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'],
            $school->work_days,
        );
        $this->assertStringStartsWith('07:30', (string) $school->work_time_from);
        $this->assertStringStartsWith('14:00', (string) $school->work_time_to);
        $this->assertStringStartsWith('15:00', (string) $school->evening_work_time_from);
        $this->assertStringStartsWith('18:00', (string) $school->evening_work_time_to);
    }

    public function test_school_staff_can_save_evening_only_shift(): void
    {
        $school = School::query()->create([
            'name_ar' => 'مدرسة',
            'name_en' => 'Evening School',
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'Street 1',
            'status' => 'active',
        ]);

        $schoolStaff = User::factory()->create([
            'school_id' => $school->id,
            'is_admin' => false,
        ]);

        $this->actingAs($schoolStaff);

        $this->put(route('dashboard.school_work_schedule.update'), [
            'shift_period' => 'EVENING',
            'work_days' => ['sunday', 'monday'],
            'work_time_from' => '15:00',
            'work_time_to' => '18:00',
        ])->assertRedirect(route('dashboard.school_work_schedule.show'));

        $school->refresh();

        $this->assertSame('EVENING', $school->shift_period);
        $this->assertNull($school->evening_work_time_from);
        $this->assertNull($school->evening_work_time_to);
    }

    public function test_admin_cannot_access_school_work_schedule_report(): void
    {
        $school = School::query()->create([
            'name_ar' => 'مدرسة',
            'name_en' => 'Admin Blocked School',
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'Street 1',
            'status' => 'active',
        ]);

        $admin = User::factory()->create([
            'is_admin' => true,
            'school_id' => $school->id,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard.school_work_schedule.show'))
            ->assertForbidden();
    }

    public function test_school_add_form_does_not_save_work_schedule(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->post(route('dashboard.schools.store'), $this->withSchoolIraqLocation([
            'name_ar' => 'مدرسة',
            'name_en' => 'No Schedule On Create',
            'address' => 'Street 1',
            'status' => 'active',
            'work_days' => ['monday'],
            'work_time_from' => '08:00',
            'work_time_to' => '15:00',
        ]))->assertRedirect(route('dashboard.schools.index'));

        $school = School::query()->where('name_en', 'No Schedule On Create')->firstOrFail();

        $this->assertNull($school->work_days);
        $this->assertNull($school->work_time_from);
        $this->assertNull($school->work_time_to);
    }
}
