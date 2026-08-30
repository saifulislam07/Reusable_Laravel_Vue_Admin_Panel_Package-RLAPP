<?php

declare(strict_types=1);

namespace App\Enums\Admin;

enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Suspended => __('Suspended'),
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Suspended => 'danger',
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
