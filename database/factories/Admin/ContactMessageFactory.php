<?php

declare(strict_types=1);

namespace Database\Factories\Admin;

use App\Enums\Admin\ContactMessageStatus;
use App\Models\Admin\ContactMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactMessage>
 */
class ContactMessageFactory extends Factory
{
    protected $model = ContactMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->boolean(70) ? fake()->phoneNumber() : null,
            'subject' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'status' => ContactMessageStatus::New,
            'ip' => fake()->ipv4(),
        ];
    }

    public function read(): static
    {
        return $this->state(fn (): array => ['status' => ContactMessageStatus::Read]);
    }

    public function replied(): static
    {
        return $this->state(fn (): array => [
            'status' => ContactMessageStatus::Replied,
            'reply_message' => fake()->paragraph(),
            'replied_at' => now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => ContactMessageStatus::Archived]);
    }
}
