<?php

namespace Database\Factories;

use App\Models\LaraclawProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LaraclawProvider>
 */
class LaraclawProviderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'name' => fake()->company(),
            'driver' => fake()->randomElement(['openai', 'anthropic', 'gemini', 'ollama', 'groq']),
            'key_env' => strtoupper(fake()->unique()->word()).'_API_KEY',
            'url' => null,
        ];
    }
}
