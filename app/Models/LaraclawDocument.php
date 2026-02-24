<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaraclawDocument extends Model
{
    protected $fillable = [
        'user_id',
        'original_name',
        'stored_path',
        'mime_type',
        'size',
        'provider_file_id',
        'vector_store_id',
        'indexed',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'indexed' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
