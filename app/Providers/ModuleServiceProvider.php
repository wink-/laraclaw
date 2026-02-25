<?php

namespace App\Providers;

use App\Laraclaw\Modules\ModuleManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ModuleManager::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(ModuleManager $moduleManager): void
    {
        if (! config('laraclaw.modules.enabled', true)) {
            return;
        }

        foreach ($moduleManager->allModules() as $module) {
            if (! is_file($module['routes_path'])) {
                continue;
            }

            $this->registerModuleRoutes($module);
        }
    }

    /**
     * @param  array<string, mixed>  $module
     */
    protected function registerModuleRoutes(array $module): void
    {
        $group = [
            'middleware' => ['web'],
        ];

        if (! empty($module['domain'])) {
            $group['domain'] = $module['domain'];
        } else {
            $group['prefix'] = trim((string) $module['prefix'], '/');
        }

        Route::group($group, $module['routes_path']);
    }
}
