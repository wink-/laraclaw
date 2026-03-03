<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessNewMemoryJob;
use App\Laraclaw\Gateways\DiscordGateway;
use App\Laraclaw\Gateways\SlackGateway;
use App\Laraclaw\Gateways\TelegramGateway;
use App\Laraclaw\Gateways\WhatsAppGateway;
use App\Laraclaw\Security\SecurityManager;
use App\Laraclaw\Security\SlackSignatureVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemoryWebhookController extends Controller
{
    public function __construct(
        protected SecurityManager $security,
        protected SlackSignatureVerifier $slackSignatureVerifier,
    ) {}

    public function __invoke(Request $request, string $platform): JsonResponse
    {
        $platform = strtolower($platform);

        if ($platform === 'slack' && ($request->input('type') === 'url_verification')) {
            return response()->json([
                'challenge' => $request->input('challenge'),
            ]);
        }

        if (! in_array($platform, ['telegram', 'discord', 'whatsapp', 'slack'], true)) {
            return response()->json(['message' => 'Unsupported platform.'], 422);
        }

        if (! $this->verifyRequest($request, $platform)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 403);
        }

        $gateway = $this->resolveGateway($platform);
        if (! $gateway) {
            return response()->json(['message' => 'Gateway unavailable.'], 503);
        }

        $parsedMessage = $gateway->parseIncomingMessage($request->all());

        $content = trim((string) ($parsedMessage['content'] ?? ''));

        if ($content === '') {
            return response()->json(['status' => 'ignored']);
        }

        $senderId = (string) ($parsedMessage['sender_id'] ?? $parsedMessage['user_id'] ?? '');
        if ($senderId !== '' && ! $this->security->isUserAllowed($senderId, $platform)) {
            return response()->json(['status' => 'unauthorized'], 403);
        }

        $channelId = (string) ($parsedMessage['channel_id'] ?? $parsedMessage['chat_id'] ?? '');
        if ($channelId !== '' && ! $this->security->isChannelAllowed($channelId, $platform)) {
            return response()->json(['status' => 'unauthorized'], 403);
        }

        $conversation = $gateway->findOrCreateConversation($parsedMessage);

        ProcessNewMemoryJob::dispatch(
            conversationId: $conversation->id,
            platform: $platform,
            content: $content,
            parsedMessage: $parsedMessage,
        );

        return response()->json([
            'status' => 'queued',
            'conversation_id' => $conversation->id,
        ], 202);
    }

    protected function verifyRequest(Request $request, string $platform): bool
    {
        if ($platform === 'telegram') {
            $configuredSecret = (string) config('services.telegram.secret_token');
            $providedSecret = $request->header('X-Telegram-Bot-Api-Secret-Token');

            return $this->security->verifyTelegramWebhook($configuredSecret, $providedSecret);
        }

        if ($platform === 'discord') {
            $signature = (string) $request->header('X-Signature-Ed25519');
            $timestamp = (string) $request->header('X-Signature-Timestamp');
            $publicKey = (string) config('services.discord.public_key');

            if ($signature === '' || $timestamp === '') {
                return true;
            }

            return $this->security->verifyDiscordWebhook(
                $publicKey,
                $request->getContent(),
                $signature,
                $timestamp,
            );
        }

        if ($platform === 'whatsapp') {
            $signature = $request->header('X-Hub-Signature-256');

            return app(WhatsAppGateway::class)->verifyWebhook($request->all(), $signature);
        }

        if ($platform === 'slack') {
            return $this->slackSignatureVerifier->verify($request);
        }

        return true;
    }

    protected function resolveGateway(string $platform): TelegramGateway|DiscordGateway|WhatsAppGateway|SlackGateway|null
    {
        return match ($platform) {
            'telegram' => app(TelegramGateway::class),
            'discord' => app(DiscordGateway::class),
            'whatsapp' => app(WhatsAppGateway::class),
            'slack' => app(SlackGateway::class),
            default => null,
        };
    }
}
