<?php

namespace App\Laraclaw\Skills;

use App\Laraclaw\Skills\Contracts\SkillInterface;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SchedulerSkill implements SkillInterface, Tool
{
    public function name(): string
    {
        return 'scheduler';
    }

    public function description(): Stringable|string
    {
        return 'Schedule a task to be executed at a specific time or interval using cron syntax. Useful for reminders, recurring reports, or delayed actions.';
    }

    public function execute(array $parameters): string
    {
        $action = trim((string) ($parameters['action'] ?? ''));
        $cronExpression = $this->resolveCronExpression($parameters);

        if ($action === '') {
            return 'Failed to schedule task: action is required.';
        }

        if ($cronExpression === null) {
            return 'Failed to schedule task: provide a cron expression or natural-language "when" value.';
        }

        try {
            DB::table('laraclaw_scheduled_tasks')->insert([
                'user_id' => $parameters['user_id'] ?? null,
                'action' => $action,
                'cron_expression' => $cronExpression,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return "Task scheduled successfully with cron: {$cronExpression} for action: {$action}";
        } catch (\Exception $e) {
            Log::error('SchedulerSkill failed: '.$e->getMessage());

            return 'Failed to schedule task: '.$e->getMessage();
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->description('The action or prompt to execute when the schedule triggers (e.g., "Remind me to drink water", "Summarize my emails").'),
            'cron' => $schema->string()->description('The cron expression defining when the task should run (e.g., "0 9 * * *" for every day at 9 AM).'),
            'when' => $schema->string()->description('Natural-language schedule (e.g., "every weekday at 8am", "tomorrow at noon", "in 3 hours", "daily at 9am").'),
            'user_id' => $schema->integer()->description('The ID of the user scheduling the task.'),
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

    protected function resolveCronExpression(array $parameters): ?string
    {
        $cron = trim((string) ($parameters['cron'] ?? ''));

        if ($cron !== '') {
            return $cron;
        }

        $when = trim((string) ($parameters['when'] ?? ''));

        if ($when === '') {
            return null;
        }

        return $this->parseNaturalSchedule($when);
    }

    protected function parseNaturalSchedule(string $when): ?string
    {
        $normalized = mb_strtolower(trim($when));

        if ($normalized === 'hourly' || $normalized === 'every hour') {
            return '0 * * * *';
        }

        if ($normalized === 'daily' || $normalized === 'every day') {
            return '0 9 * * *';
        }

        if ($normalized === 'weekly' || $normalized === 'every week') {
            return '0 9 * * 1';
        }

        if (preg_match('/^every\s+weekday(?:\s+at\s+(.+))?$/', $normalized, $matches)) {
            [$hour, $minute] = $this->parseTime($matches[1] ?? '9:00');

            return "{$minute} {$hour} * * 1-5";
        }

        if (preg_match('/^daily\s+at\s+(.+)$/', $normalized, $matches)) {
            [$hour, $minute] = $this->parseTime($matches[1]);

            return "{$minute} {$hour} * * *";
        }

        if (preg_match('/^every\s+(\d+)\s+minutes?$/', $normalized, $matches)) {
            $step = max(1, (int) $matches[1]);

            return "*/{$step} * * * *";
        }

        if (preg_match('/^every\s+(\d+)\s+hours?$/', $normalized, $matches)) {
            $step = max(1, (int) $matches[1]);

            return "0 */{$step} * * *";
        }

        if (preg_match('/^in\s+(\d+)\s+minutes?$/', $normalized, $matches)) {
            $target = now()->addMinutes((int) $matches[1]);

            return sprintf('%d %d %d %d *', $target->minute, $target->hour, $target->day, $target->month);
        }

        if (preg_match('/^in\s+(\d+)\s+hours?$/', $normalized, $matches)) {
            $target = now()->addHours((int) $matches[1]);

            return sprintf('%d %d %d %d *', $target->minute, $target->hour, $target->day, $target->month);
        }

        if (str_starts_with($normalized, 'tomorrow')) {
            $timePart = trim(str_replace(['tomorrow at', 'tomorrow'], '', $normalized));
            [$hour, $minute] = $this->parseTime($timePart === '' ? '9:00' : $timePart);
            $target = now()->addDay();

            return sprintf('%d %d %d %d *', $minute, $hour, $target->day, $target->month);
        }

        try {
            $target = Carbon::parse($when);

            return sprintf('%d %d %d %d *', $target->minute, $target->hour, $target->day, $target->month);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function parseTime(string $value): array
    {
        $raw = trim($value);

        if ($raw === '') {
            return [9, 0];
        }

        if (in_array($raw, ['noon', 'midday'], true)) {
            return [12, 0];
        }

        if ($raw === 'midnight') {
            return [0, 0];
        }

        try {
            $time = Carbon::parse($raw);

            return [$time->hour, $time->minute];
        } catch (\Throwable) {
            return [9, 0];
        }
    }
}
