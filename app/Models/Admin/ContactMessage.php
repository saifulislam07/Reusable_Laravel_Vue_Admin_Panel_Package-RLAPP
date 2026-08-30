<?php

declare(strict_types=1);

namespace App\Models\Admin;

use App\Enums\Admin\ContactMessageStatus;
use Database\Factories\Admin\ContactMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A public contact-form submission. Created only by App\Http\Controllers\
 * ContactController::store() (the public side); everything else here is
 * the admin panel reading and replying to it.
 *
 * Firing a "new message" notification is Phase 17's job, not this one's --
 * Eloquent already fires a `eloquent.created: ContactMessage` event on
 * every row this creates, which is all Phase 17 needs to hook an observer
 * or listener onto; nothing here has to change when that phase lands.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $subject
 * @property string $message
 * @property ContactMessageStatus $status
 * @property string|null $ip
 * @property string|null $reply_message
 * @property Carbon|null $replied_at
 * @property Carbon|null $created_at
 */
class ContactMessage extends Model
{
    /** @use HasFactory<ContactMessageFactory> */
    use HasFactory;

    use LogsActivity;

    protected $fillable = [
        'name', 'email', 'phone', 'subject', 'message', 'status', 'ip',
        'reply_message', 'replied_at',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('contact-messages')
            ->setDescriptionForEvent(fn (string $event): string => "Contact message from \"{$this->name}\" {$event}");
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContactMessageStatus::class,
            'replied_at' => 'datetime',
        ];
    }
}
