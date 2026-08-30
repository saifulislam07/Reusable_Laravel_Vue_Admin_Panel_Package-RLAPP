<?php

declare(strict_types=1);

namespace Database\Factories\Admin;

use App\Enums\Admin\PageStatus;
use App\Models\Admin\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'title' => $title,
            'slug' => Page::generateUniqueSlug($title),
            'content' => '<p>'.fake()->paragraph().'</p>',
            'status' => PageStatus::Draft,
            'template' => 'default',
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => PageStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'status' => PageStatus::Scheduled,
            'published_at' => now()->addWeek(),
        ]);
    }
}
