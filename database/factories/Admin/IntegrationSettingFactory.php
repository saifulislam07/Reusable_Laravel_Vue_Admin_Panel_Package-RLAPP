<?php

declare(strict_types=1);

namespace Database\Factories\Admin;

use App\Models\Admin\IntegrationSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntegrationSetting>
 */
class IntegrationSettingFactory extends Factory
{
    protected $model = IntegrationSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group' => fake()->unique()->word(),
            'data' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function smtp(array $overrides = []): static
    {
        return $this->state(fn (): array => [
            'group' => 'smtp',
            'data' => array_merge([
                'host' => 'smtp.example.com',
                'port' => 587,
                'username' => 'mailer@example.com',
                'password' => 'secret',
                'encryption' => 'tls',
                'from_address' => 'hello@example.com',
                'from_name' => 'Example',
            ], $overrides),
        ]);
    }
}
