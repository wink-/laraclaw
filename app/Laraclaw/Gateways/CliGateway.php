<?php

namespace App\Laraclaw\Gateways;

use App\Models\Conversation;

class CliGateway extends BaseGateway
{
    protected string $currentSessionId;

    public function __construct()
    {
        $this->currentSessionId = 'cli-'.uniqid();
    }

    public function getName(): string
    {
        return 'cli';
    }

    /**
     * Parse CLI input into a standardized message format.
     *
     * @param  array<string, mixed>  $payload
     * @return array{content: string, sender_id: string, sender_name: ?string, timestamp: ?string}
     */
    public function parseIncomingMessage(array $payload): array
    {
        return [
            'content' => $payload['content'] ?? $payload['message'] ?? '',
            'sender_id' => $payload['sender_id'] ?? 'cli-user',
            'sender_name' => $payload['sender_name'] ?? 'CLI User',
            'timestamp' => $payload['timestamp'] ?? now()->toIso8601String(),
        ];
    }

    /**
     * Find or create a CLI conversation.
     */
    public function findOrCreateConversation(array $parsedMessage): Conversation
    {
        $existingConversation = $this->findConversationByIdentifier($this->currentSessionId);

        if ($existingConversation) {
            return $existingConversation;
        }

        return $this->createConversation(
            $this->currentSessionId,
            'CLI Session',
            null
        );
    }

    /**
     * Output message to CLI (stdout).
     */
    public function sendMessage(Conversation $conversation, string $content): bool
    {
        // For CLI, we just output to stdout
        echo $content.PHP_EOL;

        return true;
    }

    /**
     * Start a new CLI session.
     */
    public function startNewSession(): void
    {
        $this->currentSessionId = 'cli-'.uniqid();
    }

    /**
     * Get the current session ID.
     */
    public function getSessionId(): string
    {
        return $this->currentSessionId;
    }

    /**
     * Set the session ID (useful for testing).
     */
    public function setSessionId(string $sessionId): void
    {
        $this->currentSessionId = $sessionId;
    }
}
