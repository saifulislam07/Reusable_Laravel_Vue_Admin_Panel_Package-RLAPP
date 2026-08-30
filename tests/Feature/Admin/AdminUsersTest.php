<?php

namespace Tests\Feature\Admin;

use App\Enums\Admin\UserStatus;
use App\Models\Admin\MediaFolder;
use App\Models\User;
use App\Notifications\Admin\AdminUserInvited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The Users page: listing (DataTable, Phase 5), full CRUD, role/direct-
 * permission assignment, invite-by-email, and status (Phase 8). Every route
 * is permission-gated (users.view/create/update/delete) as of this phase --
 * see routes/admin.php.
 */
class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['users.view', 'users.create', 'users.update', 'users.delete'] as $name) {
            Permission::findOrCreate($name, config('admin-kit.guard'));
        }

        Storage::fake((string) config('media-library.disk_name'));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function actingAsManager(array $permissions = ['users.view', 'users.create', 'users.update', 'users.delete']): User
    {
        $user = User::factory()->create(['name' => 'Signed-In Admin']);
        $user->givePermissionTo($permissions);
        $this->actingAs($user);

        return $user;
    }

    // -- access control ---------------------------------------------------

    public function test_guests_cannot_reach_the_users_page()
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('admin.login'));
    }

    public function test_a_user_without_users_view_is_forbidden()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_users_view_alone_does_not_grant_create_access()
    {
        $this->actingAsManager(['users.view']);

        $this->get(route('admin.users.create'))->assertForbidden();
    }

    public function test_users_view_alone_does_not_grant_update_access()
    {
        $this->actingAsManager(['users.view']);
        $target = User::factory()->create();

        $this->get(route('admin.users.edit', $target))->assertForbidden();
    }

    public function test_users_view_alone_does_not_grant_delete_access()
    {
        $this->actingAsManager(['users.view']);
        $target = User::factory()->create();

        $this->delete(route('admin.users.destroy', $target))->assertForbidden();
    }

    // -- listing ------------------------------------------------------------

    public function test_it_lists_users()
    {
        $this->actingAsManager();
        User::factory()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);

        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Grace Hopper')
            ->assertSee('grace@example.com')
            ->assertSee('Signed-In Admin');
    }

    public function test_the_datatable_component_and_pagination_render()
    {
        $this->actingAsManager();

        $this->get(route('admin.users.index'))
            ->assertSee('nk-datatable', escape: false)
            ->assertSee('nk-table', escape: false);
    }

    public function test_users_appears_in_the_sidebar_menu()
    {
        $this->actingAsManager();

        $this->get(route('admin.dashboard'))
            ->assertSee('Users')
            ->assertSee(route('admin.users.index'));
    }

    public function test_searching_filters_the_list()
    {
        $this->actingAsManager();
        User::factory()->create(['name' => 'Ada Lovelace']);
        User::factory()->create(['name' => 'Grace Hopper']);

        $response = $this->get(route('admin.users.index', ['q' => 'Lovelace']));

        $response->assertSee('Ada Lovelace')->assertDontSee('Grace Hopper');
    }

    public function test_sorting_by_name_changes_the_row_order()
    {
        $this->actingAsManager(['users.view']);
        User::factory()->create(['name' => 'Zed']);
        User::factory()->create(['name' => 'Amy']);

        $html = $this->get(route('admin.users.index', ['sort' => 'name', 'direction' => 'asc']))
            ->getContent();

        $amyPos = strpos($html, 'Amy');
        $zedPos = strpos($html, 'Zed');

        $this->assertNotFalse($amyPos);
        $this->assertNotFalse($zedPos);
        $this->assertLessThan($zedPos, $amyPos);
    }

    public function test_pagination_links_appear_once_there_are_enough_rows()
    {
        $this->actingAsManager();
        User::factory()->count(20)->create();

        $this->get(route('admin.users.index'))->assertSee('nk-pagination', escape: false);
    }

    public function test_the_role_filter_narrows_the_list()
    {
        $this->actingAsManager();
        Role::findOrCreate('Reporter', config('admin-kit.guard'));

        $inRole = User::factory()->create(['name' => 'In Role']);
        $inRole->assignRole('Reporter');
        User::factory()->create(['name' => 'Not In Role']);

        $this->get(route('admin.users.index', ['role' => 'Reporter']))
            ->assertSee('In Role')
            ->assertDontSee('Not In Role');
    }

    // -- create ---------------------------------------------------------

    public function test_a_user_can_be_created_with_a_password()
    {
        $this->actingAsManager();

        $this->post(route('admin.users.store'), [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'a-strong-password-1',
            'status' => 'active',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'status' => 'active']);
    }

    /**
     * Phase 8 deliberately skipped avatar upload, waiting on the Media
     * Library (Phase 12) -- this closes that loop: an avatar is a Media
     * row picked via <x-admin::form.media-picker>, not its own upload flow.
     */
    public function test_a_user_can_be_created_with_an_avatar_picked_from_the_media_library()
    {
        $this->actingAsManager();
        $media = MediaFolder::default()
            ->addMedia(UploadedFile::fake()->image('avatar.jpg'))
            ->toMediaCollection();

        $this->post(route('admin.users.store'), [
            'name' => 'New User',
            'email' => 'avatar-user@example.com',
            'password' => 'a-strong-password-1',
            'status' => 'active',
            'avatar_media_id' => $media->id,
        ])->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', 'avatar-user@example.com')->firstOrFail();
        $this->assertSame($media->id, $user->avatar_media_id);
        $this->assertNotNull($user->avatarUrl());
    }

    public function test_a_user_can_be_created_without_an_avatar()
    {
        $this->actingAsManager();

        $this->post(route('admin.users.store'), [
            'name' => 'New User',
            'email' => 'no-avatar@example.com',
            'password' => 'a-strong-password-1',
            'status' => 'active',
        ])->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'no-avatar@example.com')->firstOrFail();
        $this->assertNull($user->avatar_media_id);
        $this->assertNull($user->avatarUrl());
    }

    public function test_email_must_be_unique()
    {
        $this->actingAsManager();
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post(route('admin.users.store'), [
            'name' => 'New User',
            'email' => 'taken@example.com',
            'password' => 'a-strong-password-1',
            'status' => 'active',
        ])->assertSessionHasErrors('email');
    }

    public function test_a_password_is_required_unless_inviting()
    {
        $this->actingAsManager();

        $this->post(route('admin.users.store'), [
            'name' => 'New User',
            'email' => 'new@example.com',
            'status' => 'active',
        ])->assertSessionHasErrors('password');
    }

    public function test_a_user_can_be_created_with_roles_and_direct_permissions()
    {
        $this->actingAsManager();
        Role::findOrCreate('Reporter', config('admin-kit.guard'));
        Permission::findOrCreate('reports.export', config('admin-kit.guard'));

        $this->post(route('admin.users.store'), [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'a-strong-password-1',
            'status' => 'active',
            'roles' => ['Reporter'],
            'permissions' => ['reports.export'],
        ]);

        $user = User::query()->where('email', 'new@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('Reporter'));
        $this->assertTrue($user->hasDirectPermission('reports.export'));
    }

    // -- invite -----------------------------------------------------------

    public function test_inviting_creates_the_user_without_a_usable_password_and_sends_an_email()
    {
        Notification::fake();
        $this->actingAsManager();

        $this->post(route('admin.users.store'), [
            'name' => 'Invited User',
            'email' => 'invited@example.com',
            'invite' => '1',
            'status' => 'active',
        ])->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', 'invited@example.com')->firstOrFail();

        $this->assertFalse(Hash::check('', $user->password));
        Notification::assertSentTo($user, AdminUserInvited::class);
    }

    public function test_the_invite_link_lets_the_new_user_set_their_own_password()
    {
        Notification::fake();
        $this->actingAsManager();

        $this->post(route('admin.users.store'), [
            'name' => 'Invited User',
            'email' => 'invited@example.com',
            'invite' => '1',
            'status' => 'active',
        ]);

        $user = User::query()->where('email', 'invited@example.com')->firstOrFail();

        $token = null;
        Notification::assertSentTo($user, AdminUserInvited::class, function (AdminUserInvited $n) use (&$token): bool {
            $token = $n->token;

            return true;
        });

        // The invited user opening this link is a guest, not the manager
        // who's still signed in above -- the reset route is guest-only, so
        // an authenticated request to it would redirect to the dashboard
        // instead of ever reaching the controller.
        auth()->logout();

        $this->post(route('admin.password.update'), [
            'token' => $token,
            'email' => 'invited@example.com',
            'password' => 'their-own-new-password',
            'password_confirmation' => 'their-own-new-password',
        ])->assertRedirect(route('admin.login'));

        $this->assertTrue(Hash::check('their-own-new-password', $user->fresh()->password));
    }

    public function test_a_password_typed_alongside_invite_is_ignored()
    {
        Notification::fake();
        $this->actingAsManager();

        $this->post(route('admin.users.store'), [
            'name' => 'Invited User',
            'email' => 'invited@example.com',
            'password' => 'should-be-ignored',
            'invite' => '1',
            'status' => 'active',
        ]);

        $user = User::query()->where('email', 'invited@example.com')->firstOrFail();

        $this->assertFalse(Hash::check('should-be-ignored', $user->password));
    }

    // -- edit / update ----------------------------------------------------

    public function test_the_edit_form_prefills_current_values()
    {
        $this->actingAsManager();
        $target = User::factory()->create(['name' => 'Existing User', 'email' => 'existing@example.com']);

        $this->get(route('admin.users.edit', $target))
            ->assertOk()
            ->assertSee('value="Existing User"', escape: false)
            ->assertSee('value="existing@example.com"', escape: false);
    }

    public function test_a_user_can_be_updated()
    {
        $this->actingAsManager();
        $target = User::factory()->create(['name' => 'Old Name']);

        $this->put(route('admin.users.update', $target), [
            'name' => 'New Name',
            'email' => $target->email,
            'status' => 'active',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertSame('New Name', $target->fresh()->name);
    }

    public function test_a_users_avatar_can_be_set_and_cleared_on_update()
    {
        $this->actingAsManager();
        $target = User::factory()->create();
        $media = MediaFolder::default()
            ->addMedia(UploadedFile::fake()->image('new-avatar.jpg'))
            ->toMediaCollection();

        $this->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'status' => 'active',
            'avatar_media_id' => $media->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($media->id, $target->fresh()->avatar_media_id);

        $this->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'status' => 'active',
        ])->assertSessionHasNoErrors();

        $this->assertNull($target->fresh()->avatar_media_id);
    }

    public function test_leaving_the_password_blank_on_update_keeps_the_existing_one()
    {
        $this->actingAsManager();
        $target = User::factory()->create();
        $originalHash = $target->password;

        $this->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'password' => '',
            'status' => 'active',
        ]);

        $this->assertSame($originalHash, $target->fresh()->password);
    }

    public function test_providing_a_new_password_on_update_changes_it()
    {
        $this->actingAsManager();
        $target = User::factory()->create();

        $this->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'password' => 'brand-new-password-1',
            'status' => 'active',
        ]);

        $this->assertTrue(Hash::check('brand-new-password-1', $target->fresh()->password));
    }

    public function test_updating_resyncs_roles_and_direct_permissions()
    {
        $this->actingAsManager();
        Role::findOrCreate('Reporter', config('admin-kit.guard'));
        $target = User::factory()->create();
        $target->assignRole('Reporter');

        $this->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'status' => 'active',
            'roles' => [], // removes the role
        ]);

        $this->assertFalse($target->fresh()->hasRole('Reporter'));
    }

    // -- status / suspension ----------------------------------------------

    public function test_a_user_can_be_suspended()
    {
        $this->actingAsManager();
        $target = User::factory()->create(['status' => UserStatus::Active]);

        $this->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'status' => 'suspended',
        ]);

        $this->assertSame(UserStatus::Suspended, $target->fresh()->status);
    }

    public function test_a_suspended_user_cannot_log_in()
    {
        $target = User::factory()->create([
            'password' => bcrypt('correct-password'),
            'status' => UserStatus::Suspended,
        ]);

        $this->post(route('admin.login.attempt'), [
            'email' => $target->email,
            'password' => 'correct-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_the_suspended_message_is_specific_not_generic()
    {
        $target = User::factory()->create([
            'password' => bcrypt('correct-password'),
            'status' => UserStatus::Suspended,
        ]);

        $response = $this->from(route('admin.login'))->post(route('admin.login.attempt'), [
            'email' => $target->email,
            'password' => 'correct-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('suspended', (string) session('errors')->first('email'));
    }

    public function test_an_active_user_can_still_log_in()
    {
        $target = User::factory()->create([
            'password' => bcrypt('correct-password'),
            'status' => UserStatus::Active,
        ]);

        $this->post(route('admin.login.attempt'), [
            'email' => $target->email,
            'password' => 'correct-password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($target);
    }

    // -- single delete ----------------------------------------------------

    public function test_a_user_can_be_deleted()
    {
        $this->actingAsManager();
        $target = User::factory()->create();

        $this->delete(route('admin.users.destroy', $target))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_a_user_cannot_delete_their_own_account()
    {
        $actor = $this->actingAsManager();

        $this->delete(route('admin.users.destroy', $actor))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['id' => $actor->id]);
    }

    // -- bulk delete --------------------------------------------------------

    public function test_bulk_delete_removes_the_selected_users()
    {
        $this->actingAsManager();
        $victims = User::factory()->count(2)->create();

        $this->post(route('admin.users.bulk-destroy'), [
            'ids' => $victims->pluck('id')->all(),
        ])->assertRedirect(route('admin.users.index'));

        foreach ($victims as $victim) {
            $this->assertDatabaseMissing('users', ['id' => $victim->id]);
        }
    }

    public function test_bulk_delete_cannot_remove_the_signed_in_user_even_if_submitted()
    {
        $actor = $this->actingAsManager();

        $this->post(route('admin.users.bulk-destroy'), [
            'ids' => [$actor->id],
        ])->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['id' => $actor->id]);
    }

    public function test_bulk_delete_removes_others_even_when_the_actor_id_is_also_submitted()
    {
        $actor = $this->actingAsManager();
        $victim = User::factory()->create();

        $this->post(route('admin.users.bulk-destroy'), [
            'ids' => [$actor->id, $victim->id],
        ]);

        $this->assertDatabaseHas('users', ['id' => $actor->id]);
        $this->assertDatabaseMissing('users', ['id' => $victim->id]);
    }

    public function test_bulk_delete_requires_at_least_one_id()
    {
        $this->actingAsManager();

        $this->post(route('admin.users.bulk-destroy'), ['ids' => []])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseCount('users', 1);
    }

    public function test_bulk_delete_ignores_ids_that_are_not_a_valid_shape()
    {
        $this->actingAsManager();
        $victim = User::factory()->create();

        // An array or object where a scalar id is expected must not blow up
        // the query (mass-assignment-style probing).
        $this->post(route('admin.users.bulk-destroy'), [
            'ids' => [$victim->id, ['nested' => 'value'], null],
        ])->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $victim->id]);
    }

    public function test_bulk_delete_confirmation_is_wired_up_client_side()
    {
        $this->actingAsManager();
        User::factory()->create();

        $this->get(route('admin.users.index'))
            ->assertSee('data-nk-confirm', escape: false)
            ->assertSee('Delete the selected users?');
    }

    public function test_guests_cannot_bulk_delete()
    {
        $victim = User::factory()->create();

        $this->post(route('admin.users.bulk-destroy'), ['ids' => [$victim->id]])
            ->assertRedirect(route('admin.login'));

        $this->assertDatabaseHas('users', ['id' => $victim->id]);
    }
}
