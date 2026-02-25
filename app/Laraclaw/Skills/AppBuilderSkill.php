<?php

namespace App\Laraclaw\Skills;

use App\Laraclaw\Modules\ModuleManager;
use App\Laraclaw\Skills\Contracts\SkillInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AppBuilderSkill implements SkillInterface, Tool
{
    public function __construct(
        protected ModuleManager $modules,
    ) {}

    public function name(): string
    {
        return 'app_builder';
    }

    public function description(): Stringable|string
    {
        return 'Create and manage standard Laravel MVC app modules (models, controllers, migrations, routes, views).';
    }

    public function execute(array $parameters): string
    {
        $action = $parameters['action'] ?? 'list_apps';

        return match ($action) {
            'create_app' => $this->createApp($parameters),
            'list_apps' => $this->listApps(),
            'create_post_draft' => $this->createPost($parameters, 'draft'),
            'publish_post' => $this->publishPost($parameters),
            'list_posts' => $this->listPosts($parameters),
            'set_domain' => $this->setDomain($parameters),
            default => "Unknown action: {$action}.",
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->enum([
                'create_app',
                'list_apps',
                'create_post_draft',
                'publish_post',
                'list_posts',
                'set_domain',
            ])->description('App builder action'),
            'name' => $schema->string()->description('App name (for create_app)'),
            'description' => $schema->string()->description('App description (optional)'),
            'type' => $schema->string()->description('App type, currently "blog"'),
            'slug' => $schema->string()->description('App slug for module actions'),
            'title' => $schema->string()->description('Post title'),
            'content' => $schema->string()->description('Post content body'),
            'post_slug' => $schema->string()->description('Post slug for publish action'),
            'status' => $schema->string()->description('Post status filter for list_posts'),
            'domain' => $schema->string()->description('Optional custom domain to bind module routes'),
        ];
    }

    public function toTool(): Tool
    {
        return $this;
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->execute($request->all());
    }

    protected function createApp(array $parameters): string
    {
        $name = trim((string) ($parameters['name'] ?? ''));

        if ($name === '') {
            return 'Error: name is required to create an app.';
        }

        $type = strtolower(trim((string) ($parameters['type'] ?? 'blog')));

        if ($type !== 'blog') {
            return 'Error: only "blog" app type is supported in this MVP.';
        }

        $slug = Str::slug($name);
        $moduleClass = Str::studly($slug);

        if ($slug === '') {
            return 'Error: could not generate a valid app slug from the name.';
        }

        if ($this->modules->find($slug)) {
            return "App '{$slug}' already exists.";
        }

        $modulePath = $this->modules->appsPath().'/'.$moduleClass;
        $modelDirectory = $modulePath.'/Models';
        $controllerDirectory = $modulePath.'/Http/Controllers';
        $routesDirectory = (string) config('laraclaw.modules.routes_path', base_path('routes/modules'));
        $viewsDirectory = (string) config('laraclaw.modules.views_path', resource_path('views/modules')).'/'.$slug;

        File::ensureDirectoryExists($modelDirectory);
        File::ensureDirectoryExists($controllerDirectory);
        File::ensureDirectoryExists($routesDirectory);
        File::ensureDirectoryExists($viewsDirectory);

        $table = str_replace('-', '_', $slug).'_posts';
        $modelClass = "App\\Modules\\{$moduleClass}\\Models\\{$moduleClass}Post";
        $controllerClass = "App\\Modules\\{$moduleClass}\\Http\\Controllers\\{$moduleClass}PostController";
        $routeFile = $routesDirectory.'/'.$slug.'.php';
        $modelFile = $modelDirectory.'/'.$moduleClass.'Post.php';
        $controllerFile = $controllerDirectory.'/'.$moduleClass.'PostController.php';
        $migrationsDirectory = (string) config('laraclaw.modules.migrations_path', database_path('migrations'));
        File::ensureDirectoryExists($migrationsDirectory);
        $migrationFile = $migrationsDirectory.'/'.$this->migrationFileName($table);

        $manifest = [
            'name' => $name,
            'slug' => $slug,
            'description' => trim((string) ($parameters['description'] ?? '')),
            'type' => 'blog',
            'prefix' => $slug,
            'domain' => null,
            'table' => $table,
            'model_class' => $modelClass,
            'controller_class' => $controllerClass,
            'routes_path' => $routeFile,
            'views_path' => $viewsDirectory,
            'created_at' => now()->toIso8601String(),
        ];

        File::put($modulePath.'/module.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->writeModel($modelFile, $moduleClass, $table);
        $this->writeController($controllerFile, $moduleClass, $slug, $name);
        $this->writeRoutes($routeFile, $controllerClass);
        $this->writeViews($viewsDirectory, $name, $slug);
        $this->writeMigration($migrationFile, $table);

        return "Created Laravel MVC blog app '{$name}' at /{$slug}. Run 'php artisan migrate' before creating posts.";
    }

    protected function listApps(): string
    {
        $modules = $this->modules->allModules();

        if (empty($modules)) {
            return 'No generated apps found yet.';
        }

        $lines = collect($modules)->map(function (array $module): string {
            $location = $module['domain'] ? "domain={$module['domain']}" : '/'.$module['prefix'];

            return "- {$module['name']} ({$module['slug']}) => {$location}";
        })->implode("\n");

        return "Generated apps:\n{$lines}";
    }

    protected function createPost(array $parameters, string $status): string
    {
        $slug = Str::slug((string) ($parameters['slug'] ?? ''));
        $title = trim((string) ($parameters['title'] ?? ''));
        $content = trim((string) ($parameters['content'] ?? ''));

        if ($slug === '' || $title === '' || $content === '') {
            return 'Error: slug, title, and content are required for create_post_draft.';
        }

        $module = $this->modules->find($slug);

        if (! $module) {
            return "App '{$slug}' not found.";
        }

        $modelClass = $module['model_class'] ?? null;
        $table = $module['table'] ?? null;

        if (! is_string($modelClass) || ! class_exists($modelClass)) {
            return "App '{$slug}' model class not found. Ensure module files exist and autoload is updated.";
        }

        if (! is_string($table) || ! Schema::hasTable($table)) {
            return "Table '{$table}' does not exist yet. Run 'php artisan migrate' first.";
        }

        $postSlug = Str::slug($title);

        if ($modelClass::query()->where('slug', $postSlug)->exists()) {
            return "A post with slug '{$postSlug}' already exists in '{$slug}'.";
        }

        $post = $modelClass::query()->create([
            'slug' => $postSlug,
            'title' => $title,
            'excerpt' => Str::limit(strip_tags($content), 180),
            'content' => $content,
            'status' => $status,
            'published_at' => null,
        ]);

        return "Saved draft '{$post->title}' in app '{$slug}' as post slug '{$post->slug}'.";
    }

    protected function publishPost(array $parameters): string
    {
        $slug = Str::slug((string) ($parameters['slug'] ?? ''));
        $postSlug = Str::slug((string) ($parameters['post_slug'] ?? ''));

        if ($slug === '' || $postSlug === '') {
            return 'Error: slug and post_slug are required for publish_post.';
        }

        $module = $this->modules->find($slug);

        if (! $module) {
            return "App '{$slug}' not found.";
        }

        $modelClass = $module['model_class'] ?? null;
        $table = $module['table'] ?? null;

        if (! is_string($modelClass) || ! class_exists($modelClass)) {
            return "App '{$slug}' model class not found. Ensure module files exist and autoload is updated.";
        }

        if (! is_string($table) || ! Schema::hasTable($table)) {
            return "Table '{$table}' does not exist yet. Run 'php artisan migrate' first.";
        }

        $post = $modelClass::query()->where('slug', $postSlug)->first();

        if (! $post) {
            return "Post '{$postSlug}' not found in '{$slug}'.";
        }

        $post->status = 'published';
        $post->published_at = now();
        $post->save();

        return "Published post '{$postSlug}' in '{$slug}'.";
    }

    protected function listPosts(array $parameters): string
    {
        $slug = Str::slug((string) ($parameters['slug'] ?? ''));
        $status = strtolower(trim((string) ($parameters['status'] ?? '')));

        if ($slug === '') {
            return 'Error: slug is required for list_posts.';
        }

        $module = $this->modules->find($slug);

        if (! $module) {
            return "App '{$slug}' not found.";
        }

        $modelClass = $module['model_class'] ?? null;
        $table = $module['table'] ?? null;

        if (! is_string($modelClass) || ! class_exists($modelClass)) {
            return "App '{$slug}' model class not found. Ensure module files exist and autoload is updated.";
        }

        if (! is_string($table) || ! Schema::hasTable($table)) {
            return "Table '{$table}' does not exist yet. Run 'php artisan migrate' first.";
        }

        $posts = $modelClass::query()
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->limit(30)
            ->get();

        if ($posts->isEmpty()) {
            return "No posts found in '{$slug}'.";
        }

        $lines = $posts->map(fn ($post) => "- [{$post->status}] {$post->title} ({$post->slug})")->implode("\n");

        return "Posts in '{$slug}':\n{$lines}";
    }

    protected function setDomain(array $parameters): string
    {
        $slug = Str::slug((string) ($parameters['slug'] ?? ''));
        $domain = trim((string) ($parameters['domain'] ?? ''));

        if ($slug === '') {
            return 'Error: slug is required for set_domain.';
        }

        if (! $this->modules->setDomain($slug, $domain === '' ? null : $domain)) {
            return "App '{$slug}' not found.";
        }

        if ($domain === '') {
            return "Removed domain binding for '{$slug}'. Routes now use /{$slug}.";
        }

        return "Set domain '{$domain}' for '{$slug}'.";
    }

    protected function migrationFileName(string $table): string
    {
        return now()->format('Y_m_d_His').'_create_'.$table.'_table.php';
    }

    protected function writeModel(string $path, string $moduleClass, string $table): void
    {
        $template = <<<'PHP'
<?php

namespace App\Modules\{MODULE_CLASS}\Models;

use Illuminate\Database\Eloquent\Model;

class {MODULE_CLASS}Post extends Model
{
    protected $table = '{TABLE}';

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }
}
PHP;

        $content = strtr($template, [
            '{MODULE_CLASS}' => $moduleClass,
            '{TABLE}' => $table,
        ]);

        File::put($path, $content);
    }

    protected function writeController(string $path, string $moduleClass, string $slug, string $name): void
    {
        $viewPrefix = 'modules.'.$slug;

        $template = <<<'PHP'
<?php

namespace App\Modules\{MODULE_CLASS}\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\{MODULE_CLASS}\Models\{MODULE_CLASS}Post;
use Illuminate\View\View;

class {MODULE_CLASS}PostController extends Controller
{
    public function index(): View
    {
        $posts = {MODULE_CLASS}Post::query()
            ->where('status', 'published')
            ->latest('published_at')
            ->latest('id')
            ->get();

        return view('{VIEW_PREFIX}.index', [
            'posts' => $posts,
            'moduleName' => {MODULE_NAME},
        ]);
    }

    public function show(string $postSlug): View
    {
        $post = {MODULE_CLASS}Post::query()
            ->where('slug', $postSlug)
            ->where('status', 'published')
            ->firstOrFail();

        return view('{VIEW_PREFIX}.post', ['post' => $post]);
    }
}
PHP;

        $content = strtr($template, [
            '{MODULE_CLASS}' => $moduleClass,
            '{VIEW_PREFIX}' => $viewPrefix,
            '{MODULE_NAME}' => $this->quote($name),
        ]);

        File::put($path, $content);
    }

    protected function writeRoutes(string $path, string $controllerClass): void
    {
        $content = <<<PHP
<?php

use Illuminate\Support\Facades\Route;

Route::get('/', [{$controllerClass}::class, 'index'])
    ->name('module.index');

Route::get('/post/{postSlug}', [{$controllerClass}::class, 'show'])
    ->name('module.post.show');

PHP;

        File::put($path, $content);
    }

    protected function writeViews(string $viewsPath, string $name, string $slug): void
    {
        $index = <<<BLADE
<div class="max-w-3xl mx-auto px-4 py-10">
    <h1 class="text-3xl font-bold mb-2">{$name}</h1>
    <p class="text-gray-600 mb-8">Published posts</p>

    @if(\$posts->isEmpty())
        <p class="text-gray-500">No published posts yet.</p>
    @else
        <div class="space-y-4">
            @foreach(\$posts as \$post)
                <article class="border rounded-xl p-4 bg-white">
                    <h2 class="text-xl font-semibold mb-1">
                        <a href="/{{ $slug }}/post/{{ \$post->slug }}" class="text-indigo-600 hover:text-indigo-700">
                            {{ \$post->title }}
                        </a>
                    </h2>
                    @if(\$post->excerpt)
                        <p class="text-gray-600">{{ \$post->excerpt }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</div>
BLADE;

        $post = <<<BLADE
<article class="max-w-3xl mx-auto px-4 py-10">
    <a href="/{{ $slug }}" class="text-indigo-600 hover:text-indigo-700">← Back</a>
    <h1 class="text-4xl font-bold mt-4 mb-4">{{ \$post->title }}</h1>
    <div class="prose max-w-none whitespace-pre-wrap">{{ \$post->content }}</div>
</article>
BLADE;

        File::put($viewsPath.'/index.blade.php', $index);
        File::put($viewsPath.'/post.blade.php', $post);
    }

    protected function writeMigration(string $path, string $table): void
    {
        $template = <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('{TABLE}', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('{TABLE}');
    }
};
PHP;

        $migration = strtr($template, [
            '{TABLE}' => $table,
        ]);

        if (! File::exists($path)) {
            File::put($path, $migration);
        }
    }

    protected function quote(string $value): string
    {
        return var_export($value, true);
    }
}
