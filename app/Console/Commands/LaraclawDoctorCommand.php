<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class LaraclawDoctorCommand extends Command
{
    protected $signature = 'laraclaw:doctor
                            {--fix : Attempt to fix issues automatically}';

    protected $description = 'Run diagnostics on Laraclaw installation';

    protected array $checks = [];

    protected int $passed = 0;

    protected int $failed = 0;

    protected int $warnings = 0;

    public function handle(): int
    {
        info('🩺 Laraclaw Doctor - Running Diagnostics');
        info('========================================');
        $this->newLine();

        // Run all checks
        $this->checkPhpExtensions();
        $this->checkDatabaseConnection();
        $this->checkDatabaseTables();
        $this->checkAiProvider();
        $this->checkIdentityFiles();
        $this->checkStoragePermissions();

        $this->newLine();
        $this->displaySummary();

        return $this->failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function checkPhpExtensions(): void
    {
        info('PHP Extensions:');

        $extensions = [
            'pdo_sqlite' => 'SQLite database support',
            'curl' => 'HTTP requests',
            'json' => 'JSON handling',
            'mbstring' => 'Multibyte string handling',
            'openssl' => 'Encryption',
        ];

        foreach ($extensions as $ext => $description) {
            if (extension_loaded($ext)) {
                info("  ✓ {$ext} ({$description})");
                $this->passed++;
            } else {
                error("  ✗ {$ext} ({$description}) - NOT LOADED");
                $this->failed++;
            }
        }
        $this->newLine();
    }

    protected function checkDatabaseConnection(): void
    {
        info('Database Connection:');

        try {
            $connection = config('database.default');
            info("  ✓ Using: {$connection}");

            \DB::connection()->getPdo();
            info('  ✓ Connection successful');
            $this->passed++;
        } catch (\Exception $e) {
            error('  ✗ Connection failed: '.$e->getMessage());
            $this->failed++;
        }
        $this->newLine();
    }

    protected function checkDatabaseTables(): void
    {
        info('Database Tables:');

        $tables = [
            'conversations' => 'Stores conversation history',
            'messages' => 'Stores individual messages',
            'memory_fragments' => 'Stores long-term memories',
        ];

        foreach ($tables as $table => $description) {
            if (Schema::hasTable($table)) {
                info("  ✓ {$table} ({$description})");
                $this->passed++;
            } else {
                error("  ✗ {$table} ({$description}) - MISSING");
                $this->failed++;
            }
        }
        $this->newLine();
    }

    protected function checkAiProvider(): void
    {
        info('AI Provider Configuration:');

        $provider = env('AI_PROVIDER', 'openai');
        info("  Provider: {$provider}");

        $configured = match ($provider) {
            'openai' => ! empty(env('OPENAI_API_KEY')),
            'anthropic' => ! empty(env('ANTHROPIC_API_KEY')),
            'ollama' => $this->checkOllamaConnection(),
            default => false,
        };

        if ($configured) {
            info('  ✓ AI provider configured');
            $this->passed++;
        } else {
            warning('  ⚠ AI provider not fully configured');
            $this->warnings++;
        }
        $this->newLine();
    }

    protected function checkOllamaConnection(): bool
    {
        $host = env('OLLAMA_HOST', 'http://localhost:11434');

        try {
            $response = Http::timeout(2)->get("{$host}/api/tags");

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function checkIdentityFiles(): void
    {
        info('Identity Files:');

        $files = [
            storage_path('laraclaw/IDENTITY.md') => 'Assistant identity',
            storage_path('laraclaw/SOUL.md') => 'Assistant personality',
        ];

        foreach ($files as $path => $description) {
            if (File::exists($path)) {
                info("  ✓ {$description} exists");
                $this->passed++;
            } else {
                warning("  ⚠ {$description} missing (optional)");
                $this->warnings++;
            }
        }
        $this->newLine();
    }

    protected function checkStoragePermissions(): void
    {
        info('Storage Permissions:');

        $paths = [
            storage_path('app'),
            storage_path('logs'),
            storage_path('laraclaw'),
        ];

        foreach ($paths as $path) {
            if (File::isDirectory($path) && File::isWritable($path)) {
                info("  ✓ {$path} writable");
                $this->passed++;
            } else {
                error("  ✗ {$path} not writable");
                $this->failed++;
            }
        }
        $this->newLine();
    }

    protected function displaySummary(): void
    {
        info('Summary:');
        info("  ✓ Passed: {$this->passed}");
        warning("  ⚠ Warnings: {$this->warnings}");
        error("  ✗ Failed: {$this->failed}");

        if ($this->failed > 0) {
            $this->newLine();
            warning('Run <comment>php artisan laraclaw:install</comment> to fix missing components');
        } elseif ($this->warnings > 0) {
            $this->newLine();
            info('System is functional but some optional features may not work.');
        } else {
            $this->newLine();
            info('All systems operational! 🎉');
        }
    }
}
