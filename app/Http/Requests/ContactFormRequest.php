<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Backs <x-admin::contact-form /> (Phase 16) -- a project drops that
 * component into whatever public page it wants a contact form on, and this
 * is the validation its POST always goes through, regardless of which page
 * it was submitted from.
 */
class ContactFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            // A blank honeypot field means a human filled the form out --
            // <x-admin::contact-form /> renders it hidden via CSS, not the
            // `hidden` attribute, since most bots skip fields real browsers
            // would never show regardless of markup, but some specifically
            // ignore ones marked hidden="hidden" in an attempt to dodge
            // exactly this trick.
            'website' => ['prohibited'],
        ];
    }
}
