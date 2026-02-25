<?php

namespace App\Laraclaw\Skills;

use App\Laraclaw\Notifications\NotificationDispatcher;
use App\Laraclaw\Skills\Contracts\SkillInterface;
use App\Models\LaraclawNotification;
use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class NotificationSkill implements SkillInterface, Tool
{
    public function __construct(
        protected NotificationDispatcher $dispatcher,
    ) {}

    public function name(): string
    {
        return 'notifications';
    }

    public function description(): Stringable|string
    {
        return 'Create, list, cancel, and trigger proactive outbound notifications for Telegram, Discord, and WhatsApp.';
    }

    public function execute(array $parameters): string
    {
        $action = strtolower(trim((string) ($parameters['action'] ?? 'list')));

        return match ($action) {
            'create' => $this->createNotification($parameters),
            'list' => $this->listNotifications($parameters),
            'cancel' => $this->cancelNotification($parameters),
            'dispatch_now' => $this->dispatchNow($parameters),
            default => "Unknown action: {$action}. Use create, list, cancel, or dispatch_now.",
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['create', 'list', 'cancel', 'dispatch_now'])
                ->description('Notification action to perform.'),
            'id' => $schema->integer()->description('Notification ID used by cancel or dispatch_now.'),
            'gateway' => $schema->string()
                ->enum(['telegram', 'discord', 'whatsapp'])
                ->description('Target gateway for the notification.'),
            'channel_id' => $schema->string()->description('Gateway-specific channel/chat identifier.'),
            'message' => $schema->string()->description('Message body to send.'),
            'user_id' => $schema->integer()->description('Optional user ID for routing and ownership.'),
            'conversation_id' => $schema->integer()->description('Optional existing conversation ID to send into.'),
            'cron' => $schema->string()->description('Optional cron expression for recurring notification delivery.'),
            'when' => $schema->string()->description('Optional natural-language one-time schedule (e.g. "tomorrow at 8am").'),
            'send_at' => $schema->string()->description('Optional explicit datetime (ISO 8601).'),
            'status' => $schema->string()->enum(['pending', 'sent', 'failed'])->description('Optional status filter for list action.'),
            'limit' => $schema->integer()->description('Optional list limit (default 10, max 50).'),
        ];
    }

    public function toTool(): Tool
    {
        return $this;
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->execute($request->all());
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function createNotification(array $parameters): string
    {
        $gateway = strtolower(trim((string) ($parameters['gateway'] ?? '')));
        $message = trim((string) ($parameters['message'] ?? ''));

        if (! in_array($gateway, ['telegram', 'discord', 'whatsapp'], true)) {
            return 'Error: gateway must be one of telegram, discord, or whatsapp.';
        }

        if ($message === '') {
            return 'Error: message is required.';
        }

        $cronExpression = trim((string) ($parameters['cron'] ?? ''));
        if ($cronExpression !== '') {
            try {
                new CronExpression($cronExpression);
            } catch (\Throwable) {
                return 'Error: invalid cron expression.';
            }
        }

        $sendAt = $this->resolveSendAt($parameters);
        if (($parameters['when'] ?? null) !== null && $sendAt === null) {
            return 'Error: unable to parse when value.';
        }

        $notification = LaraclawNotification::query()->create([
            'user_id' => $parameters['user_id'] ?? null,
            'conversation_id' => $parameters['conversation_id'] ?? null,
            'gateway' => $gateway,
            'channel_id' => ($parameters['channel_id'] ?? null) ?: null,
            'message' => $message,
            'cron_expression' => $cronExpression !== '' ? $cronExpression : null,
            'send_at' => $sendAt,
            'status' => 'pending',
            'metadata' => [
                'source' => 'notification_skill',
            ],
        ]);

        $schedule = $notification->cron_expression
            ? "recurring ({$notification->cron_expression})"
            : ($notification->send_at ? 'scheduled for '.$notification->send_at->toDateTimeString() : 'queued for next dispatch cycle');

        return "Notification #{$notification->id} created for {$gateway}: {$schedule}.";
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function listNotifications(array $parameters): string
    {
        $status = strtolower(trim((string) ($parameters['status'] ?? '')));
        $gateway = strtolower(trim((string) ($parameters['gateway'] ?? '')));
        $limit = max(1, min(50, (int) ($parameters['limit'] ?? 10)));

        $notifications = LaraclawNotification::query()
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($gateway !== '', fn ($query) => $query->where('gateway', $gateway))
            ->latest('id')
            ->limit($limit)
            ->get();

        if ($notifications->isEmpty()) {
            return 'No notifications found.';
        }

        $lines = $notifications->map(function (LaraclawNotification $notification): string {
            $schedule = $notification->cron_expression
                ? "cron={$notification->cron_expression}"
                : ($notification->send_at?->toDateTimeString() ?? 'immediate');

            return "- #{$notification->id} [{$notification->status}] {$notification->gateway} ({$schedule}) :: {$notification->message}";
        })->implode("\n");

        return "Notifications:\n{$lines}";
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function cancelNotification(array $parameters): string
    {
        $id = (int) ($parameters['id'] ?? 0);

        if ($id <= 0) {
            return 'Error: id is required for cancel.';
        }

        $notification = LaraclawNotification::query()->find($id);

        if (! $notification) {
            return "Notification #{$id} not found.";
        }

        $notification->update([
            'status' => 'failed',
            'last_error' => 'Cancelled by user/tool action.',
        ]);

        return "Notification #{$id} cancelled.";
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function dispatchNow(array $parameters): string
    {
        $id = (int) ($parameters['id'] ?? 0);

        if ($id <= 0) {
            return 'Error: id is required for dispatch_now.';
        }

        $notification = LaraclawNotification::query()->find($id);

        if (! $notification) {
            return "Notification #{$id} not found.";
        }

        $sent = $this->dispatcher->dispatch($notification);

        if (! $sent) {
            $notification->update([
                'status' => 'failed',
                'last_error' => 'Unable to dispatch notification to gateway.',
            ]);

            return "Notification #{$id} failed to dispatch.";
        }

        $notification->update([
            'status' => $notification->cron_expression ? 'pending' : 'sent',
            'sent_at' => now(),
            'last_error' => null,
        ]);

        return "Notification #{$id} dispatched successfully.";
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function resolveSendAt(array $parameters): ?Carbon
    {
        $sendAt = trim((string) ($parameters['send_at'] ?? ''));

        if ($sendAt !== '') {
            try {
                return Carbon::parse($sendAt);
            } catch (\Throwable) {
                return null;
            }
        }

        $when = trim((string) ($parameters['when'] ?? ''));

        if ($when === '') {
            return null;
        }

        try {
            return Carbon::parse($when);
        } catch (\Throwable) {
            return null;
        }
    }
}
