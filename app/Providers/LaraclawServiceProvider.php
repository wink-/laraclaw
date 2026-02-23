<?php

namespace App\Providers;

use App\Laraclaw\Agents\CoreAgent;
use App\Laraclaw\Gateways\CliGateway;
use App\Laraclaw\Gateways\DiscordGateway;
use App\Laraclaw\Gateways\TelegramGateway;
use App\Laraclaw\Identity\IdentityManager;
use App\Laraclaw\Laraclaw;
use App\Laraclaw\Memory\MemoryManager;
use App\Laraclaw\Security\SecurityManager;
use App\Laraclaw\Skills\CalculatorSkill;
use App\Laraclaw\Skills\MemorySkill;
use App\Laraclaw\Skills\TimeSkill;
use App\Laraclaw\Skills\WebSearchSkill;
use Illuminate\Support\ServiceProvider;

class LaraclawServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register MemoryManager as singleton
        $this->app->singleton(MemoryManager::class);

        // Register SecurityManager as singleton
        $this->app->singleton(SecurityManager::class);

        // Register IdentityManager as singleton
        $this->app->singleton(IdentityManager::class);

        // Register skills as singletons
        $this->app->singleton(TimeSkill::class);
        $this->app->singleton(CalculatorSkill::class);
        $this->app->singleton(WebSearchSkill::class);
        $this->app->singleton(MemorySkill::class);

        // Tag skills
        $this->app->tag([
            TimeSkill::class,
            CalculatorSkill::class,
            WebSearchSkill::class,
            MemorySkill::class,
        ], 'laraclaw.skills');

        // Register CoreAgent with skills
        $this->app->singleton(CoreAgent::class, function ($app) {
            $skills = collect($app->tagged('laraclaw.skills'));

            return new CoreAgent($skills);
        });

        // Register Gateways
        $this->app->singleton(CliGateway::class);
        $this->app->singleton(TelegramGateway::class);
        $this->app->singleton(DiscordGateway::class);

        // Tag gateways
        $this->app->tag([
            CliGateway::class,
            TelegramGateway::class,
            DiscordGateway::class,
        ], 'laraclaw.gateways');

        // Register main Laraclaw service
        $this->app->singleton('laraclaw', function ($app) {
            return new Laraclaw(
                $app->make(MemoryManager::class),
                $app->make(CoreAgent::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
