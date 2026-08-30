<?php

declare(strict_types=1);

namespace App\Support\Admin;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves config('admin-kit.widgets') -- a plain list of AdminWidget FQCNs
 * -- into instances, in the order they're listed. Resolution goes through
 * the container so a widget can type-hint its own dependencies, same as any
 * controller.
 */
class WidgetManager
{
    public function __construct(
        private readonly Config $config,
        private readonly Container $container,
    ) {}

    /**
     * @return list<AdminWidget>
     */
    public function all(): array
    {
        /** @var list<class-string<AdminWidget>> $classes */
        $classes = (array) $this->config->get('admin-kit.widgets', []);

        return array_map(
            fn (string $class): AdminWidget => $this->container->make($class),
            $classes,
        );
    }
}
