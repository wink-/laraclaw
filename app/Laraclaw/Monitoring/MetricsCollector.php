<?php

namespace App\Laraclaw\Monitoring;

use Illuminate\Support\Facades\Cache;

class MetricsCollector
{
    protected string $prefix = 'laraclaw.metrics.';

    protected array $metrics = [
        'messages_sent' => 0,
        'messages_received' => 0,
        'errors' => 0,
        'avg_response_time' => 0,
        'active_conversations' => 0,
    ];

    public function increment(string $metric, int $value = 1): self
    {
        $current = Cache::get($this->prefix.$metric, 0);
        Cache::put($this->prefix.$metric, $current + $value, now()->addDays(30));

        return $this;
    }

    public function record(string $metric, float $value): self
    {
        if ($metric === 'response_time') {
            $this->recordResponseTime($value);
        } else {
            Cache::put($this->prefix.$metric, $value, now()->addDays(30));
        }

        return $this;
    }

    protected function recordResponseTime(float $time): void
    {
        $key = $this->prefix.'response_times';
        $times = Cache::get($key, []);

        $times[] = $time;

        // Keep only last 100 response times
        $times = array_slice($times, -100);

        Cache::put($key, $times, now()->addDays(30));

        // Calculate average
        $avg = count($times) > 0 ? array_sum($times) / count($times) : 0;
        Cache::put($this->prefix.'avg_response_time', round($avg, 2), now()->addDays(30));
    }

    public function getMetrics(): array
    {
        return [
            'messages_sent' => Cache::get($this->prefix.'messages_sent', 0),
            'messages_received' => Cache::get($this->prefix.'messages_received', 0),
            'errors' => Cache::get($this->prefix.'errors', 0),
            'avg_response_time' => Cache::get($this->prefix.'avg_response_time', 0),
            'active_conversations' => Cache::get($this->prefix.'active_conversations', 0),
        ];
    }

    public function reset(): self
    {
        foreach (array_keys($this->metrics) as $metric) {
            Cache::forget($this->prefix.$metric);
        }

        Cache::forget($this->prefix.'response_times');

        return $this;
    }

    public function getPrometheusFormat(): string
    {
        $metrics = $this->getMetrics();
        $output = '';

        foreach ($metrics as $name => $value) {
            $output .= "laraclaw_{$name} {$value}\n";
        }

        return $output;
    }
}
