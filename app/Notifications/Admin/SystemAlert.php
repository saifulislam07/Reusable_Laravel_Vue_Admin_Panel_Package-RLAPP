<?php

declare(strict_types=1);

namespace App\Notifications\Admin;

/**
 * A generic panel-wide alert -- currently only raised by
 * SettingsController::updateSystem() when maintenance mode is switched on,
 * so the admins who didn't flip the switch still find out the site is down
 * for visitors.
 */
class SystemAlert extends AdminAlert
{
    public function __construct(string $body, ?string $actionUrl = null, ?string $actionLabel = null)
    {
        parent::__construct(
            title: __('System alert'),
            body: $body,
            actionUrl: $actionUrl,
            actionLabel: $actionLabel,
            icon: 'alert-triangle',
        );
    }
}
