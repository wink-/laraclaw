<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaraclawProvider extends Model
{
    protected $table = 'laraclaw_providers';

    protected $fillable = [
        'key',
        'name',
        'driver',
        'key_env',
        'url',
    ];

    protected function casts(): array
    {
        return [];
    }
}
