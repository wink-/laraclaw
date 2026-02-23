<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'gateway',
        'gateway_conversation_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function memoryFragments(): HasMany
    {
        return $this->hasMany(MemoryFragment::class);
    }

    /**
     * Get the formatted conversation history for the LLM.
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function toPromptMessages(): array
    {
        return $this->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn (Message $message) => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->all();
    }
}
