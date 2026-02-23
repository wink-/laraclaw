<?php

namespace App\Console\Commands;

use App\Laraclaw\Channels\ChannelBindingManager;
use Illuminate\Console\Command;

class ChannelListCommand extends Command
{
    protected $signature = 'laraclaw:channel:list {--gateway= : Filter by gateway (telegram, discord, etc.)} {--all : Show inactive bindings as well}';

    protected $description = 'List all channel bindings';

    public function handle(ChannelBindingManager $bindingManager): int
    {
        $gateway = $this->option('gateway');
        $activeOnly = ! $this->option('all');

        $bindings = $bindingManager->listBindings($gateway, $activeOnly);

        if ($bindings->isEmpty()) {
            $this->info('No channel bindings found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Gateway', 'Channel ID', 'User', 'Conversation', 'Active', 'Created'],
            $bindings->map(fn ($binding) => [
                $binding->id,
                $binding->gateway,
                $binding->channel_id,
                $binding->user?->name ?? $binding->user_id ?? '-',
                $binding->conversation?->title ?? $binding->conversation_id ?? '-',
                $binding->active ? 'Yes' : 'No',
                $binding->created_at->format('Y-m-d H:i'),
            ])->all()
        );

        $this->line("Total: {$bindings->count()} binding(s)");

        return self::SUCCESS;
    }
}
