<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(),
            'gateway' => 'cli',
            'gateway_conversation_id' => null,
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the conversation belongs to a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Indicate that the conversation is from a specific gateway.
     */
    public function fromGateway(string $gateway): static
    {
        return $this->state(fn (array $attributes) => [
            'gateway' => $gateway,
        ]);
    }
}
