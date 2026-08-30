<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('permissions.manage', config('admin-kit.guard'));
    }

    private function actingAsManager(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('permissions.manage');
        $this->actingAs($user);

        return $user;
    }

    public function test_guests_cannot_reach_any_permissions_route()
    {
        $this->get(route('admin.permissions.index'))->assertRedirect(route('admin.login'));
    }

    public function test_a_user_without_permissions_manage_is_forbidden()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.permissions.index'))->assertForbidden();
    }

    public function test_the_index_page_lists_permissions_with_a_role_count()
    {
        $this->actingAsManager();
        $permission = Permission::findOrCreate('reports.export', config('admin-kit.guard'));
        Role::findOrCreate('Reporter', config('admin-kit.guard'))->givePermissionTo($permission);

        $this->get(route('admin.permissions.index'))
            ->assertOk()
            ->assertSee('reports.export');
    }

    public function test_a_permission_can_be_created()
    {
        $this->actingAsManager();

        $this->post(route('admin.permissions.store'), ['name' => 'reports.export'])
            ->assertRedirect(route('admin.permissions.index'));

        $this->assertDatabaseHas('permissions', [
            'name' => 'reports.export',
            'guard_name' => config('admin-kit.guard'),
        ]);
    }

    public function test_a_permission_name_must_be_unique()
    {
        $this->actingAsManager();
        Permission::findOrCreate('reports.export', config('admin-kit.guard'));

        $this->post(route('admin.permissions.store'), ['name' => 'reports.export'])
            ->assertSessionHasErrors('name');
    }

    public function test_a_permission_name_is_required()
    {
        $this->actingAsManager();

        $this->post(route('admin.permissions.store'), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_a_permission_can_be_deleted()
    {
        $this->actingAsManager();
        $permission = Permission::findOrCreate('reports.export', config('admin-kit.guard'));

        $this->delete(route('admin.permissions.destroy', $permission))
            ->assertRedirect(route('admin.permissions.index'));

        $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
    }

    public function test_bulk_delete_removes_selected_permissions()
    {
        $this->actingAsManager();
        $a = Permission::findOrCreate('reports.export', config('admin-kit.guard'));
        $b = Permission::findOrCreate('reports.view', config('admin-kit.guard'));

        $this->post(route('admin.permissions.bulk-destroy'), [
            'ids' => [$a->id, $b->id],
        ])->assertRedirect(route('admin.permissions.index'));

        $this->assertDatabaseMissing('permissions', ['id' => $a->id]);
        $this->assertDatabaseMissing('permissions', ['id' => $b->id]);
    }

    public function test_deleting_a_permission_that_still_backs_a_role_does_not_lock_out_a_super_admin()
    {
        // A Super Admin's access never depends on any specific Permission row
        // existing -- see AppServiceProvider's Gate::before() hook -- so
        // deleting a permission a non-protected role relies on is allowed,
        // and the protected role stays fully functional regardless.
        $this->actingAsManager();
        $permission = Permission::findOrCreate('reports.export', config('admin-kit.guard'));
        Role::findOrCreate('Reporter', config('admin-kit.guard'))->givePermissionTo($permission);

        $this->delete(route('admin.permissions.destroy', $permission));

        Role::findOrCreate(config('admin-kit.roles.protected_role'), config('admin-kit.guard'));
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(config('admin-kit.roles.protected_role'));
        $this->actingAs($superAdmin);

        $this->get(route('admin.permissions.index'))->assertOk();
    }
}
