<?php

namespace App\Providers;

use App\Laraclaw\Agents\CoreAgent;
use App\Laraclaw\Agents\IntentRouter;
use App\Laraclaw\Agents\MultiAgentOrchestrator;
use App\Laraclaw\Channels\ChannelBindingManager;
use App\Laraclaw\Gateways\CliGateway;
use App\Laraclaw\Gateways\DiscordGateway;
use App\Laraclaw\Gateways\TelegramGateway;
use App\Laraclaw\Gateways\WhatsAppGateway;
use App\Laraclaw\Heartbeat\HeartbeatEngine;
use App\Laraclaw\Identity\IdentityManager;
use App\Laraclaw\Laraclaw;
use App\Laraclaw\Memory\MemoryManager;
use App\Laraclaw\Monitoring\TokenUsageTracker;
use App\Laraclaw\Security\SecurityManager;
use App\Laraclaw\Skills\AppBuilderSkill;
use App\Laraclaw\Skills\CalculatorSkill;
use App\Laraclaw\Skills\CalendarSkill;
use App\Laraclaw\Skills\EmailSkill;
use App\Laraclaw\Skills\ExecuteSkill;
use App\Laraclaw\Skills\FileSystemSkill;
use App\Laraclaw\Skills\MemorySkill;
use App\Laraclaw\Skills\PluginManager;
use App\Laraclaw\Skills\SchedulerSkill;
use App\Laraclaw\Skills\ShoppingListSkill;
use App\Laraclaw\Skills\TimeSkill;
use App\Laraclaw\Skills\WebSearchSkill;
use App\Laraclaw\Storage\FileStorageService;
use App\Laraclaw\Storage\VectorStoreService;
use App\Laraclaw\Tunnels\TailscaleNetworkManager;
use App\Laraclaw\Tunnels\TunnelManager;
use App\Laraclaw\Voice\VoiceService;
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

        // Register ChannelBindingManager as singleton
        $this->app->singleton(ChannelBindingManager::class);

        // Register TunnelManager as singleton
        $this->app->singleton(TunnelManager::class, function ($app) {
            return new TunnelManager(config('laraclaw.tunnels', []));
        });

        // Register TailscaleNetworkManager as singleton
        $this->app->singleton(TailscaleNetworkManager::class);

        // Register HeartbeatEngine as singleton
        $this->app->singleton(HeartbeatEngine::class);

        // Register VoiceService as singleton
        $this->app->singleton(VoiceService::class);

        // Register FileStorageService as singleton
        $this->app->singleton(FileStorageService::class);

        // Register VectorStoreService as singleton
        $this->app->singleton(VectorStoreService::class);

        // Register plugin manager as singleton
        $this->app->singleton(PluginManager::class);

        // Register token usage tracker as singleton
        $this->app->singleton(TokenUsageTracker::class);

        // Register skills as singletons
        $this->app->singleton(TimeSkill::class);
        $this->app->singleton(CalculatorSkill::class);
        $this->app->singleton(WebSearchSkill::class);
        $this->app->singleton(AppBuilderSkill::class);
        $this->app->singleton(MemorySkill::class);
        $this->app->singleton(FileSystemSkill::class);
        $this->app->singleton(ExecuteSkill::class);
        $this->app->singleton(EmailSkill::class);
        $this->app->singleton(CalendarSkill::class);
        $this->app->singleton(SchedulerSkill::class);
        $this->app->singleton(ShoppingListSkill::class);

        // Tag skills
        $this->app->tag([
            TimeSkill::class,
            CalculatorSkill::class,
            WebSearchSkill::class,
            AppBuilderSkill::class,
            MemorySkill::class,
            FileSystemSkill::class,
            ExecuteSkill::class,
            EmailSkill::class,
            CalendarSkill::class,
            SchedulerSkill::class,
            ShoppingListSkill::class,
        ], 'laraclaw.skills');

        // Register CoreAgent with skills
        $this->app->singleton(CoreAgent::class, function ($app) {
            $allSkills = collect($app->tagged('laraclaw.skills'));
            $enabledClasses = $app->make(PluginManager::class)
                ->enabledSkillClasses($allSkills->map(fn ($skill) => $skill::class)->all());

            $skills = $allSkills->filter(fn ($skill) => in_array($skill::class, $enabledClasses, true))->values();

            return new CoreAgent($skills);
        });

        // Register multi-agent orchestrator
        $this->app->singleton(MultiAgentOrchestrator::class);

        // Register intent router
        $this->app->singleton(IntentRouter::class);

        // Register Gateways
        $this->app->singleton(CliGateway::class);
        $this->app->singleton(TelegramGateway::class);
        $this->app->singleton(DiscordGateway::class);
        $this->app->singleton(WhatsAppGateway::class);

        // Tag gateways
        $this->app->tag([
            CliGateway::class,
            TelegramGateway::class,
            DiscordGateway::class,
            WhatsAppGateway::class,
        ], 'laraclaw.gateways');

        // Register main Laraclaw service
        $this->app->singleton('laraclaw', function ($app) {
            return new Laraclaw(
                $app->make(MemoryManager::class),
                $app->make(CoreAgent::class),
                $app->make(IntentRouter::class),
                $app->make(MultiAgentOrchestrator::class),
                $app->make(PluginManager::class),
                $app->make(TokenUsageTracker::class),
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
