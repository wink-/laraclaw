<?php

namespace App\Laraclaw\Skills;

use App\Laraclaw\Skills\Contracts\SkillInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class TimeSkill implements SkillInterface, Tool
{
    protected ?string $timezone = null;

    public function name(): string
    {
        return 'get_current_time';
    }

    public function description(): Stringable|string
    {
        return 'Get the current date and time. Use this when you need to know what time it is now.';
    }

    public function execute(array $parameters): string
    {
        $timezone = $parameters['timezone'] ?? 'UTC';
        $format = $parameters['format'] ?? 'Y-m-d H:i:s';

        try {
            $now = now()->setTimezone($timezone);
            $formatted = $now->format($format);
            $dayOfWeek = $now->format('l');
            $relative = $now->diffForHumans();

            return "Current time in {$timezone}: {$formatted} ({$dayOfWeek}, {$relative})";
        } catch (\Exception $e) {
            return "Error: Invalid timezone '{$timezone}'. Please use a valid timezone like 'UTC', 'America/New_York', etc.";
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'timezone' => $schema->string()->description('The timezone to use (e.g., UTC, America/New_York)'),
            'format' => $schema->string()->description('The date format (e.g., Y-m-d H:i:s)'),
        ];
    }

    public function toTool(): Tool
    {
        return $this;
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->execute($request->all());
    }

    public function withTimezone(string $timezone): self
    {
        $this->timezone = $timezone;

        return $this;
    }
}
