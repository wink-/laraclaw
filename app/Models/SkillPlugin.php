<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkillPlugin extends Model
{
    protected $fillable = [
        'name',
        'class_name',
        'description',
        'enabled',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
