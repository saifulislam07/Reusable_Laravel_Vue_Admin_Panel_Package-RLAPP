<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('roles.manage', config('admin-kit.guard'));
        Permission::findOrCreate('reports.view', config('admin-kit.guard'));
        Permission::findOrCreate('reports.export', config('admin-kit.guard'));
    }

    private function actingAsManager(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('roles.manage');
        $this->actingAs($user);

        return $user;
    }

    private function protectedRoleName(): string
    {
        return (string) config('admin-kit.roles.protected_role');
    }

    public function test_guests_cannot_reach_any_roles_route()
    {
        $this->get(route('admin.roles.index'))->assertRedirect(route('admin.login'));
    }

    public function test_a_user_without_roles_manage_is_forbidden()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.roles.index'))->assertForbidden();
    }

    public function test_the_index_page_lists_roles_with_counts()
    {
        $manager = $this->actingAsManager();
        $role = Role::findOrCreate('Reporter', config('admin-kit.guard'));
        $role->givePermissionTo('reports.view');
        $manager->assignRole($role);

        $this->get(route('admin.roles.index'))
            ->assertOk()
            ->assertSee('Reporter');
    }

    public function test_a_role_can_be_created_with_permissions()
    {
        $this->actingAsManager();

        $this->post(route('admin.roles.store'), [
            'name' => 'Reporter',
            'permissions' => ['reports.view', 'reports.export'],
        ])->assertRedirect(route('admin.roles.index'));

        $role = Role::query()->where('name', 'Reporter')->firstOrFail();
        $this->assertEqualsCanonicalizing(['reports.view', 'reports.export'], $role->permissions->pluck('name')->all());
    }

    public function test_a_role_name_must_be_unique()
    {
        $this->actingAsManager();
        Role::findOrCreate('Reporter', config('admin-kit.guard'));

        $this->post(route('admin.roles.store'), ['name' => 'Reporter'])
            ->assertSessionHasErrors('name');
    }

    public function test_a_role_can_be_created_with_no_permissions_at_all()
    {
        $this->actingAsManager();

        $this->post(route('admin.roles.store'), ['name' => 'Empty Role'])
            ->assertRedirect(route('admin.roles.index'));

        $this->assertSame([], Role::query()->where('name', 'Empty Role')->firstOrFail()->permissions->pluck('name')->all());
    }

    public function test_the_edit_form_shows_currently_assigned_permissions()
    {
        $this->actingAsManager();
        $role = Role::findOrCreate('Reporter', config('admin-kit.guard'));
        $role->givePermissionTo('reports.view');

        $this->get(route('admin.roles.edit', $role))
            ->assertOk()
            ->assertSee('checked', escape: false)
            ->assertSee('reports.view');
    }

    public function test_updating_a_role_renames_it_and_resyncs_permissions()
    {
        $this->actingAsManager();
        $role = Role::findOrCreate('Reporter', config('admin-kit.guard'));
        $role->givePermissionTo('reports.view');

        $this->put(route('admin.roles.update', $role), [
            'name' => 'Senior Reporter',
            'permissions' => ['reports.export'],
        ])->assertRedirect(route('admin.roles.index'));

        $role->refresh();
        $this->assertSame('Senior Reporter', $role->name);
        $this->assertSame(['reports.export'], $role->permissions->pluck('name')->all());
    }

    public function test_a_role_can_be_deleted()
    {
        $this->actingAsManager();
        $role = Role::findOrCreate('Reporter', config('admin-kit.guard'));

        $this->delete(route('admin.roles.destroy', $role))
            ->assertRedirect(route('admin.roles.index'));

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_the_protected_role_cannot_be_deleted()
    {
        $this->actingAsManager();
        $role = Role::findOrCreate($this->protectedRoleName(), config('admin-kit.guard'));

        $this->delete(route('admin.roles.destroy', $role))
            ->assertRedirect(route('admin.roles.index'));

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_the_protected_role_cannot_be_renamed_or_stripped_of_permissions()
    {
        $this->actingAsManager();
        $role = Role::findOrCreate($this->protectedRoleName(), config('admin-kit.guard'));
        $role->givePermissionTo(Permission::query()->get());

        $this->put(route('admin.roles.update', $role), [
            'name' => 'Renamed Away',
            'permissions' => [], // an attempt to strip every permission
        ])->assertRedirect(route('admin.roles.index'));

        $role->refresh();
        $this->assertSame($this->protectedRoleName(), $role->name);
        $this->assertEqualsCanonicalizing(
            Permission::query()->pluck('name')->all(),
            $role->permissions->pluck('name')->all(),
        );
    }

    public function test_the_protected_roles_edit_page_offers_no_editable_form()
    {
        $this->actingAsManager();
        $role = Role::findOrCreate($this->protectedRoleName(), config('admin-kit.guard'));

        // The page has a form regardless -- the topbar's own sign-out button
        // is one -- so what actually matters is that nothing posts back to
        // the update route, and there's nothing left to submit.
        $this->get(route('admin.roles.edit', $role))
            ->assertOk()
            ->assertDontSee(route('admin.roles.update', $role))
            ->assertDontSee('Save changes')
            ->assertSee('cannot be renamed or deleted');
    }

    public function test_bulk_delete_removes_selected_roles_but_never_the_protected_one()
    {
        $this->actingAsManager();
        $protected = Role::findOrCreate($this->protectedRoleName(), config('admin-kit.guard'));
        $victim = Role::findOrCreate('Temp Role', config('admin-kit.guard'));

        $this->post(route('admin.roles.bulk-destroy'), [
            'ids' => [$protected->id, $victim->id],
        ])->assertRedirect(route('admin.roles.index'));

        $this->assertDatabaseHas('roles', ['id' => $protected->id]);
        $this->assertDatabaseMissing('roles', ['id' => $victim->id]);
    }

    public function test_the_permission_matrix_is_grouped_by_module()
    {
        $this->actingAsManager();

        $this->get(route('admin.roles.create'))
            ->assertOk()
            ->assertSee('Reports')
            ->assertSee('reports.view')
            ->assertSee('reports.export');
    }
}
