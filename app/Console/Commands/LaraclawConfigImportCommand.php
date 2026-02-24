<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LaraclawConfigImportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laraclaw:config:import
        {path=laraclaw/config-export.json : Storage path to import from}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Laraclaw configuration bundle';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if (! Storage::disk('local')->exists($path)) {
            $this->error('Import file not found: storage/app/'.$path);

            return self::FAILURE;
        }

        $contents = Storage::disk('local')->get($path);
        $payload = json_decode($contents, true);

        if (! is_array($payload)) {
            $this->error('Invalid import payload.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($payload): void {
            $this->importTableRows('skill_plugins', (array) ($payload['skill_plugins'] ?? []), ['class_name']);
            $this->importTableRows('channel_bindings', (array) ($payload['channel_bindings'] ?? []), ['gateway', 'channel_id']);

            if (DB::getSchemaBuilder()->hasTable('laraclaw_scheduled_tasks')) {
                $this->importTableRows('laraclaw_scheduled_tasks', (array) ($payload['scheduled_tasks'] ?? []), ['id']);
            }

            if (DB::getSchemaBuilder()->hasTable('laraclaw_notifications')) {
                $this->importTableRows('laraclaw_notifications', (array) ($payload['notifications'] ?? []), ['id']);
            }
        });

        $this->writeIdentityFiles((array) ($payload['identity'] ?? []));

        $this->info('Laraclaw configuration imported from storage/app/'.$path);

        return self::SUCCESS;
    }

    protected function importTableRows(string $table, array $rows, array $uniqueBy): void
    {
        if (! DB::getSchemaBuilder()->hasTable($table)) {
            return;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            DB::table($table)->updateOrInsert(
                collect($row)->only($uniqueBy)->all(),
                collect($row)->except(['id'])->all(),
            );
        }
    }

    protected function writeIdentityFiles(array $identity): void
    {
        $identityPath = (string) config('laraclaw.identity.path', storage_path('laraclaw'));
        $identityFile = (string) config('laraclaw.identity.identity_file', 'IDENTITY.md');
        $soulFile = (string) config('laraclaw.identity.soul_file', 'SOUL.md');
        $aieosFile = (string) config('laraclaw.identity.aieos_file', 'aieos.json');

        if (! is_dir($identityPath)) {
            mkdir($identityPath, 0755, true);
        }

        if (isset($identity['identity']) && is_string($identity['identity'])) {
            file_put_contents($identityPath.'/'.$identityFile, $identity['identity']);
        }

        if (isset($identity['soul']) && is_string($identity['soul'])) {
            file_put_contents($identityPath.'/'.$soulFile, $identity['soul']);
        }

        if (isset($identity['aieos']) && is_string($identity['aieos'])) {
            file_put_contents($identityPath.'/'.$aieosFile, $identity['aieos']);
        }
    }
}
