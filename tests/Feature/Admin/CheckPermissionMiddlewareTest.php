<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CheckPermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private function protectedRoute(): string
    {
        Route::middleware(['web', 'auth', 'checkpermission:widgets.manage'])
            ->get('/admin/_permission-probe', fn () => 'ok');

        return '/admin/_permission-probe';
    }

    public function test_guests_are_redirected_before_the_permission_check_runs()
    {
        $this->get($this->protectedRoute())->assertRedirect(route('admin.login'));
    }

    public function test_a_user_without_the_permission_is_denied()
    {
        Permission::findOrCreate('widgets.manage', config('admin-kit.guard'));
        $this->actingAs(User::factory()->create());

        $this->get($this->protectedRoute())->assertForbidden();
    }

    public function test_a_user_with_the_permission_is_allowed()
    {
        Permission::findOrCreate('widgets.manage', config('admin-kit.guard'));
        $user = User::factory()->create();
        $user->givePermissionTo('widgets.manage');
        $this->actingAs($user);

        $this->get($this->protectedRoute())->assertOk()->assertSee('ok');
    }

    public function test_a_role_carrying_the_permission_is_allowed()
    {
        Permission::findOrCreate('widgets.manage', config('admin-kit.guard'));
        $role = Role::findOrCreate('Widget Manager', config('admin-kit.guard'));
        $role->givePermissionTo('widgets.manage');

        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $this->get($this->protectedRoute())->assertOk();
    }

    public function test_the_protected_role_bypasses_every_permission_check_even_without_being_granted_it()
    {
        // Deliberately never created: proves the bypass doesn't depend on
        // the permission existing, let alone being assigned.
        $superAdmin = Role::findOrCreate(config('admin-kit.roles.protected_role'), config('admin-kit.guard'));

        $user = User::factory()->create();
        $user->assignRole($superAdmin);
        $this->actingAs($user);

        $this->get($this->protectedRoute())->assertOk();
    }

    public function test_the_error_page_is_the_panels_branded_403_not_a_bare_response()
    {
        config()->set('app.debug', false);
        Permission::findOrCreate('widgets.manage', config('admin-kit.guard'));
        $this->actingAs(User::factory()->create());

        $this->get($this->protectedRoute())
            ->assertForbidden()
            ->assertSee('Access denied');
    }
}
