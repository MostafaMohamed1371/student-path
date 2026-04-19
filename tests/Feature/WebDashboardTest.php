<?php

namespace Tests\Feature;

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

    public function test_user_can_login_with_phone_and_password(): void
    {
        User::factory()->create([
            'phone' => '9647912345678',
            'password' => 'dashboard-secret',
        ]);

        $this->post('/login', [
            'phone' => '7912345678',
            'password' => 'dashboard-secret',
        ])->assertRedirect(route('dashboard'));

        $this->get('/dashboard')->assertOk()->assertSee(__('dashboard.dashboard_title'), false);
    }

    public function test_dashboard_user_can_create_update_and_delete_user(): void
    {
        $admin = User::factory()->create([
            'phone' => '9647900000001',
            'password' => 'dashboard-secret',
        ]);

        $this->actingAs($admin);

        $this->post(route('dashboard.users.store'), [
            'name' => 'Test User',
            'phone' => '7901234567',
            'password' => '12345678',
            'is_active' => 1,
        ])->assertRedirect(route('dashboard.users.index'));

        $created = User::query()->where('phone', '9647901234567')->first();
        $this->assertNotNull($created);

        $this->put(route('dashboard.users.update', $created), [
            'name' => 'Test User 2',
            'phone' => '7901234567',
            'is_active' => 0,
            'password' => '',
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
