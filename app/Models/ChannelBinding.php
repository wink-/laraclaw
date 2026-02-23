<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelBinding extends Model
{
    protected $fillable = [
        'gateway',
        'channel_id',
        'user_id',
        'conversation_id',
        'metadata',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'active' => 'boolean',
        ];
    }

    /**
     * Get the user associated with this binding.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the conversation associated with this binding.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Scope to get only active bindings.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope to get bindings for a specific gateway.
     */
    public function scopeForGateway($query, string $gateway)
    {
        return $query->where('gateway', $gateway);
    }

    /**
     * Scope to get a binding by gateway and channel ID.
     */
    public function scopeByChannel($query, string $gateway, string $channelId)
    {
        return $query->where('gateway', $gateway)->where('channel_id', $channelId);
    }
}
