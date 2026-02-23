<?php

namespace App\Console\Commands;

use App\Laraclaw\Monitoring\MetricsCollector;
use Illuminate\Console\Command;

class LaraclawMetricsCommand extends Command
{
    protected $signature = 'laraclaw:metrics
                            {--reset : Reset all metrics}
                            {--watch : Live update every 5 seconds}';

    protected $description = 'Display Laraclaw metrics';

    public function handle(MetricsCollector $metrics): int
    {
        if ($this->option('reset')) {
            $metrics->reset();
            $this->info('Metrics reset successfully.');

            return self::SUCCESS;
        }

        if ($this->option('watch')) {
            return $this->watchMetrics($metrics);
        }

        $this->displayMetrics($metrics);

        return self::SUCCESS;
    }

    protected function displayMetrics(MetricsCollector $metrics): void
    {
        $data = $metrics->getMetrics();

        $this->newLine();
        $this->info('📊 Laraclaw Metrics');
        $this->info('===================');
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Messages Sent', $data['messages_sent']],
                ['Messages Received', $data['messages_received']],
                ['Errors', $data['errors']],
                ['Avg Response Time', $data['avg_response_time'].' ms'],
                ['Active Conversations', $data['active_conversations']],
            ]
        );

        $this->newLine();
    }

    protected function watchMetrics(MetricsCollector $metrics): int
    {
        $this->info('Watching metrics (Press Ctrl+C to stop)...');
        $this->newLine();

        while (true) {
            $this->output->write("\033[H\033[2J");
            $this->displayMetrics($metrics);
            $this->comment('Last updated: '.now()->toDateTimeString());
            sleep(5);
        }

        return self::SUCCESS;
    }
}
