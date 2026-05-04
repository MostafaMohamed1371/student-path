<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiMergedCanonicalRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_wallet_under_api_root(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/wallet')->assertOk()->assertJsonPath('success', true);
    }

    public function test_org_schools_index_requires_auth(): void
    {
        $this->getJson('/api/org/schools')->assertUnauthorized();
    }
}
