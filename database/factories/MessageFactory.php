<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'role' => fake()->randomElement(['user', 'assistant', 'system']),
            'content' => fake()->paragraph(),
            'tool_name' => null,
            'tool_arguments' => null,
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the message is from the user.
     */
    public function fromUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'user',
        ]);
    }

    /**
     * Indicate that the message is from the assistant.
     */
    public function fromAssistant(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'assistant',
        ]);
    }

    /**
     * Indicate that the message is a tool result.
     */
    public function asToolResult(string $toolName, array $arguments = []): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'tool',
            'tool_name' => $toolName,
            'tool_arguments' => $arguments,
        ]);
    }
}
