<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LaraclawConfigExportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laraclaw:config:export
        {path=laraclaw/config-export.json : Storage path for the export bundle}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export Laraclaw configuration bundle';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = (string) $this->argument('path');

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'app' => [
                'name' => config('app.name'),
                'env' => config('app.env'),
            ],
            'laraclaw' => config('laraclaw'),
            'identity' => $this->readIdentityFiles(),
            'skill_plugins' => DB::getSchemaBuilder()->hasTable('skill_plugins')
                ? DB::table('skill_plugins')->get()->all()
                : [],
            'channel_bindings' => DB::getSchemaBuilder()->hasTable('channel_bindings')
                ? DB::table('channel_bindings')->get()->all()
                : [],
            'scheduled_tasks' => DB::getSchemaBuilder()->hasTable('laraclaw_scheduled_tasks')
                ? DB::table('laraclaw_scheduled_tasks')->get()->all()
                : [],
            'notifications' => DB::getSchemaBuilder()->hasTable('laraclaw_notifications')
                ? DB::table('laraclaw_notifications')->get()->all()
                : [],
        ];

        Storage::disk('local')->put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info('Laraclaw configuration exported to storage/app/'.$path);

        return self::SUCCESS;
    }

    protected function readIdentityFiles(): array
    {
        $identityPath = (string) config('laraclaw.identity.path', storage_path('laraclaw'));
        $identityFile = (string) config('laraclaw.identity.identity_file', 'IDENTITY.md');
        $soulFile = (string) config('laraclaw.identity.soul_file', 'SOUL.md');
        $aieosFile = (string) config('laraclaw.identity.aieos_file', 'aieos.json');

        return [
            'identity' => is_file($identityPath.'/'.$identityFile) ? file_get_contents($identityPath.'/'.$identityFile) : null,
            'soul' => is_file($identityPath.'/'.$soulFile) ? file_get_contents($identityPath.'/'.$soulFile) : null,
            'aieos' => is_file($identityPath.'/'.$aieosFile) ? file_get_contents($identityPath.'/'.$aieosFile) : null,
        ];
    }
}
