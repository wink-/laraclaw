<?php

namespace App\Console\Commands;

use App\Laraclaw\Facades\Laraclaw;
use App\Laraclaw\Gateways\CliGateway;
use App\Models\Conversation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\text;

class LaraclawChatCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laraclaw:chat
                            {--new : Start a new conversation}
                            {--id= : Resume a specific conversation by ID}
                            {message? : Send a single message and exit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Chat with Laraclaw AI assistant';

    protected CliGateway $gateway;

    protected ?Conversation $conversation = null;

    /**
     * Execute the console command.
     */
    public function handle(CliGateway $gateway): int
    {
        $this->gateway = $gateway;

        // Set up the gateway session
        if ($this->option('id')) {
            $this->conversation = Conversation::find($this->option('id'));
            if (! $this->conversation) {
                $this->error("Conversation ID {$this->option('id')} not found.");

                return self::FAILURE;
            }
            $this->gateway->setSessionId($this->conversation->gateway_conversation_id);
        } elseif (! $this->option('new')) {
            // Try to find an existing CLI conversation from today
            $this->conversation = Conversation::query()
                ->where('gateway', 'cli')
                ->whereDate('created_at', today())
                ->latest()
                ->first();
        }

        // Single message mode
        if ($message = $this->argument('message')) {
            return $this->sendSingleMessage($message);
        }

        // Interactive mode
        return $this->startInteractiveChat();
    }

    /**
     * Send a single message and exit.
     */
    protected function sendSingleMessage(string $message): int
    {
        if (! $this->conversation) {
            $this->conversation = Laraclaw::startConversation(gateway: 'cli');
        }

        $response = Laraclaw::chat($this->conversation, $message);
        $this->line($response);

        return self::SUCCESS;
    }

    /**
     * Start an interactive chat session.
     */
    protected function startInteractiveChat(): int
    {
        intro('🦁 Welcome to Laraclaw - Your Laravel AI Assistant');

        if ($this->conversation) {
            info("Resuming conversation #{$this->conversation->id}");
            $messageCount = $this->conversation->messages()->count();
            if ($messageCount > 0) {
                info("Previous messages: {$messageCount}");
            }
        } else {
            $this->conversation = Laraclaw::startConversation(gateway: 'cli');
            info("Started new conversation #{$this->conversation->id}");
        }

        $this->newLine();
        $this->displayHelp();

        while (true) {
            $input = text(
                label: 'You',
                placeholder: 'Type your message (or /help for commands)...',
                required: true
            );

            // Handle commands
            if (str_starts_with($input, '/')) {
                if ($this->handleCommand($input)) {
                    continue;
                }

                break; // /exit or /quit
            }

            // Send message and get response
            $this->newLine();
            $this->line('<info>Laraclaw:</info> Thinking...');

            try {
                $response = Laraclaw::chat($this->conversation, $input);
                $this->newLine();
                $this->line("<info>Laraclaw:</info> {$response}");
                $this->newLine();
            } catch (\Throwable $e) {
                $this->error("Error: {$e->getMessage()}");

                if (App::environment('local')) {
                    $this->error($e->getTraceAsString());
                }
            }
        }

        info('Goodbye! 👋');

        return self::SUCCESS;
    }

    /**
     * Handle slash commands.
     *
     * @return bool True to continue, false to exit
     */
    protected function handleCommand(string $command): bool
    {
        $command = strtolower(trim($command));

        return match ($command) {
            '/help', '/h', '/?' => $this->displayHelp(),
            '/new' => $this->startNewConversation(),
            '/history' => $this->showHistory(),
            '/clear' => $this->clearScreen(),
            '/exit', '/quit', '/q' => false,
            default => $this->unknownCommand($command),
        };
    }

    /**
     * Display help information.
     */
    protected function displayHelp(): bool
    {
        $this->newLine();
        $this->line('<comment>Commands:</comment>');
        $this->line('  <info>/help</info>     Show this help message');
        $this->line('  <info>/new</info>      Start a new conversation');
        $this->line('  <info>/history</info>  Show conversation history');
        $this->line('  <info>/clear</info>    Clear the screen');
        $this->line('  <info>/exit</info>     Exit the chat');
        $this->newLine();

        return true;
    }

    /**
     * Start a new conversation.
     */
    protected function startNewConversation(): bool
    {
        $this->gateway->startNewSession();
        $this->conversation = Laraclaw::startConversation(gateway: 'cli');
        $this->newLine();
        info("Started new conversation #{$this->conversation->id}");
        $this->newLine();

        return true;
    }

    /**
     * Show conversation history.
     */
    protected function showHistory(): bool
    {
        $messages = $this->conversation->messages()
            ->orderBy('created_at')
            ->get();

        if ($messages->isEmpty()) {
            $this->line('No messages in this conversation yet.');
            $this->newLine();

            return true;
        }

        $this->newLine();
        $this->line('<comment>Conversation History:</comment>');
        $this->newLine();

        foreach ($messages as $message) {
            $role = ucfirst($message->role);
            $color = match ($message->role) {
                'user' => 'info',
                'assistant' => 'comment',
                default => 'fg=white',
            };
            $this->line("<{$color}>{$role}:</{$color}> {$message->content}");
        }

        $this->newLine();

        return true;
    }

    /**
     * Clear the terminal screen.
     */
    protected function clearScreen(): bool
    {
        $this->output->write("\033[2J\033[;H");

        return true;
    }

    /**
     * Handle unknown commands.
     */
    protected function unknownCommand(string $command): bool
    {
        $this->line("<error>Unknown command: {$command}</error>");
        $this->line('Type <info>/help</info> for available commands.');
        $this->newLine();

        return true;
    }
}
