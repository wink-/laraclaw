<?php

namespace App\Console\Commands;

use App\Laraclaw\Notifications\NotificationDispatcher;
use App\Models\LaraclawNotification;
use Cron\CronExpression;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class LaraclawDispatchNotificationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laraclaw:dispatch-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch due proactive Laraclaw notifications';

    /**
     * Execute the console command.
     */
    public function handle(NotificationDispatcher $dispatcher): int
    {
        if (! config('laraclaw.notifications.enabled', true)) {
            $this->info('Notifications are disabled.');

            return self::SUCCESS;
        }

        $due = LaraclawNotification::query()
            ->where('status', 'pending')
            ->where(function ($query): void {
                $query->whereNull('send_at')->orWhere('send_at', '<=', now());
            })
            ->orderBy('id')
            ->limit(100)
            ->get();

        foreach ($due as $notification) {
            try {
                $sent = $dispatcher->dispatch($notification);

                if (! $sent) {
                    $notification->update([
                        'status' => 'failed',
                        'last_error' => 'Unable to dispatch notification to gateway.',
                    ]);

                    continue;
                }

                $nextRunAt = null;
                $nextStatus = 'sent';

                if ($notification->cron_expression) {
                    $cron = new CronExpression($notification->cron_expression);
                    $nextRunAt = $cron->getNextRunDate(now())->format('Y-m-d H:i:s');
                    $nextStatus = 'pending';
                }

                $notification->update([
                    'status' => $nextStatus,
                    'last_error' => null,
                    'sent_at' => now(),
                    'send_at' => $nextRunAt,
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed proactive notification dispatch.', [
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage(),
                ]);

                $notification->update([
                    'status' => 'failed',
                    'last_error' => $e->getMessage(),
                ]);
            }
        }

        $this->info('Processed '.$due->count().' notifications.');

        return self::SUCCESS;
    }
}
