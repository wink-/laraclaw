<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeartbeatRun extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'heartbeat_id',
        'instruction',
        'status',
        'response',
        'executed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'executed_at' => 'datetime',
        ];
    }
}
