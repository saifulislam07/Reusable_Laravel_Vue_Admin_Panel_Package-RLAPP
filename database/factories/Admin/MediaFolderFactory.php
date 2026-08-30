<?php

declare(strict_types=1);

namespace Database\Factories\Admin;

use App\Models\Admin\MediaFolder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaFolder>
 */
class MediaFolderFactory extends Factory
{
    protected $model = MediaFolder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
        ];
    }
}
