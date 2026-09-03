<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_page_is_reachable(): void
    {
        $this->get('/admin/login')->assertStatus(200);
    }

    public function test_anonymous_is_redirected_away_from_admin(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_super_admin_can_enter_the_panel(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($user)->get('/admin')->assertSuccessful();
    }

    public function test_role_column_defaults_to_editor(): void
    {
        $this->assertSame(User::ROLE_EDITOR, User::factory()->create()->role);
    }
}
