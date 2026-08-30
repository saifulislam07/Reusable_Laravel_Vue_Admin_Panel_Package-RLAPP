<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\ContactMessage;
use App\Models\User;
use App\Notifications\Admin\NewContactMessage;
use App\Notifications\Admin\NewUserRegistered;
use App\Notifications\Admin\SystemAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The notification system (Phase 17): recipient resolution (User::admins()),
 * the three concrete alerts and what triggers each of them
 * (ContactMessageObserver, UserController::store(),
 * SettingsController::updateSystem()), the self-service NotificationController,
 * and the topbar unread badge.
 */
class AdminNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(string $role = 'Admin'): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($role, config('admin-kit.guard')));

        return $user;
    }

    // -- recipient resolution ---------------------------------------------

    public function test_the_admins_scope_only_matches_users_with_a_role()
    {
        $withRole = $this->adminUser();
        $withoutRole = User::factory()->create();

        $this->assertTrue(User::query()->admins()->whereKey($withRole->id)->exists());
        $this->assertFalse(User::query()->admins()->whereKey($withoutRole->id)->exists());
    }

    // -- ContactMessageObserver / NewContactMessage ------------------------

    public function test_a_new_contact_message_notifies_admin_users()
    {
        Notification::fake();
        $admin = $this->adminUser();
        User::factory()->create();

        $message = ContactMessage::factory()->create();

        Notification::assertSentTo(
            $admin,
            NewContactMessage::class,
            fn ($notification) => $notification->actionUrl === route('admin.contact-messages.show', $message),
        );
    }

    public function test_a_new_contact_message_does_not_notify_users_without_a_role()
    {
        Notification::fake();
        User::factory()->create();

        ContactMessage::factory()->create();

        Notification::assertNothingSent();
    }

    public function test_a_new_contact_message_notification_is_persisted_to_the_database()
    {
        $admin = $this->adminUser();

        ContactMessage::factory()->create(['name' => 'Grace Hopper']);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $admin->id,
            'type' => NewContactMessage::class,
        ]);
    }

    // -- UserController::store() / NewUserRegistered -----------------------

    public function test_creating_a_user_notifies_other_admins_but_not_the_actor_or_the_new_user()
    {
        Notification::fake();
        Permission::findOrCreate('users.view', config('admin-kit.guard'));
        Permission::findOrCreate('users.create', config('admin-kit.guard'));

        $actor = $this->adminUser();
        $actor->givePermissionTo(['users.view', 'users.create']);
        $otherAdmin = $this->adminUser();

        $this->actingAs($actor);

        $this->post(route('admin.users.store'), [
            'name' => 'New User',
            'email' => 'new-admin@example.com',
            'password' => 'a-strong-password-1',
            'status' => 'active',
        ])->assertRedirect(route('admin.users.index'));

        $newUser = User::query()->where('email', 'new-admin@example.com')->firstOrFail();

        Notification::assertSentTo($otherAdmin, NewUserRegistered::class);
        Notification::assertNotSentTo($actor, NewUserRegistered::class);
        Notification::assertNotSentTo($newUser, NewUserRegistered::class);
    }

    // -- SettingsController::updateSystem() / SystemAlert ------------------

    public function test_turning_on_maintenance_mode_notifies_other_admins_but_not_the_actor()
    {
        Notification::fake();
        Permission::findOrCreate('settings.manage', config('admin-kit.guard'));

        $actor = $this->adminUser();
        $actor->givePermissionTo('settings.manage');
        $otherAdmin = $this->adminUser();

        $this->actingAs($actor);

        $this->post(route('admin.settings.system.update'), [
            'maintenance_mode' => '1',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
            'currency_position' => 'prefix',
            'backup_schedule' => 'daily',
        ]);

        Notification::assertSentTo($otherAdmin, SystemAlert::class);
        Notification::assertNotSentTo($actor, SystemAlert::class);
    }

    public function test_saving_system_settings_again_while_still_on_does_not_re_notify()
    {
        Permission::findOrCreate('settings.manage', config('admin-kit.guard'));

        $actor = $this->adminUser();
        $actor->givePermissionTo('settings.manage');
        $this->adminUser();

        $this->actingAs($actor);

        $this->post(route('admin.settings.system.update'), [
            'maintenance_mode' => '1',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
            'currency_position' => 'prefix',
            'backup_schedule' => 'daily',
        ]);

        Notification::fake();

        $this->post(route('admin.settings.system.update'), [
            'maintenance_mode' => '1',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
            'currency_position' => 'prefix',
            'backup_schedule' => 'daily',
        ]);

        Notification::assertNothingSent();
    }

    // -- NotificationController (self-service) ------------------------------

    public function test_guests_cannot_reach_notifications()
    {
        $this->get(route('admin.notifications.index'))->assertRedirect(route('admin.login'));
    }

    public function test_the_index_lists_the_authenticated_users_own_notifications()
    {
        $admin = $this->adminUser();
        $this->actingAs($admin);

        ContactMessage::factory()->create(['name' => 'Ada Lovelace']);

        $this->get(route('admin.notifications.index'))
            ->assertOk()
            ->assertSee('New contact message')
            ->assertSee('Ada Lovelace');
    }

    public function test_marking_a_notification_read_marks_it_read_and_redirects_to_its_action_url()
    {
        $admin = $this->adminUser();
        $this->actingAs($admin);
        $message = ContactMessage::factory()->create();

        $notification = $admin->notifications()->firstOrFail();
        $this->assertNull($notification->read_at);

        $this->post(route('admin.notifications.read', $notification->id))
            ->assertRedirect(route('admin.contact-messages.show', $message));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_marking_a_notification_unread_clears_its_read_at()
    {
        $admin = $this->adminUser();
        $this->actingAs($admin);
        ContactMessage::factory()->create();

        $notification = $admin->notifications()->firstOrFail();
        $notification->markAsRead();

        $this->post(route('admin.notifications.unread', $notification->id))
            ->assertRedirect(route('admin.notifications.index'));

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_read_clears_every_unread_notification()
    {
        $admin = $this->adminUser();
        $this->actingAs($admin);
        ContactMessage::factory()->count(3)->create();

        $this->assertSame(3, $admin->unreadNotifications()->count());

        $this->post(route('admin.notifications.mark-all-read'))->assertRedirect();

        $this->assertSame(0, $admin->unreadNotifications()->count());
    }

    /**
     * The scoped $request->user()->notifications() lookup, not an unscoped
     * route-model-bound DatabaseNotification, is what stops one admin from
     * reading or clearing another admin's notification by guessing its UUID.
     */
    public function test_a_user_cannot_read_another_admins_notification()
    {
        $owner = $this->adminUser();
        $intruder = $this->adminUser();

        ContactMessage::factory()->create();
        $notification = $owner->notifications()->firstOrFail();

        $this->actingAs($intruder);

        $this->post(route('admin.notifications.read', $notification->id))->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }

    // -- topbar ---------------------------------------------------------------

    public function test_the_topbar_shows_an_unread_badge_when_there_are_unread_notifications()
    {
        $admin = $this->adminUser();
        $this->actingAs($admin);
        ContactMessage::factory()->create();

        $this->get(route('admin.dashboard'))->assertOk()->assertSee('nk-iconbtn__dot', false);
    }

    public function test_the_topbar_shows_no_badge_once_everything_is_read()
    {
        $admin = $this->adminUser();
        $this->actingAs($admin);
        ContactMessage::factory()->create();
        $admin->unreadNotifications->markAsRead();

        $this->get(route('admin.dashboard'))->assertOk()->assertDontSee('nk-iconbtn__dot', false);
    }
}
