<?php

namespace Tests\Feature\Admin;

use App\Enums\Admin\ContactMessageStatus;
use App\Mail\Admin\ContactMessageReplyMail;
use App\Models\Admin\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The admin side of Contact Messages (Phase 16): listing (DataTable) with
 * status filter, bulk status updates + bulk delete, and the detail/reply
 * view. Public submission is covered by ContactFormTest.
 */
class AdminContactMessagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['contact-messages.view', 'contact-messages.manage', 'contact-messages.delete'] as $name) {
            Permission::findOrCreate($name, config('admin-kit.guard'));
        }
    }

    /**
     * @param  list<string>  $permissions
     */
    private function actingAsManager(array $permissions = ['contact-messages.view', 'contact-messages.manage', 'contact-messages.delete']): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        $this->actingAs($user);

        return $user;
    }

    // -- access control ---------------------------------------------------

    public function test_guests_cannot_reach_the_module()
    {
        $this->get(route('admin.contact-messages.index'))->assertRedirect(route('admin.login'));
    }

    public function test_a_user_without_view_is_forbidden()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.contact-messages.index'))->assertForbidden();
    }

    public function test_view_alone_does_not_grant_reply_access()
    {
        $this->actingAsManager(['contact-messages.view']);
        $message = ContactMessage::factory()->create();

        $this->post(route('admin.contact-messages.reply', $message), ['reply' => 'Hi'])
            ->assertForbidden();
    }

    public function test_view_alone_does_not_grant_delete_access()
    {
        $this->actingAsManager(['contact-messages.view']);
        $message = ContactMessage::factory()->create();

        $this->delete(route('admin.contact-messages.destroy', $message))->assertForbidden();
    }

    // -- listing ------------------------------------------------------------

    public function test_the_index_lists_messages()
    {
        $this->actingAsManager();
        ContactMessage::factory()->create(['name' => 'Grace Hopper', 'subject' => 'Question']);

        $this->get(route('admin.contact-messages.index'))
            ->assertOk()
            ->assertSee('Grace Hopper')
            ->assertSee('Question');
    }

    public function test_the_status_filter_narrows_the_list()
    {
        $this->actingAsManager();
        ContactMessage::factory()->create(['name' => 'New One']);
        ContactMessage::factory()->archived()->create(['name' => 'Archived One']);

        $this->get(route('admin.contact-messages.index', ['status' => 'archived']))
            ->assertSee('Archived One')
            ->assertDontSee('New One');
    }

    public function test_search_matches_the_message_body()
    {
        $this->actingAsManager();
        ContactMessage::factory()->create(['name' => 'Alice', 'message' => 'A very unique phrase right here']);
        ContactMessage::factory()->create(['name' => 'Bob', 'message' => 'Something else entirely']);

        $this->get(route('admin.contact-messages.index', ['q' => 'unique phrase']))
            ->assertSee('Alice')
            ->assertDontSee('Bob');
    }

    // -- show / auto-read ---------------------------------------------------

    public function test_opening_a_new_message_marks_it_read()
    {
        $this->actingAsManager();
        $message = ContactMessage::factory()->create();
        $this->assertSame(ContactMessageStatus::New, $message->status);

        $this->get(route('admin.contact-messages.show', $message))->assertOk();

        $this->assertSame(ContactMessageStatus::Read, $message->fresh()->status);
    }

    public function test_opening_an_already_replied_message_does_not_change_its_status()
    {
        $this->actingAsManager();
        $message = ContactMessage::factory()->replied()->create();

        $this->get(route('admin.contact-messages.show', $message));

        $this->assertSame(ContactMessageStatus::Replied, $message->fresh()->status);
    }

    // -- reply ------------------------------------------------------------

    public function test_replying_sends_an_email_and_marks_the_message_replied()
    {
        Mail::fake();
        $this->actingAsManager();
        $message = ContactMessage::factory()->create(['email' => 'visitor@example.com']);

        $this->post(route('admin.contact-messages.reply', $message), [
            'reply' => 'Thanks for reaching out, here is the answer.',
        ])->assertRedirect(route('admin.contact-messages.show', $message));

        $message->refresh();
        $this->assertSame(ContactMessageStatus::Replied, $message->status);
        $this->assertSame('Thanks for reaching out, here is the answer.', $message->reply_message);
        $this->assertNotNull($message->replied_at);

        Mail::assertSent(
            ContactMessageReplyMail::class,
            fn ($mail) => $mail->hasTo('visitor@example.com') && $mail->reply === 'Thanks for reaching out, here is the answer.',
        );
    }

    public function test_a_reply_cannot_be_blank()
    {
        $this->actingAsManager();
        $message = ContactMessage::factory()->create();

        $this->post(route('admin.contact-messages.reply', $message), ['reply' => ''])
            ->assertSessionHasErrors('reply');
    }

    // -- delete -----------------------------------------------------------

    public function test_a_message_can_be_deleted()
    {
        $this->actingAsManager();
        $message = ContactMessage::factory()->create();

        $this->delete(route('admin.contact-messages.destroy', $message))
            ->assertRedirect(route('admin.contact-messages.index'));

        $this->assertDatabaseMissing('contact_messages', ['id' => $message->id]);
    }

    public function test_messages_can_be_bulk_deleted()
    {
        $this->actingAsManager();
        $messages = ContactMessage::factory()->count(3)->create();

        $this->post(route('admin.contact-messages.bulk-destroy'), [
            'ids' => $messages->pluck('id')->all(),
        ])->assertRedirect(route('admin.contact-messages.index'));

        $this->assertSame(0, ContactMessage::query()->count());
    }

    // -- bulk status updates -------------------------------------------------

    public function test_messages_can_be_bulk_marked_read()
    {
        $this->actingAsManager();
        $messages = ContactMessage::factory()->count(2)->create();

        $this->post(route('admin.contact-messages.bulk-mark-read'), [
            'ids' => $messages->pluck('id')->all(),
        ])->assertRedirect(route('admin.contact-messages.index'));

        $this->assertSame(2, ContactMessage::query()->where('status', ContactMessageStatus::Read)->count());
    }

    public function test_messages_can_be_bulk_archived()
    {
        $this->actingAsManager();
        $messages = ContactMessage::factory()->count(2)->create();

        $this->post(route('admin.contact-messages.bulk-archive'), [
            'ids' => $messages->pluck('id')->all(),
        ])->assertRedirect(route('admin.contact-messages.index'));

        $this->assertSame(2, ContactMessage::query()->where('status', ContactMessageStatus::Archived)->count());
    }

    public function test_bulk_actions_require_at_least_one_id()
    {
        $this->actingAsManager();

        $this->post(route('admin.contact-messages.bulk-mark-read'), ['ids' => []])
            ->assertRedirect(route('admin.contact-messages.index'));

        $this->assertSame('Select at least one row.', session('error'));
    }
}
