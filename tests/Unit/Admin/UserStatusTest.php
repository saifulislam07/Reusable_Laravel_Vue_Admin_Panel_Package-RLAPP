<?php

namespace Tests\Unit\Admin;

use App\Enums\Admin\UserStatus;
use Tests\TestCase;

class UserStatusTest extends TestCase
{
    public function test_it_has_exactly_two_cases()
    {
        $this->assertSame(['active', 'suspended'], array_column(UserStatus::cases(), 'value'));
    }

    public function test_options_maps_value_to_label()
    {
        $this->assertSame([
            'active' => 'Active',
            'suspended' => 'Suspended',
        ], UserStatus::options());
    }

    public function test_badge_variants_are_distinct()
    {
        $this->assertSame('success', UserStatus::Active->badgeVariant());
        $this->assertSame('danger', UserStatus::Suspended->badgeVariant());
    }
}
