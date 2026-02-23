<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\MemoryFragment;
use App\Models\Message;
use Illuminate\Console\Command;

use function Laravel\Prompts\info;

class LaraclawStatusCommand extends Command
{
    protected $signature = 'laraclaw:status';

    protected $description = 'Show Laraclaw system status and statistics';

    public function handle(): int
    {
        info('🦀 Laraclaw Status');
        info('==================');
        $this->newLine();

        // System info
        $this->displaySystemInfo();

        // Statistics
        $this->displayStatistics();

        // Gateway status
        $this->displayGatewayStatus();

        // Recent activity
        $this->displayRecentActivity();

        return self::SUCCESS;
    }

    protected function displaySystemInfo(): void
    {
        info('System Information:');
        $this->line('  Laravel Version: '.app()->version());
        $this->line('  PHP Version: '.PHP_VERSION);
        $this->line('  Database: '.config('database.default'));
        $this->line('  AI Provider: '.env('AI_PROVIDER', 'openai'));
        $this->newLine();
    }

    protected function displayStatistics(): void
    {
        info('Statistics:');

        try {
            $conversationCount = Conversation::count();
            $messageCount = Message::count();
            $memoryCount = MemoryFragment::count();

            $this->line("  Conversations: {$conversationCount}");
            $this->line("  Messages: {$messageCount}");
            $this->line("  Memory Fragments: {$memoryCount}");

            if ($conversationCount > 0) {
                $avgMessages = round($messageCount / $conversationCount, 1);
                $this->line("  Avg Messages/Conversation: {$avgMessages}");
            }
        } catch (\Exception $e) {
            $this->line('  Unable to fetch statistics (run migrations first)');
        }
        $this->newLine();
    }

    protected function displayGatewayStatus(): void
    {
        info('Gateway Status:');

        $gateways = [
            'CLI' => true, // Always available
            'Telegram' => ! empty(env('TELEGRAM_BOT_TOKEN')),
            'Discord' => ! empty(env('DISCORD_BOT_TOKEN')),
        ];

        foreach ($gateways as $name => $configured) {
            $status = $configured ? '<info>✓ Configured</info>' : '<comment>○ Not configured</comment>';
            $this->line("  {$name}: {$status}");
        }
        $this->newLine();
    }

    protected function displayRecentActivity(): void
    {
        info('Recent Activity:');

        try {
            $recentConversations = Conversation::latest()
                ->take(5)
                ->get(['id', 'title', 'gateway', 'created_at']);

            if ($recentConversations->isEmpty()) {
                $this->line('  No conversations yet');
            } else {
                foreach ($recentConversations as $conv) {
                    $date = $conv->created_at->diffForHumans();
                    $title = $conv->title ?? 'Untitled';
                    $this->line("  [{$conv->gateway}] {$title} - {$date}");
                }
            }
        } catch (\Exception $e) {
            $this->line('  Unable to fetch recent activity');
        }
        $this->newLine();
    }
}
