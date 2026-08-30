<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ContactFormRequest;
use App\Models\Admin\ContactMessage;
use Illuminate\Http\RedirectResponse;

/**
 * The receiving end of <x-admin::contact-form /> (Phase 16). No public
 * "view the form" route of its own -- a project embeds the component on
 * whatever page it likes; this only has to exist once, for all of them.
 */
class ContactController extends Controller
{
    public function store(ContactFormRequest $request): RedirectResponse
    {
        ContactMessage::query()->create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'subject' => $request->validated('subject'),
            'message' => $request->validated('message'),
            'ip' => $request->ip(),
        ]);

        return back()->with('status', __('Thanks -- your message has been sent.'));
    }
}
