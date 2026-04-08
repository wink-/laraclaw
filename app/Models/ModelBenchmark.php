<?php

namespace App\Models;

use Database\Factories\ModelBenchmarkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModelBenchmark extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'model_id',
        'model_name',
        'last_status',
        'last_response_time_ms',
        'fastest_response_time_ms',
        'slowest_response_time_ms',
        'average_response_time_ms',
        'last_response_excerpt',
        'last_error_message',
        'last_tested_at',
        'total_runs',
        'successful_runs',
        'failed_runs',
    ];

    protected function casts(): array
    {
        return [
            'average_response_time_ms' => 'decimal:2',
            'last_tested_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ModelBenchmarkFactory
    {
        return ModelBenchmarkFactory::new();
    }
}
