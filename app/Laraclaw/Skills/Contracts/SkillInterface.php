<?php

namespace App\Laraclaw\Skills\Contracts;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Stringable;

interface SkillInterface
{
    /**
     * Get the name of the skill.
     */
    public function name(): string;

    /**
     * Get the description of what the skill does.
     */
    public function description(): Stringable|string;

    /**
     * Execute the skill with the given parameters.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function execute(array $parameters): string;

    /**
     * Get the schema for the skill's parameters.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array;

    /**
     * Convert the skill to a Laravel AI Tool.
     */
    public function toTool(): Tool;
}
