<?php

namespace App\Console\Commands;

use App\Laraclaw\Channels\ChannelBindingManager;
use Illuminate\Console\Command;

class ChannelUnbindCommand extends Command
{
    protected $signature = 'laraclaw:channel:unbind {gateway : The gateway type (telegram, discord, etc.)} {channel_id : The channel/chat ID to unbind}';

    protected $description = 'Unbind a channel binding';

    public function handle(ChannelBindingManager $bindingManager): int
    {
        $gateway = $this->argument('gateway');
        $channelId = (string) $this->argument('channel_id');

        if (! $this->confirm("Are you sure you want to unbind {$gateway} channel {$channelId}?")) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        if ($bindingManager->unbind($gateway, $channelId)) {
            $this->info("Successfully unbound {$gateway} channel {$channelId}.");

            return self::SUCCESS;
        }

        $this->error("Binding not found for {$gateway} channel {$channelId}.");

        return self::FAILURE;
    }
}
