<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentCollaboration extends Model
{
    protected $fillable = [
        'conversation_id',
        'user_message',
        'planner_output',
        'executor_output',
        'reviewer_output',
        'final_output',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
