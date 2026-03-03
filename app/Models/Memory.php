<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Memory extends Model
{
    use HasFactory;

    protected $table = 'memories';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'platform_source',
        'content',
        'embedding',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'metadata' => 'array',
        ];
    }

    public function getConnectionName(): ?string
    {
        if (app()->runningUnitTests()) {
            return config('database.default');
        }

        return config('laraclaw.memory.connection', config('database.default'));
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
