<?php

namespace App\Console\Commands;

use App\Laraclaw\Gateways\TelegramGateway;
use App\Laraclaw\Tunnels\TunnelManager;
use Illuminate\Console\Command;

class LaraclawSyncTelegramWebhookCommand extends Command
{
    protected $signature = 'laraclaw:telegram:webhook-sync
                            {--provider=cloudflare : Tunnel provider to use when starting a tunnel}
                            {--port=8000 : Local port to expose through the tunnel}
                            {--url= : Explicit public base URL to use instead of starting or reading a tunnel}';

    protected $description = 'Sync the Telegram webhook with the current public Laraclaw URL';

    /**
     * Execute the console command.
     */
    public function handle(TunnelManager $tunnelManager, TelegramGateway $telegram): int
    {
        if (! filled(config('services.telegram.bot_token'))) {
            $this->error('TELEGRAM_BOT_TOKEN is not configured.');

            return self::FAILURE;
        }

        $baseUrl = $this->resolveBaseUrl($tunnelManager);

        if (! $baseUrl) {
            $this->error('Unable to determine a public URL for Telegram webhooks.');

            return self::FAILURE;
        }

        $webhookUrl = rtrim($baseUrl, '/').'/laraclaw/webhooks/telegram';

        if (! $telegram->setWebhook($webhookUrl)) {
            $this->error('Failed to register the Telegram webhook.');

            return self::FAILURE;
        }

        $this->info('Telegram webhook synced successfully.');
        $this->line("Webhook URL: {$webhookUrl}");

        $webhookInfo = $telegram->getWebhookInfo();

        if ($webhookInfo) {
            $pendingUpdates = $webhookInfo['pending_update_count'] ?? 0;
            $lastError = $webhookInfo['last_error_message'] ?? null;

            $this->line("Pending updates: {$pendingUpdates}");

            if ($lastError) {
                $this->warn("Telegram reports last error: {$lastError}");
            }
        }

        return self::SUCCESS;
    }

    protected function resolveBaseUrl(TunnelManager $tunnelManager): ?string
    {
        $explicitUrl = $this->option('url');

        if (is_string($explicitUrl) && $explicitUrl !== '') {
            return rtrim($explicitUrl, '/');
        }

        $activeUrl = $tunnelManager->getUrl();

        if ($activeUrl) {
            return $activeUrl;
        }

        $provider = (string) $this->option('provider');
        $port = (int) $this->option('port');

        if (! $tunnelManager->start($provider, ['port' => $port])) {
            return null;
        }

        return $tunnelManager->getUrl();
    }
}
