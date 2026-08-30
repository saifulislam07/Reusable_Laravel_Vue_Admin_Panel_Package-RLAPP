<?php

declare(strict_types=1);

namespace App\Console\Commands\Admin;

use App\Enums\Admin\PageStatus;
use App\Models\Admin\Page;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use SimpleXMLElement;

/**
 * Writes public/sitemap.xml from every Page that's actually publicly
 * visible: Published, or Scheduled with a publish date that's already due
 * -- there's no cron flipping Scheduled to Published on its own (see
 * Phase 11), so a scheduled run of this command is what makes a scheduled
 * page start showing up.
 *
 * Only Pages exist as a sitemap-able resource so far; a future content
 * type adds itself here the same way.
 */
#[Signature('sitemap:generate')]
#[Description('Write public/sitemap.xml from every publicly-visible Page')]
class GenerateSitemap extends Command
{
    public function handle(): int
    {
        $pages = Page::query()
            ->where(function ($query): void {
                $query->where('status', PageStatus::Published)
                    ->orWhere(function ($query): void {
                        $query->where('status', PageStatus::Scheduled)
                            ->where('published_at', '<=', now());
                    });
            })
            ->get();

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');

        foreach ($pages as $page) {
            $entry = $xml->addChild('url');
            $entry->addChild('loc', htmlspecialchars($this->pageUrl($page), ENT_XML1));

            if ($page->updated_at) {
                $entry->addChild('lastmod', $page->updated_at->toAtomString());
            }
        }

        File::put(public_path('sitemap.xml'), (string) $xml->asXML());

        $this->info("Wrote {$pages->count()} URLs to public/sitemap.xml");

        return self::SUCCESS;
    }

    /**
     * No public route renders a Page yet (Phase 11's brief was admin CRUD
     * only), so the URL pattern is config-driven -- a consuming project
     * that adds its own public route overrides this to match.
     */
    private function pageUrl(Page $page): string
    {
        $pattern = (string) config('admin-kit.seo.page_url_pattern', '/{slug}');

        return url(str_replace('{slug}', $page->slug, $pattern));
    }
}
