<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRequest extends Model
{
    /** @use HasFactory<\Database\Factories\ApprovalRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'action',
        'payload',
        'status',
        'approval_token',
        'requester_gateway',
        'requester_id',
        'approver_id',
        'notes',
        'expires_at',
        'approved_at',
        'rejected_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
