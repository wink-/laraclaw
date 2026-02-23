<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class LaraclawInstallCommand extends Command
{
    protected $signature = 'laraclaw:install
                            {--skip-env : Skip .env configuration}
                            {--skip-migrations : Skip running migrations}';

    protected $description = 'Install and configure Laraclaw AI assistant';

    public function handle(): int
    {
        info('🦀 Laraclaw Installation');
        info('========================');
        $this->newLine();

        // Step 1: Check prerequisites
        $this->checkPrerequisites();

        // Step 2: Configure environment
        if (! $this->option('skip-env')) {
            $this->configureEnvironment();
        }

        // Step 3: Run migrations
        if (! $this->option('skip-migrations')) {
            $this->runMigrations();
        }

        // Step 4: Create identity files
        $this->createIdentityFiles();

        // Step 5: Display next steps
        $this->displayNextSteps();

        return self::SUCCESS;
    }

    protected function checkPrerequisites(): void
    {
        info('Checking prerequisites...');

        // Check PHP version
        $phpVersion = PHP_VERSION;
        if (version_compare($phpVersion, '8.2', '>=')) {
            info("✓ PHP {$phpVersion}");
        } else {
            warning("⚠ PHP {$phpVersion} - recommend 8.2+");
        }

        // Check SQLite extension
        if (extension_loaded('pdo_sqlite')) {
            info('✓ pdo_sqlite extension loaded');
        } else {
            warning('⚠ pdo_sqlite extension not loaded - SQLite required');
        }

        // Check cURL for API calls
        if (extension_loaded('curl')) {
            info('✓ curl extension loaded');
        } else {
            warning('⚠ curl extension not loaded - required for LLM APIs');
        }

        $this->newLine();
    }

    protected function configureEnvironment(): void
    {
        info('Configuring environment...');

        $envPath = base_path('.env');
        $envContent = File::exists($envPath) ? File::get($envPath) : '';

        // Ask for AI provider
        $configureAi = confirm('Configure AI provider now?', true);
        if ($configureAi) {
            $this->configureAiProvider($envContent);
        }

        // Ask for Telegram
        $configureTelegram = confirm('Configure Telegram bot?', false);
        if ($configureTelegram) {
            $this->configureTelegram($envContent);
        }

        // Ask for Discord
        $configureDiscord = confirm('Configure Discord bot?', false);
        if ($configureDiscord) {
            $this->configureDiscord($envContent);
        }

        $this->newLine();
    }

    protected function configureAiProvider(string &$envContent): void
    {
        $provider = text(
            label: 'AI Provider (openai, anthropic, ollama)',
            default: 'openai',
            required: true
        );

        $apiKey = text(
            label: 'API Key (leave empty for Ollama)',
            default: '',
        );

        if ($provider === 'anthropic') {
            $this->setEnvVar($envContent, 'ANTHROPIC_API_KEY', $apiKey);
            $this->setEnvVar($envContent, 'AI_PROVIDER', 'anthropic');
        } elseif ($provider === 'ollama') {
            $this->setEnvVar($envContent, 'AI_PROVIDER', 'ollama');
            $this->setEnvVar($envContent, 'OLLAMA_HOST', 'http://localhost:11434');
        } else {
            $this->setEnvVar($envContent, 'OPENAI_API_KEY', $apiKey);
            $this->setEnvVar($envContent, 'AI_PROVIDER', 'openai');
        }

        File::put(base_path('.env'), $envContent);
        info('✓ AI provider configured');
    }

    protected function configureTelegram(string &$envContent): void
    {
        $token = text(
            label: 'Telegram Bot Token',
            placeholder: '123456789:ABCdefGHIjklMNOpqrsTUVwxyz',
        );

        $secret = text(
            label: 'Webhook Secret Token (optional, for security)',
            default: bin2hex(random_bytes(16)),
        );

        $this->setEnvVar($envContent, 'TELEGRAM_BOT_TOKEN', $token);
        $this->setEnvVar($envContent, 'TELEGRAM_SECRET_TOKEN', $secret);

        File::put(base_path('.env'), $envContent);
        info('✓ Telegram configured');
    }

    protected function configureDiscord(string &$envContent): void
    {
        $token = text(
            label: 'Discord Bot Token',
            placeholder: 'your-discord-bot-token',
        );

        $appId = text(
            label: 'Discord Application ID',
            placeholder: '123456789012345678',
        );

        $this->setEnvVar($envContent, 'DISCORD_BOT_TOKEN', $token);
        $this->setEnvVar($envContent, 'DISCORD_APPLICATION_ID', $appId);

        File::put(base_path('.env'), $envContent);
        info('✓ Discord configured');
    }

    protected function setEnvVar(string &$content, string $key, string $value): void
    {
        $pattern = "/^{$key}=.*$/m";
        $replacement = "{$key}={$value}";

        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $replacement, $content);
        } else {
            $content .= "\n{$replacement}";
        }
    }

    protected function runMigrations(): void
    {
        info('Running database migrations...');

        $this->call('migrate', [
            '--force' => true,
        ]);

        info('✓ Migrations complete');
        $this->newLine();
    }

    protected function createIdentityFiles(): void
    {
        info('Creating identity files...');

        // Create IDENTITY.md
        $identityPath = storage_path('laraclaw/IDENTITY.md');
        if (! File::exists($identityPath)) {
            File::ensureDirectoryExists(dirname($identityPath));
            File::put($identityPath, $this->getIdentityTemplate());
            info('✓ Created IDENTITY.md');
        } else {
            info('✓ IDENTITY.md already exists');
        }

        // Create SOUL.md (optional, for personality)
        $soulPath = storage_path('laraclaw/SOUL.md');
        if (! File::exists($soulPath)) {
            File::put($soulPath, $this->getSoulTemplate());
            info('✓ Created SOUL.md');
        } else {
            info('✓ SOUL.md already exists');
        }

        $this->newLine();
    }

    protected function getIdentityTemplate(): string
    {
        return <<<'MD'
        # Laraclaw Identity

        This file defines who Laraclaw is and how it should behave.

        ## Core Identity

        You are Laraclaw, a personal AI assistant built on Laravel. You are helpful,
        concise, and capable of helping with various tasks through your skills.

        ## Capabilities

        - Remember information about the user across conversations
        - Search the web for current information
        - Perform calculations
        - Tell the current time and date

        ## Behavior

        - Be helpful but not overly verbose
        - Use available skills when they would help the user
        - Remember important information the user shares
        - Ask clarifying questions when needed

        ## Limitations

        - Only use skills that are available
        - Don't make up information
        - Be honest about what you can and cannot do
        MD;
    }

    protected function getSoulTemplate(): string
    {
        return <<<'MD'
        # Laraclaw Soul

        This file defines the personality and deeper characteristics of Laraclaw.

        ## Personality Traits

        - Curious and eager to help
        - Patient with complex questions
        - Light sense of humor when appropriate
        - Respectful of user preferences

        ## Communication Style

        - Clear and direct
        - Adapts to user's communication style
        - Uses markdown formatting for clarity
        - Provides examples when helpful

        ## Values

        - Privacy: User data stays local
        - Transparency: Clear about limitations
        - Helpfulness: Goes the extra mile
        - Reliability: Consistent behavior

        <!-- Customize this file to give Laraclaw your preferred personality -->
        MD;
    }

    protected function displayNextSteps(): void
    {
        info('🎉 Installation Complete!');
        $this->newLine();

        $this->line('Next steps:');
        $this->line('  1. Configure your AI provider in .env if not done');
        $this->line('  2. Run <comment>php artisan laraclaw:chat</comment> to test');
        $this->line('  3. Customize identity files in <comment>storage/laraclaw/</comment>');
        $this->newLine();

        $this->line('Available commands:');
        $this->line('  <comment>php artisan laraclaw:chat</comment>    - Start interactive chat');
        $this->line('  <comment>php artisan laraclaw:doctor</comment>  - Run diagnostics');
        $this->line('  <comment>php artisan laraclaw:status</comment>  - Check system status');
        $this->newLine();

        $this->line('Gateway setup:');
        $this->line('  Set webhook URLs in your bot platforms:');
        $this->line('  Telegram: <info>'.config('app.url').'/laraclaw/webhooks/telegram</info>');
        $this->line('  Discord:  <info>'.config('app.url').'/laraclaw/webhooks/discord</info>');
    }
}
