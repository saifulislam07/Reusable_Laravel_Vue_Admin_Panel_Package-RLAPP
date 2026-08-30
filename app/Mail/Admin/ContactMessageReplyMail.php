<?php

declare(strict_types=1);

namespace App\Mail\Admin;

use App\Models\Admin\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent by ContactMessageController::reply() to the address the visitor
 * submitted the form with -- goes through whatever SMTP settings are
 * configured (Phase 6), same as every other Mailable in this app, with no
 * extra wiring: IntegrationSettings::applyRuntimeMailConfig() already runs
 * on every request.
 */
class ContactMessageReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ContactMessage $contactMessage,
        public readonly string $reply,
    ) {}

    public function build(): self
    {
        return $this
            ->subject(__('Re: :subject', [
                'subject' => $this->contactMessage->subject ?: __('Your message to :brand', ['brand' => config('admin-kit.brand.name')]),
            ]))
            ->view('admin::mail.contact-reply');
    }
}
