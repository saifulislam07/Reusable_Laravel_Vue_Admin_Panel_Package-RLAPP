<?php

declare(strict_types=1);

namespace App\Support\Admin;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Builds the sidebar tree for the current request.
 *
 * Two drivers: `config` reads admin-kit.menu.items (Phase 1), `database`
 * reads the menus table (Phase 7). Both produce MenuItem objects, so the
 * views are driver-agnostic and swapping is a one-line config change.
 */
class MenuManager
{
    /** @var list<MenuItem>|null */
    private ?array $resolved = null;

    /** @var array<string, callable(): list<array<string, mixed>>> */
    private array $drivers = [];

    public function __construct(
        private readonly Config $config,
        private readonly AuthFactory $auth,
    ) {}

    /**
     * Register (or replace) a menu source.
     *
     * Phase 7 binds 'database' here from its service provider rather than
     * editing this class.
     *
     * @param  callable(): list<array<string, mixed>>  $resolver
     */
    public function extend(string $driver, callable $resolver): void
    {
        $this->drivers[$driver] = $resolver;
        $this->resolved = null;
    }

    /**
     * The permission-filtered menu tree for the current user.
     *
     * @return list<MenuItem>
     */
    public function items(): array
    {
        return $this->resolved ??= $this->build($this->rawItems());
    }

    /**
     * Drop the memoised tree -- needed when tests swap the authenticated user.
     */
    public function flush(): void
    {
        $this->resolved = null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function rawItems(): array
    {
        $driver = (string) $this->config->get('admin-kit.menu.driver', 'config');

        if (isset($this->drivers[$driver])) {
            return ($this->drivers[$driver])();
        }

        /** @var array<array-key, array<string, mixed>> $items */
        $items = (array) $this->config->get('admin-kit.menu.items', []);

        return array_values($items);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<MenuItem>
     */
    protected function build(array $items): array
    {
        $built = [];

        foreach ($items as $item) {
            if (! $this->isVisible($item)) {
                continue;
            }

            /** @var list<array<string, mixed>> $rawChildren */
            $rawChildren = array_values((array) ($item['children'] ?? []));
            $children = $this->build($rawChildren);

            // A parent that exists only to group links is pointless once every
            // child has been filtered away.
            if ($rawChildren !== [] && $children === []) {
                continue;
            }

            $built[] = MenuItem::fromArray($item, $children);
        }

        return $built;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function isVisible(array $item): bool
    {
        if (array_key_exists('is_active', $item) && ! $item['is_active']) {
            return false;
        }

        $permission = $item['permission'] ?? null;

        if (! is_string($permission) || $permission === '') {
            return true;
        }

        $user = $this->auth->guard($this->config->get('admin-kit.guard'))->user();

        return $user instanceof Authorizable && $user->can($permission);
    }
}
