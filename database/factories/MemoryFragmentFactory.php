<?php

namespace Database\Factories;

use App\Models\MemoryFragment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MemoryFragment>
 */
class MemoryFragmentFactory extends Factory
{
    protected $model = MemoryFragment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'conversation_id' => null,
            'key' => fake()->word(),
            'content' => fake()->paragraph(),
            'embedding_id' => null,
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the memory belongs to a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Indicate that the memory has a specific key.
     */
    public function withKey(string $key): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => $key,
        ]);
    }
}
