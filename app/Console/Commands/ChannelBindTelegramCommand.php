<?php

namespace App\Console\Commands;

use App\Laraclaw\Channels\ChannelBindingManager;
use Illuminate\Console\Command;

class ChannelBindTelegramCommand extends Command
{
    protected $signature = 'laraclaw:channel:bind-telegram {chat_id : The Telegram chat ID to bind} {--user= : The user ID to associate with this binding}';

    protected $description = 'Bind a Telegram chat to a user';

    public function handle(ChannelBindingManager $bindingManager): int
    {
        $chatId = (string) $this->argument('chat_id');
        $userId = $this->option('user') ? (int) $this->option('user') : null;

        $binding = $bindingManager->bind(
            gateway: 'telegram',
            channelId: $chatId,
            userId: $userId
        );

        $this->info("Successfully bound Telegram chat {$chatId}.");

        if ($userId) {
            $this->line("Associated with user ID: {$userId}");
        }

        $this->line("Binding ID: {$binding->id}");

        return self::SUCCESS;
    }
}
