<?php

namespace App\Console\Commands;

use App\Laraclaw\Heartbeat\HeartbeatEngine;
use Illuminate\Console\Command;

class LaraclawHeartbeatRunCommand extends Command
{
    protected $signature = 'laraclaw:heartbeat:run
                            {--dry-run : Show what would run without executing}';

    protected $description = 'Run due HEARTBEAT.md tasks via the AI agent';

    public function handle(HeartbeatEngine $engine): int
    {
        if (! config('laraclaw.heartbeat.enabled', true)) {
            $this->line('Heartbeat engine is disabled in config.');

            return self::SUCCESS;
        }

        $items = $engine->parseHeartbeatFile();

        if (empty($items)) {
            $this->line('No heartbeat items found. Create a HEARTBEAT.md in storage/laraclaw/.');

            return self::SUCCESS;
        }

        $enabledCount = count(array_filter($items, fn ($i) => $i['enabled']));
        $this->info("Found {$enabledCount} enabled heartbeat items (of ".count($items).' total).');

        if ($this->option('dry-run')) {
            $this->table(
                ['ID', 'Instruction', 'Schedule', 'Enabled'],
                array_map(fn ($item) => [
                    $item['id'],
                    substr($item['instruction'], 0, 60),
                    $item['schedule'],
                    $item['enabled'] ? 'Yes' : 'No',
                ], $items)
            );

            return self::SUCCESS;
        }

        $results = $engine->runDueItems();

        if (empty($results)) {
            $this->line('No heartbeat items are due to run.');

            return self::SUCCESS;
        }

        foreach ($results as $result) {
            $icon = $result['status'] === 'success' ? '<fg=green>OK</>' : '<fg=red>FAIL</>';
            $this->line("  [{$icon}] {$result['instruction']}");
        }

        $this->newLine();
        $successes = count(array_filter($results, fn ($r) => $r['status'] === 'success'));
        $failures = count($results) - $successes;
        $this->info("Completed: {$successes} succeeded, {$failures} failed.");

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
