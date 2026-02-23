<?php

namespace App\Laraclaw\Security;

enum AutonomyLevel: string
{
    case READONLY = 'readonly';
    case SUPERVISED = 'supervised';
    case FULL = 'full';

    public function canWrite(): bool
    {
        return $this !== self::READONLY;
    }

    public function requiresApproval(): bool
    {
        return $this === self::SUPERVISED;
    }

    public function canExecute(): bool
    {
        return $this === self::FULL;
    }
}

class SecurityManager
{
    protected AutonomyLevel $autonomyLevel;

    protected array $allowedUsers = [];

    protected array $blockedUsers = [];

    protected array $allowedChannels = [];

    protected bool $allowlistEnabled = false;

    public function __construct()
    {
        $this->loadConfiguration();
    }

    /**
     * Load security configuration from config/environment.
     */
    protected function loadConfiguration(): void
    {
        $this->autonomyLevel = AutonomyLevel::tryFrom(
            config('laraclaw.security.autonomy', 'supervised')
        ) ?? AutonomyLevel::SUPERVISED;

        $this->allowedUsers = config('laraclaw.security.allowed_users', []);
        $this->blockedUsers = config('laraclaw.security.blocked_users', []);
        $this->allowedChannels = config('laraclaw.security.allowed_channels', []);
        $this->allowlistEnabled = config('laraclaw.security.allowlist_enabled', false);
    }

    /**
     * Check if a user is allowed to interact with the assistant.
     */
    public function isUserAllowed(string $userId, string $gateway): bool
    {
        // Check blocklist first
        $userKey = "{$gateway}:{$userId}";
        if (in_array($userKey, $this->blockedUsers) || in_array($userId, $this->blockedUsers)) {
            return false;
        }

        // If allowlist is disabled, allow all (except blocked)
        if (! $this->allowlistEnabled) {
            return true;
        }

        // Check allowlist
        return in_array($userKey, $this->allowedUsers)
            || in_array($userId, $this->allowedUsers)
            || in_array('*', $this->allowedUsers);
    }

    /**
     * Check if a channel is allowed.
     */
    public function isChannelAllowed(string $channelId, string $gateway): bool
    {
        if (empty($this->allowedChannels)) {
            return true;
        }

        $channelKey = "{$gateway}:{$channelId}";

        return in_array($channelKey, $this->allowedChannels)
            || in_array($channelId, $this->allowedChannels)
            || in_array('*', $this->allowedChannels);
    }

    /**
     * Verify webhook signature for Telegram.
     */
    public function verifyTelegramWebhook(string $secretToken, ?string $receivedToken): bool
    {
        if (empty($secretToken)) {
            return true; // Skip if no secret configured
        }

        return hash_equals($secretToken, $receivedToken ?? '');
    }

    /**
     * Verify webhook signature for Discord.
     */
    public function verifyDiscordWebhook(string $publicKey, string $body, string $signature, string $timestamp): bool
    {
        if (empty($publicKey)) {
            return true; // Skip if no public key configured
        }

        // Discord uses Ed25519 signatures
        $message = $timestamp.$body;

        try {
            $binarySignature = sodium_hex2bin($signature);
            $binaryKey = sodium_hex2bin($publicKey);

            return sodium_crypto_sign_verify_detached($binarySignature, $message, $binaryKey);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the current autonomy level.
     */
    public function getAutonomyLevel(): AutonomyLevel
    {
        return $this->autonomyLevel;
    }

    /**
     * Set the autonomy level.
     */
    public function setAutonomyLevel(AutonomyLevel $level): self
    {
        $this->autonomyLevel = $level;

        return $this;
    }

    /**
     * Check if an action is permitted under current autonomy level.
     */
    public function canPerformAction(string $action): bool
    {
        return match ($action) {
            'read', 'search', 'recall' => true,
            'write', 'remember', 'send' => $this->autonomyLevel->canWrite(),
            'execute', 'delete', 'forget' => $this->autonomyLevel->canExecute(),
            default => false,
        };
    }

    /**
     * Check if an action requires approval.
     */
    public function requiresApproval(string $action): bool
    {
        if (! $this->canPerformAction($action)) {
            return false;
        }

        return $this->autonomyLevel->requiresApproval();
    }

    /**
     * Add a user to the allowlist.
     */
    public function allowUser(string $userId, ?string $gateway = null): self
    {
        $key = $gateway ? "{$gateway}:{$userId}" : $userId;

        if (! in_array($key, $this->allowedUsers)) {
            $this->allowedUsers[] = $key;
        }

        // Remove from blocklist if present
        $this->blockedUsers = array_filter(
            $this->blockedUsers,
            fn ($u) => $u !== $key
        );

        return $this;
    }

    /**
     * Block a user.
     */
    public function blockUser(string $userId, ?string $gateway = null): self
    {
        $key = $gateway ? "{$gateway}:{$userId}" : $userId;

        if (! in_array($key, $this->blockedUsers)) {
            $this->blockedUsers[] = $key;
        }

        // Remove from allowlist if present
        $this->allowedUsers = array_filter(
            $this->allowedUsers,
            fn ($u) => $u !== $key
        );

        return $this;
    }

    /**
     * Enable or disable the allowlist.
     */
    public function setAllowlistEnabled(bool $enabled): self
    {
        $this->allowlistEnabled = $enabled;

        return $this;
    }

    /**
     * Get security status summary.
     */
    public function getStatus(): array
    {
        return [
            'autonomy_level' => $this->autonomyLevel->value,
            'allowlist_enabled' => $this->allowlistEnabled,
            'allowed_users_count' => count($this->allowedUsers),
            'blocked_users_count' => count($this->blockedUsers),
            'allowed_channels_count' => count($this->allowedChannels),
        ];
    }

    /**
     * Validate a file path for safe filesystem operations.
     */
    public function isPathAllowed(string $path, ?string $basePath = null): bool
    {
        $basePath = $basePath ?? config('laraclaw.security.filesystem_scope', storage_path('laraclaw'));

        // Normalize paths
        $realBasePath = realpath($basePath);
        $realPath = realpath($path);

        // If path doesn't exist yet (for creation), check parent directory
        if ($realPath === false) {
            $parentDir = dirname($path);
            $realParent = realpath($parentDir);

            return $realParent !== false && str_starts_with($realParent, $realBasePath);
        }

        return str_starts_with($realPath, $realBasePath);
    }

    /**
     * Get allowed filesystem scope.
     */
    public function getFilesystemScope(): string
    {
        return config('laraclaw.security.filesystem_scope', storage_path('laraclaw'));
    }
}
