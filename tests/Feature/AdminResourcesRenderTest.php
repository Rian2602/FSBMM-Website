<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Course;
use App\Models\Eresource;
use App\Models\Organization;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Renders every Filament resource page for a super admin so form/table
 * schema errors (e.g. a bogus FileUpload method) surface in the suite —
 * this caught EresourceResource's storeFileNames() 500 on the edit page.
 */
class AdminResourcesRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_admin_resource_pages_render_for_super_admin(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        // A second staff user: the acting admin cannot edit their own account
        // (super_admin self-edit is blocked), so the users edit page needs a
        // different record to exercise the form.
        $staff = User::factory()->create(['role' => User::ROLE_EDITOR]);

        // One row per resource so list + edit pages have data to render.
        $organization = Organization::factory()->create();
        $category = Category::factory()->create();
        $article = Article::factory()->create(['category_id' => $category->id]);
        $page = Page::factory()->create();
        $eresource = Eresource::factory()->create();
        $course = Course::factory()->create();

        // Route params bind by each model's route key (slug for content
        // models, id for users/categories) — getRouteKey() handles both.
        $paths = [
            'index' => [
                '/admin/users',
                '/admin/organizations',
                '/admin/categories',
                '/admin/articles',
                '/admin/pages',
                '/admin/eresources',
                '/admin/courses',
            ],
            'edit' => [
                '/admin/users/' . $staff->getRouteKey() . '/edit',
                '/admin/organizations/' . $organization->getRouteKey() . '/edit',
                '/admin/categories/' . $category->getRouteKey() . '/edit',
                '/admin/articles/' . $article->getRouteKey() . '/edit',
                '/admin/pages/' . $page->getRouteKey() . '/edit',
                '/admin/eresources/' . $eresource->getRouteKey() . '/edit',
                '/admin/courses/' . $course->getRouteKey() . '/edit',
            ],
        ];

        foreach ($paths as $group) {
            foreach ($group as $path) {
                $this->actingAs($admin)->get($path)->assertSuccessful();
            }
        }
    }

    public function test_admin_resource_pages_render_for_editor(): void
    {
        $editor = User::factory()->create(); // editor: content yes, users no

        $this->actingAs($editor)->get('/admin/articles')->assertSuccessful();
        $this->actingAs($editor)->get('/admin/pages')->assertSuccessful();
        $this->actingAs($editor)->get('/admin/users')->assertForbidden();
    }
}
