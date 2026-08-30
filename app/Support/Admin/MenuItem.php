<?php

declare(strict_types=1);

namespace App\Support\Admin;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Route;

/**
 * One resolved sidebar entry.
 *
 * Built by MenuManager from whatever the active driver returns (config array
 * today, menus table from Phase 7), so views never touch the raw source.
 *
 * @implements Arrayable<string, mixed>
 */
final class MenuItem implements Arrayable
{
    /**
     * @param  list<MenuItem>  $children
     * @param  list<string>  $activePatterns  Extra route-name patterns that also light this item up.
     */
    public function __construct(
        public readonly string $label,
        public readonly ?string $icon = null,
        public readonly ?string $url = null,
        public readonly ?string $routeName = null,
        public readonly ?string $permission = null,
        public readonly array $activePatterns = [],
        public readonly ?string $badge = null,
        public readonly ?string $badgeVariant = null,
        public readonly array $children = [],
    ) {}

    /**
     * @param  array<string, mixed>  $item
     * @param  list<MenuItem>  $children
     */
    public static function fromArray(array $item, array $children = []): self
    {
        /** @var array<string, mixed> $params */
        $params = is_array($item['route_params'] ?? null) ? $item['route_params'] : [];

        $routeName = isset($item['route']) && is_string($item['route']) ? $item['route'] : null;

        $url = match (true) {
            isset($item['url']) && is_string($item['url']) => $item['url'],
            $routeName !== null && Route::has($routeName) => route($routeName, $params),
            default => null,
        };

        /** @var list<string> $active */
        $active = array_values(array_filter(
            (array) ($item['active'] ?? []),
            is_string(...),
        ));

        return new self(
            label: (string) ($item['label'] ?? ''),
            icon: isset($item['icon']) && is_string($item['icon']) ? $item['icon'] : null,
            url: $url,
            routeName: $routeName,
            permission: isset($item['permission']) && is_string($item['permission']) ? $item['permission'] : null,
            activePatterns: $active,
            badge: isset($item['badge']) ? (string) $item['badge'] : null,
            badgeVariant: isset($item['badge_variant']) && is_string($item['badge_variant']) ? $item['badge_variant'] : null,
            children: $children,
        );
    }

    /**
     * A heading-only row, or a parent whose children carry the links.
     */
    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    /**
     * True when the current request lands on this item or any descendant.
     */
    public function isActive(): bool
    {
        foreach ($this->children as $child) {
            if ($child->isActive()) {
                return true;
            }
        }

        $patterns = $this->activePatterns;

        if ($this->routeName !== null) {
            $patterns[] = $this->routeName;
        }

        foreach ($patterns as $pattern) {
            if (Route::currentRouteNamed($pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'icon' => $this->icon,
            'url' => $this->url,
            'route' => $this->routeName,
            'permission' => $this->permission,
            'badge' => $this->badge,
            'badge_variant' => $this->badgeVariant,
            'active' => $this->isActive(),
            'children' => array_map(static fn (MenuItem $child): array => $child->toArray(), $this->children),
        ];
    }
}
