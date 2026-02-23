<?php

namespace App\Console\Commands;

use App\Laraclaw\Channels\ChannelBindingManager;
use Illuminate\Console\Command;

class ChannelBindDiscordCommand extends Command
{
    protected $signature = 'laraclaw:channel:bind-discord {channel_id : The Discord channel ID to bind} {--user= : The user ID to associate with this binding}';

    protected $description = 'Bind a Discord channel to a user';

    public function handle(ChannelBindingManager $bindingManager): int
    {
        $channelId = (string) $this->argument('channel_id');
        $userId = $this->option('user') ? (int) $this->option('user') : null;

        $binding = $bindingManager->bind(
            gateway: 'discord',
            channelId: $channelId,
            userId: $userId
        );

        $this->info("Successfully bound Discord channel {$channelId}.");

        if ($userId) {
            $this->line("Associated with user ID: {$userId}");
        }

        $this->line("Binding ID: {$binding->id}");

        return self::SUCCESS;
    }
}
