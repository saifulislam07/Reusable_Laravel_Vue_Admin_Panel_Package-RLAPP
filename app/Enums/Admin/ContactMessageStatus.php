<?php

declare(strict_types=1);

namespace App\Enums\Admin;

enum ContactMessageStatus: string
{
    case New = 'new';
    case Read = 'read';
    case Replied = 'replied';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::New => __('New'),
            self::Read => __('Read'),
            self::Replied => __('Replied'),
            self::Archived => __('Archived'),
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Read => 'primary',
            self::Replied => 'success',
            self::Archived => 'warning',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(
            array_map(fn (self $case): string => $case->value, self::cases()),
            array_map(fn (self $case): string => $case->label(), self::cases()),
        );
    }
}
