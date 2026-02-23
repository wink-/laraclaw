<?php

namespace App\Laraclaw\Tunnels\Contracts;

interface TunnelServiceInterface
{
    /**
     * Start the tunnel service.
     *
     * @param  array<string, mixed>  $options
     */
    public function start(array $options = []): bool;

    /**
     * Stop the tunnel service.
     */
    public function stop(): bool;

    /**
     * Check if the tunnel is currently active.
     */
    public function isActive(): bool;

    /**
     * Get the public URL of the tunnel.
     */
    public function getUrl(): ?string;

    /**
     * Get the name of the tunnel provider.
     */
    public function getName(): string;
}
