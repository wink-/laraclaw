<?php

namespace App\Console\Commands;

use App\Laraclaw\Facades\Laraclaw;
use App\Models\Conversation;
use Cron\CronExpression;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LaraclawRunScheduledTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laraclaw:run-scheduled-tasks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run scheduled tasks created by the Laraclaw agent';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! DB::getSchemaBuilder()->hasTable('laraclaw_scheduled_tasks')) {
            $this->info('No scheduled tasks table found. Skipping.');

            return self::SUCCESS;
        }

        $tasks = DB::table('laraclaw_scheduled_tasks')
            ->where('is_active', true)
            ->get();

        $now = now();

        foreach ($tasks as $task) {
            try {
                $cron = new CronExpression($task->cron_expression);

                if ($cron->isDue($now)) {
                    $this->info("Running task ID: {$task->id} - Action: {$task->action}");

                    $conversation = Conversation::create([
                        'user_id' => $task->user_id,
                        'gateway' => 'scheduler',
                        'title' => 'Scheduled Task',
                    ]);

                    $response = Laraclaw::chat($conversation, "Scheduled Task Triggered: {$task->action}");

                    Log::info("Scheduled task {$task->id} executed.", [
                        'conversation_id' => $conversation->id,
                        'response' => $response,
                    ]);

                    DB::table('laraclaw_scheduled_tasks')
                        ->where('id', $task->id)
                        ->update(['last_run_at' => $now]);
                }
            } catch (\Exception $e) {
                Log::error("Failed to run scheduled task {$task->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
