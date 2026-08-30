<?php

namespace Tests\Feature;

use App\Models\Admin\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * The public receiving end of <x-admin::contact-form /> (Phase 16):
 * App\Http\Controllers\ContactController@store and its ContactFormRequest.
 * The admin side (listing, reply, status) is covered by
 * AdminContactMessagesTest.
 */
class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('contact-form');
    }

    public function test_a_valid_submission_creates_a_message_and_records_the_ip()
    {
        $this->post(route('contact.store'), [
            'name' => 'Jane Visitor',
            'email' => 'jane@example.com',
            'phone' => '555-0100',
            'subject' => 'Question',
            'message' => 'Hello there.',
        ])->assertRedirect();

        $this->assertDatabaseHas('contact_messages', [
            'name' => 'Jane Visitor',
            'email' => 'jane@example.com',
            'message' => 'Hello there.',
        ]);

        $message = ContactMessage::query()->firstOrFail();
        $this->assertNotNull($message->ip);
    }

    public function test_name_email_and_message_are_required()
    {
        $this->post(route('contact.store'), [])
            ->assertSessionHasErrors(['name', 'email', 'message']);
    }

    public function test_the_email_must_be_a_valid_address()
    {
        $this->post(route('contact.store'), [
            'name' => 'Jane',
            'email' => 'not-an-email',
            'message' => 'Hello.',
        ])->assertSessionHasErrors('email');
    }

    public function test_phone_and_subject_are_optional()
    {
        $this->post(route('contact.store'), [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'message' => 'Hello.',
        ])->assertSessionHasNoErrors();
    }

    /**
     * <x-admin::contact-form /> renders the honeypot hidden via CSS, not
     * the `hidden` attribute -- a real visitor's browser never fills it
     * in, but a bot that does gets rejected here.
     */
    public function test_a_filled_honeypot_field_is_rejected()
    {
        $this->post(route('contact.store'), [
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'message' => 'Buy now.',
            'website' => 'http://spam.example',
        ])->assertSessionHasErrors('website');

        $this->assertDatabaseMissing('contact_messages', ['email' => 'bot@example.com']);
    }

    public function test_the_message_has_a_max_length()
    {
        $this->post(route('contact.store'), [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'message' => str_repeat('a', 5001),
        ])->assertSessionHasErrors('message');
    }

    public function test_the_endpoint_is_rate_limited()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('contact.store'), [
                'name' => 'Jane',
                'email' => 'jane@example.com',
                'message' => 'Hello.',
            ]);
        }

        $this->post(route('contact.store'), [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'message' => 'One too many.',
        ])->assertStatus(429);
    }

    public function test_the_contact_form_component_renders()
    {
        $html = view('admin::components.contact-form')->render();

        $this->assertStringContainsString('name="name"', $html);
        $this->assertStringContainsString('name="email"', $html);
        $this->assertStringContainsString('name="message"', $html);
        $this->assertStringContainsString('name="website"', $html);
    }
}
