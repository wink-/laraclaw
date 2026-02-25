<?php

namespace App\Laraclaw\Modules;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModuleManager
{
    protected string $appsPath;

    public function __construct(?string $appsPath = null)
    {
        $this->appsPath = (string) ($appsPath ?: config('laraclaw.modules.path', app_path('Modules')));

        config()->set('laraclaw.modules.path', $this->appsPath);
    }

    public function appsPath(): string
    {
        $appsPath = $this->appsPath !== ''
            ? $this->appsPath
            : (string) config('laraclaw.modules.path', app_path('Modules'));

        if (! File::isDirectory($appsPath)) {
            File::makeDirectory($appsPath, 0755, true);
        }

        return $appsPath;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allModules(): array
    {
        $modules = [];
        $routesBasePath = (string) config('laraclaw.modules.routes_path', base_path('routes/modules'));
        $viewsBasePath = (string) config('laraclaw.modules.views_path', resource_path('views/modules'));

        foreach (File::directories($this->appsPath()) as $directory) {
            $manifestPath = $directory.'/module.json';

            if (! File::exists($manifestPath)) {
                continue;
            }

            $manifest = json_decode((string) File::get($manifestPath), true) ?: [];
            $slug = $manifest['slug'] ?? basename($directory);
            $prefix = $manifest['prefix'] ?? $slug;
            $routesPath = $manifest['routes_path'] ?? $routesBasePath.'/'.$slug.'.php';
            $viewsPath = $manifest['views_path'] ?? $viewsBasePath.'/'.$slug;

            $modules[] = [
                'slug' => $slug,
                'name' => $manifest['name'] ?? Str::headline($slug),
                'description' => $manifest['description'] ?? null,
                'prefix' => $prefix,
                'domain' => $manifest['domain'] ?? null,
                'type' => $manifest['type'] ?? 'app',
                'path' => $directory,
                'manifest_path' => $manifestPath,
                'routes_path' => $routesPath,
                'views_path' => $viewsPath,
                'model_class' => $manifest['model_class'] ?? null,
                'controller_class' => $manifest['controller_class'] ?? null,
                'table' => $manifest['table'] ?? null,
                'created_at' => $manifest['created_at'] ?? null,
            ];
        }

        usort($modules, fn (array $a, array $b): int => strcmp($a['slug'], $b['slug']));

        return $modules;
    }

    public function find(string $slug): ?array
    {
        $normalizedSlug = Str::slug($slug);

        return collect($this->allModules())
            ->first(fn (array $module) => $module['slug'] === $normalizedSlug);
    }

    public function setDomain(string $slug, ?string $domain): bool
    {
        $module = $this->find($slug);

        if (! $module) {
            return false;
        }

        $manifest = json_decode((string) File::get($module['manifest_path']), true) ?: [];
        $manifest['domain'] = $domain ?: null;
        $manifest['updated_at'] = now()->toIso8601String();

        File::put($module['manifest_path'], json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return true;
    }
}
