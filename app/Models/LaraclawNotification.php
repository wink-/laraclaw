<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaraclawNotification extends Model
{
    protected $fillable = [
        'user_id',
        'conversation_id',
        'gateway',
        'channel_id',
        'message',
        'cron_expression',
        'send_at',
        'status',
        'last_error',
        'sent_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'send_at' => 'datetime',
            'sent_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
