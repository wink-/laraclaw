<?php

use App\Laraclaw\Modules\ModuleManager;
use App\Laraclaw\Skills\AppBuilderSkill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $appsPath = app_path('Modules');
    $routesPath = storage_path('framework/testing/laraclaw-modules/routes');
    $viewsPath = storage_path('framework/testing/laraclaw-modules/views');
    $migrationsPath = storage_path('framework/testing/laraclaw-modules/migrations');

    config()->set('laraclaw.modules.path', $appsPath);
    config()->set('laraclaw.modules.routes_path', $routesPath);
    config()->set('laraclaw.modules.views_path', $viewsPath);
    config()->set('laraclaw.modules.migrations_path', $migrationsPath);

    foreach (['HomeBlog', 'IdeasBlog', 'PhoneBlog', 'DomainBlog'] as $moduleClass) {
        $path = $appsPath.'/'.$moduleClass;
        if (File::isDirectory($path)) {
            File::deleteDirectory($path);
        }
    }

    $base = storage_path('framework/testing/laraclaw-modules');
    if (File::isDirectory($base)) {
        File::deleteDirectory($base);
    }

    File::ensureDirectoryExists($appsPath);
    File::ensureDirectoryExists($routesPath);
    File::ensureDirectoryExists($viewsPath);
    File::ensureDirectoryExists($migrationsPath);
});

afterEach(function () {
    $appsPath = app_path('Modules');
    foreach (['HomeBlog', 'IdeasBlog', 'PhoneBlog', 'DomainBlog'] as $moduleClass) {
        $path = $appsPath.'/'.$moduleClass;
        if (File::isDirectory($path)) {
            File::deleteDirectory($path);
        }
    }

    $base = storage_path('framework/testing/laraclaw-modules');

    if (File::isDirectory($base)) {
        File::deleteDirectory($base);
    }
});

it('creates a blog module with app builder skill', function () {
    $skill = app(AppBuilderSkill::class);

    $result = $skill->execute([
        'action' => 'create_app',
        'name' => 'Home Blog',
        'description' => 'My home ideas',
        'type' => 'blog',
    ]);

    expect($result)->toContain('Created Laravel MVC blog app')
        ->and(File::exists(config('laraclaw.modules.path').'/HomeBlog/module.json'))->toBeTrue()
        ->and(File::exists(config('laraclaw.modules.path').'/HomeBlog/Models/HomeBlogPost.php'))->toBeTrue()
        ->and(File::exists(config('laraclaw.modules.path').'/HomeBlog/Http/Controllers/HomeBlogPostController.php'))->toBeTrue()
        ->and(File::exists(config('laraclaw.modules.routes_path').'/home-blog.php'))->toBeTrue()
        ->and(File::exists(config('laraclaw.modules.views_path').'/home-blog/index.blade.php'))->toBeTrue();
});

it('lists generated apps', function () {
    $skill = app(AppBuilderSkill::class);

    $skill->execute([
        'action' => 'create_app',
        'name' => 'Ideas Blog',
        'type' => 'blog',
    ]);

    $result = $skill->execute([
        'action' => 'list_apps',
    ]);

    expect($result)->toContain('ideas-blog')
        ->and($result)->toContain('/ideas-blog');
});

it('creates draft and publishes post for a module', function () {
    $skill = app(AppBuilderSkill::class);

    $skill->execute([
        'action' => 'create_app',
        'name' => 'Phone Blog',
        'type' => 'blog',
    ]);

    Artisan::call('migrate', [
        '--path' => config('laraclaw.modules.migrations_path'),
        '--realpath' => true,
        '--force' => true,
    ]);

    $draft = $skill->execute([
        'action' => 'create_post_draft',
        'slug' => 'phone-blog',
        'title' => 'Voice note post',
        'content' => 'Draft content from phone voice note.',
    ]);

    expect($draft)->toContain('Saved draft');

    $published = $skill->execute([
        'action' => 'publish_post',
        'slug' => 'phone-blog',
        'post_slug' => 'voice-note-post',
    ]);

    expect($published)->toContain('Published post');

    expect(DB::table('phone_blog_posts')->count())->toBe(1)
        ->and(DB::table('phone_blog_posts')->value('status'))->toBe('published');
});

it('sets and removes module domain binding', function () {
    $skill = app(AppBuilderSkill::class);
    $manager = app(ModuleManager::class);

    $skill->execute([
        'action' => 'create_app',
        'name' => 'Domain Blog',
        'type' => 'blog',
    ]);

    $setResult = $skill->execute([
        'action' => 'set_domain',
        'slug' => 'domain-blog',
        'domain' => 'blog.example.test',
    ]);

    expect($setResult)->toContain('Set domain');

    $module = $manager->find('domain-blog');

    expect($module)->not->toBeNull()
        ->and($module['domain'])->toBe('blog.example.test');

    $clearResult = $skill->execute([
        'action' => 'set_domain',
        'slug' => 'domain-blog',
        'domain' => '',
    ]);

    expect($clearResult)->toContain('Removed domain binding');
});
