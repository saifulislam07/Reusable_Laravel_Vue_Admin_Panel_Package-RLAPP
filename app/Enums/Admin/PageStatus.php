<?php

declare(strict_types=1);

namespace App\Enums\Admin;

enum PageStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Scheduled = 'scheduled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Published => __('Published'),
            self::Scheduled => __('Scheduled'),
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::Published => 'success',
            self::Scheduled => 'info',
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
