<?php

declare(strict_types=1);

namespace App\Support\Admin;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\HtmlString;

/**
 * Turns the admin-kit.theme config into the CSS custom properties the
 * stylesheet consumes.
 *
 * Re-branding a consuming project means overriding config tokens -- never
 * editing admin.css -- which is what keeps the panel brand-agnostic.
 */
class Theme
{
    public function __construct(private readonly Config $config) {}

    /**
     * A `:root { --nk-*: … }` block, plus the dark-scheme overrides.
     */
    public function styleBlock(): HtmlString
    {
        $light = $this->declarations($this->tokens());
        $dark = $this->declarations($this->darkTokens());

        $css = ":root{{$light}}";

        if ($dark !== '') {
            // Both selectors so an explicit choice and the system default agree.
            $css .= "@media (prefers-color-scheme:dark){:root:not([data-nk-scheme=\"light\"]){{$dark}}}";
            $css .= ":root[data-nk-scheme=\"dark\"]{{$dark}}";
        }

        return new HtmlString($css);
    }

    /**
     * @return array<string, string>
     */
    public function tokens(): array
    {
        return $this->stringMap($this->config->get('admin-kit.theme.tokens', []));
    }

    /**
     * @return array<string, string>
     */
    public function darkTokens(): array
    {
        return $this->stringMap($this->config->get('admin-kit.theme.tokens_dark', []));
    }

    public function token(string $name, ?string $default = null): ?string
    {
        return $this->tokens()[$name] ?? $default;
    }

    public function colorScheme(): string
    {
        $scheme = (string) $this->config->get('admin-kit.theme.color_scheme', 'system');

        return in_array($scheme, ['light', 'dark', 'system'], true) ? $scheme : 'system';
    }

    public function sidebarStartsCollapsed(): bool
    {
        return $this->config->get('admin-kit.theme.sidebar_default') === 'collapsed';
    }

    /**
     * @param  array<string, string>  $tokens
     */
    private function declarations(array $tokens): string
    {
        $out = '';

        foreach ($tokens as $name => $value) {
            $out .= '--nk-'.$name.':'.$value.';';
        }

        return $out;
    }

    /**
     * @param  mixed  $tokens
     * @return array<string, string>
     */
    private function stringMap($tokens): array
    {
        $map = [];

        foreach ((array) $tokens as $name => $value) {
            if (is_string($value) || is_int($value) || is_float($value)) {
                // Keep the value away from the `}` that would break out of the block.
                $map[(string) $name] = str_replace(['<', '>', '}', ';'], '', (string) $value);
            }
        }

        return $map;
    }
}
