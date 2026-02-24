<?php

namespace App\Laraclaw\Heartbeat;

use App\Laraclaw\Facades\Laraclaw;
use App\Models\Conversation;
use App\Models\HeartbeatRun;
use Illuminate\Support\Facades\Log;

class HeartbeatEngine
{
    protected string $heartbeatPath;

    public function __construct(?string $heartbeatPath = null)
    {
        $this->heartbeatPath = $heartbeatPath ?? config(
            'laraclaw.heartbeat.path',
            storage_path('laraclaw/HEARTBEAT.md')
        );
    }

    /**
     * Parse the HEARTBEAT.md file and return heartbeat items.
     *
     * @return array<int, array{id: string, instruction: string, schedule: string, enabled: bool}>
     */
    public function parseHeartbeatFile(): array
    {
        if (! file_exists($this->heartbeatPath)) {
            return [];
        }

        $content = file_get_contents($this->heartbeatPath);
        $lines = explode("\n", $content);
        $items = [];
        $currentId = 0;

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines, comments, and header
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '<!--')) {
                continue;
            }

            // Parse checkbox items: - [x] instruction @schedule or - [ ] instruction @schedule
            if (preg_match('/^-\s*\[([ xX])\]\s*(.+)$/', $line, $matches)) {
                $enabled = strtolower($matches[1]) === 'x';
                $rawInstruction = trim($matches[2]);

                // Extract schedule from @every(...) or @cron(...) or @daily, @hourly etc
                $schedule = $this->extractSchedule($rawInstruction);
                $instruction = $this->stripScheduleFromInstruction($rawInstruction);

                $currentId++;
                $items[] = [
                    'id' => 'heartbeat_'.$currentId,
                    'instruction' => $instruction,
                    'schedule' => $schedule,
                    'enabled' => $enabled,
                ];
            }
        }

        return $items;
    }

    /**
     * Run all due heartbeat items.
     *
     * @return array<int, array{id: string, instruction: string, status: string, response: ?string}>
     */
    public function runDueItems(): array
    {
        $items = $this->parseHeartbeatFile();
        $results = [];

        foreach ($items as $item) {
            if (! $item['enabled']) {
                continue;
            }

            if (! $this->isDue($item)) {
                continue;
            }

            $results[] = $this->executeItem($item);
        }

        return $results;
    }

    /**
     * Check if a heartbeat item is due to run.
     *
     * @param  array{id: string, instruction: string, schedule: string, enabled: bool}  $item
     */
    protected function isDue(array $item): bool
    {
        $lastRun = HeartbeatRun::query()
            ->where('heartbeat_id', $item['id'])
            ->latest('executed_at')
            ->first();

        if (! $lastRun) {
            return true;
        }

        $intervalMinutes = $this->scheduleToMinutes($item['schedule']);

        return $lastRun->executed_at->addMinutes($intervalMinutes)->lte(now());
    }

    /**
     * Execute a single heartbeat item by sending it to the agent.
     *
     * @param  array{id: string, instruction: string, schedule: string, enabled: bool}  $item
     * @return array{id: string, instruction: string, status: string, response: ?string}
     */
    protected function executeItem(array $item): array
    {
        try {
            // Create or find a heartbeat-specific conversation
            $conversation = Conversation::firstOrCreate(
                ['title' => 'Heartbeat: '.substr($item['instruction'], 0, 50)],
                ['user_id' => null, 'gateway' => 'heartbeat']
            );

            $systemPrompt = 'You are running a heartbeat task. Execute the following instruction '
                .'and provide a concise result. If the task requires external data you cannot access, '
                .'describe what you would do and provide the best response you can.';

            $response = Laraclaw::chat(
                $conversation,
                "[Heartbeat Task] {$item['instruction']}",
                $systemPrompt
            );

            HeartbeatRun::create([
                'heartbeat_id' => $item['id'],
                'instruction' => $item['instruction'],
                'status' => 'success',
                'response' => is_string($response) ? substr($response, 0, 5000) : null,
                'executed_at' => now(),
            ]);

            return [
                'id' => $item['id'],
                'instruction' => $item['instruction'],
                'status' => 'success',
                'response' => is_string($response) ? $response : null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Heartbeat task failed: '.$e->getMessage(), [
                'heartbeat_id' => $item['id'],
                'instruction' => $item['instruction'],
            ]);

            HeartbeatRun::create([
                'heartbeat_id' => $item['id'],
                'instruction' => $item['instruction'],
                'status' => 'failed',
                'response' => $e->getMessage(),
                'executed_at' => now(),
            ]);

            return [
                'id' => $item['id'],
                'instruction' => $item['instruction'],
                'status' => 'failed',
                'response' => $e->getMessage(),
            ];
        }
    }

    /**
     * Extract schedule notation from instruction string.
     */
    protected function extractSchedule(string $instruction): string
    {
        // @every(30m), @every(1h), @every(6h), @every(1d)
        if (preg_match('/@every\(([^)]+)\)/', $instruction, $matches)) {
            return 'every:'.trim($matches[1]);
        }

        // @cron(* * * * *)
        if (preg_match('/@cron\(([^)]+)\)/', $instruction, $matches)) {
            return 'cron:'.trim($matches[1]);
        }

        // Shorthand aliases
        if (str_contains($instruction, '@hourly')) {
            return 'every:1h';
        }
        if (str_contains($instruction, '@daily')) {
            return 'every:24h';
        }
        if (str_contains($instruction, '@weekly')) {
            return 'every:168h';
        }

        // Default: every 60 minutes
        return 'every:60m';
    }

    /**
     * Remove schedule notation from instruction text.
     */
    protected function stripScheduleFromInstruction(string $instruction): string
    {
        $instruction = preg_replace('/@every\([^)]+\)/', '', $instruction);
        $instruction = preg_replace('/@cron\([^)]+\)/', '', $instruction);
        $instruction = str_replace(['@hourly', '@daily', '@weekly'], '', $instruction);

        return trim($instruction);
    }

    /**
     * Convert a schedule string to interval in minutes.
     */
    protected function scheduleToMinutes(string $schedule): int
    {
        if (str_starts_with($schedule, 'every:')) {
            $value = substr($schedule, 6);

            if (preg_match('/^(\d+)(m|h|d)$/', $value, $matches)) {
                $num = (int) $matches[1];

                return match ($matches[2]) {
                    'm' => $num,
                    'h' => $num * 60,
                    'd' => $num * 1440,
                    default => 60,
                };
            }
        }

        // Default: 60 minutes
        return 60;
    }
}
