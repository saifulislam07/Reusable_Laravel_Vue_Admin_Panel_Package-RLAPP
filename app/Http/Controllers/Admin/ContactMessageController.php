<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Admin\ContactMessageStatus;
use App\Http\Controllers\Admin\Concerns\HasBulkActions;
use App\Http\Controllers\Admin\Concerns\HasDataTable;
use App\Http\Requests\Admin\ContactMessageReplyRequest;
use App\Mail\Admin\ContactMessageReplyMail;
use App\Models\Admin\ContactMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactMessageController extends AdminController
{
    use HasBulkActions;
    use HasDataTable;

    public function index(Request $request): View
    {
        $query = ContactMessage::query();

        if (filled($status = $request->query('status'))) {
            $query->where('status', $status);
        }

        $messages = $this->paginateDataTable(
            $query,
            searchable: ['name', 'email', 'subject', 'message'],
            sortable: ['name', 'status', 'created_at'],
            defaultSort: 'created_at',
            defaultDirection: 'desc',
        );

        return view('admin::pages.contact-messages.index', [
            'messages' => $messages,
            'statusOptions' => ContactMessageStatus::options(),
            'currentStatus' => (string) $status,
        ]);
    }

    /**
     * Opening a still-new message marks it read -- the same "you've seen
     * it now" convention as any inbox, and the point of the New/Read
     * split in the first place.
     */
    public function show(ContactMessage $contactMessage): View
    {
        if ($contactMessage->status === ContactMessageStatus::New) {
            $contactMessage->update(['status' => ContactMessageStatus::Read]);
        }

        return view('admin::pages.contact-messages.show', ['message' => $contactMessage]);
    }

    /**
     * The only way a message becomes Replied -- there is no bulk "mark as
     * replied", since that status is meant to mean an actual reply went
     * out, not just that someone clicked a button.
     */
    public function reply(ContactMessageReplyRequest $request, ContactMessage $contactMessage): RedirectResponse
    {
        Mail::to($contactMessage->email)->send(
            new ContactMessageReplyMail($contactMessage, (string) $request->validated('reply')),
        );

        $contactMessage->update([
            'reply_message' => $request->validated('reply'),
            'replied_at' => now(),
            'status' => ContactMessageStatus::Replied,
        ]);

        return $this->redirectWithSuccess('admin.contact-messages.show', __('Reply sent.'), ['contactMessage' => $contactMessage]);
    }

    public function destroy(ContactMessage $contactMessage): RedirectResponse
    {
        $contactMessage->delete();

        return $this->redirectWithSuccess('admin.contact-messages.index', __('Message deleted.'));
    }

    /**
     * Named distinctly from HasBulkActions::bulkDestroy() -- see
     * UserController::bulkDelete() for why.
     */
    public function bulkDelete(Request $request): RedirectResponse
    {
        return $this->bulkDestroy($request, ContactMessage::query(), 'admin.contact-messages.index');
    }

    public function bulkMarkRead(Request $request): RedirectResponse
    {
        return $this->bulkSetStatus($request, ContactMessageStatus::Read);
    }

    public function bulkArchive(Request $request): RedirectResponse
    {
        return $this->bulkSetStatus($request, ContactMessageStatus::Archived);
    }

    private function bulkSetStatus(Request $request, ContactMessageStatus $status): RedirectResponse
    {
        $ids = $this->bulkIds($request);

        if ($ids === []) {
            $this->flashError(__('Select at least one row.'));

            return redirect()->route('admin.contact-messages.index');
        }

        $updated = ContactMessage::query()->whereKey($ids)->update(['status' => $status]);

        if ($updated > 0) {
            $this->flashSuccess(trans_choice(
                '{1}1 message updated.|[2,*]:count messages updated.',
                $updated,
                ['count' => $updated],
            ));
        }

        return redirect()->route('admin.contact-messages.index');
    }
}
