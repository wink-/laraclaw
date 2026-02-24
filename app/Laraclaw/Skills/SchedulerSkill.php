<?php

namespace App\Laraclaw\Skills;

use App\Laraclaw\Skills\Contracts\SkillInterface;
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
        try {
            DB::table('laraclaw_scheduled_tasks')->insert([
                'user_id' => $parameters['user_id'],
                'action' => $parameters['action'],
                'cron_expression' => $parameters['cron'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return "Task scheduled successfully with cron: {$parameters['cron']} for action: {$parameters['action']}";
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
}
