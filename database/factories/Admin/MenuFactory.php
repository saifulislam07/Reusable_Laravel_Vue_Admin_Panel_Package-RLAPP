<?php

declare(strict_types=1);

namespace Database\Factories\Admin;

use App\Models\Admin\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
{
    protected $model = Menu::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => fake()->unique()->words(2, true),
            'icon' => 'dot',
            'url' => '/'.fake()->slug(),
            'route' => null,
            'parent_id' => null,
            'order' => 0,
            'permission_name' => null,
            'is_active' => true,
        ];
    }
}
