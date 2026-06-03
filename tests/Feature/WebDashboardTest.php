<?php

namespace Tests\Feature;

use App\Models\OtpCode;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee(__('dashboard.welcome'), false)
            ->assertSee('+964', false);
    }

    public function test_dashboard_redirects_guests_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_admin_can_login_with_phone_and_password(): void
    {
        User::factory()->create([
            'phone' => '9647912345678',
            'password' => 'dashboard-secret',
            'is_admin' => true,
        ]);

        $this->post(route('login.authenticate'), [
            'phone' => '7912345678',
            'password' => 'dashboard-secret',
        ])->assertRedirect(route('dashboard'));

        $this->get('/dashboard')->assertOk()->assertSee(__('dashboard.dashboard_title'), false);
    }

    public function test_login_lookup_returns_password_mode_for_admin(): void
    {
        User::factory()->create([
            'phone' => '9647900000099',
            'is_admin' => true,
            'is_active' => true,
        ]);

        $this->postJson(route('login.lookup'), ['phone' => '7900000099'])
            ->assertOk()
            ->assertJsonPath('login_mode', 'password');
    }

    public function test_login_lookup_returns_otp_mode_for_non_admin(): void
    {
        User::factory()->create([
            'phone' => '9647900000088',
            'is_admin' => false,
            'school_id' => null,
            'is_active' => true,
        ]);

        $this->postJson(route('login.lookup'), ['phone' => '7900000088'])
            ->assertOk()
            ->assertJsonPath('login_mode', 'otp');
    }

    public function test_non_admin_can_login_with_otp(): void
    {
        $phoneNational = '7900000077';
        User::factory()->create([
            'phone' => '964'.$phoneNational,
            'is_admin' => false,
            'school_id' => School::query()->create([
                'name_ar' => 'مدرسة',
                'name_en' => 'School',
                'province' => 'Baghdad',
                'district' => '1',
                'address' => 'St',
                'status' => 'active',
            ])->id,
            'password' => null,
        ]);

        $this->postJson(route('login.send_otp'), ['phone' => $phoneNational])->assertOk();

        $otp = OtpCode::query()->where('phone', '964'.$phoneNational)->latest('id')->first();
        $this->assertNotNull($otp);

        $this->post(route('login.verify_otp'), [
            'phone' => $phoneNational,
            'code' => $otp->code,
        ])->assertRedirect(route('dashboard'));

        $this->get('/dashboard')->assertOk();
    }

    public function test_non_admin_cannot_use_password_login(): void
    {
        User::factory()->create([
            'phone' => '9647900000066',
            'is_admin' => false,
            'password' => 'secret',
        ]);

        $this->post(route('login.authenticate'), [
            'phone' => '7900000066',
            'password' => 'secret',
        ])->assertSessionHasErrors('phone');
    }

    public function test_dashboard_user_can_create_update_and_delete_user(): void
    {
        $school = School::query()->create([
            'name_ar' => 'مدرسة',
            'name_en' => 'Test School',
            'province' => 'Baghdad',
            'district' => '1',
            'address' => 'St',
            'status' => 'active',
        ]);

        $admin = User::factory()->create([
            'phone' => '9647900000001',
            'password' => 'dashboard-secret',
            'is_admin' => true,
            'school_id' => $school->id,
        ]);

        $this->actingAs($admin);

        $this->post(route('dashboard.users.store'), [
            'name' => 'Test User',
            'school_id' => $school->id,
            'phone' => '7901234567',
            'password' => '12345678',
            'is_active' => 1,
            'votes' => 0,
            'rate' => 0,
        ])->assertRedirect(route('dashboard.users.index'));

        $created = User::query()->where('phone', '9647901234567')->first();
        $this->assertNotNull($created);

        $this->put(route('dashboard.users.update', $created), [
            'name' => 'Test User 2',
            'school_id' => $school->id,
            'phone' => '7901234567',
            'is_active' => 0,
            'password' => '',
            'votes' => 0,
            'rate' => 0,
        ])->assertRedirect(route('dashboard.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $created->id,
            'name' => 'Test User 2',
            'phone' => '9647901234567',
            'is_active' => 0,
        ]);

        $this->delete(route('dashboard.users.destroy', $created))
            ->assertRedirect(route('dashboard.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $created->id]);
    }
}
