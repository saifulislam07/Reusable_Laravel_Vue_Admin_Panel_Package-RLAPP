<?php

declare(strict_types=1);

namespace App\Mail\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent by the "Send test email" button on the SMTP settings tab, straight to
 * the admin who clicked it -- never to an address supplied through the
 * request, so this can't be used to relay mail to arbitrary recipients.
 */
class SmtpTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function build(): self
    {
        return $this
            ->subject(__(':brand — test email', ['brand' => config('admin-kit.brand.name')]))
            ->view('admin::mail.smtp-test');
    }
}
