<?php

namespace App\Laraclaw\Skills;

use App\Laraclaw\Security\SecurityManager;
use App\Laraclaw\Skills\Contracts\SkillInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Process;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ExecuteSkill implements SkillInterface, Tool
{
    protected bool $canExecute;

    protected array $allowedCommands = [];

    protected array $blockedPatterns = [
        'rm -rf',
        'sudo',
        'chmod',
        'chown',
        'mkfs',
        'dd if=',
        '>:',
        'curl | bash',
        'wget |',
        '/etc/passwd',
        '/etc/shadow',
        'eval',
        'exec',
    ];

    protected int $timeout = 30;

    public function __construct(
        protected SecurityManager $security
    ) {
        $this->canExecute = $security->getAutonomyLevel()->canExecute();
        $this->allowedCommands = config('laraclaw.security.allowed_commands', []);
        $this->timeout = config('laraclaw.security.command_timeout', 30);
    }

    public function name(): string
    {
        return 'execute';
    }

    public function description(): Stringable|string
    {
        return 'Execute shell commands on the system. Use with caution - only safe commands are allowed.';
    }

    public function execute(array $parameters): string
    {
        if (! $this->canExecute) {
            return 'Error: Command execution is disabled. Autonomy level must be "full" to execute commands.';
        }

        $command = $parameters['command'] ?? '';

        if (empty($command)) {
            return 'Error: No command provided.';
        }

        // Security checks
        if (! $this->isCommandSafe($command)) {
            return 'Error: Command contains blocked patterns or is not allowed for security reasons.';
        }

        // Check against allowed commands if whitelist is configured
        if (! empty($this->allowedCommands) && ! $this->isCommandAllowed($command)) {
            return 'Error: Command is not in the allowed list.';
        }

        return $this->runCommand($command);
    }

    protected function isCommandSafe(string $command): bool
    {
        $lowerCommand = strtolower($command);

        foreach ($this->blockedPatterns as $pattern) {
            if (str_contains($lowerCommand, strtolower($pattern))) {
                return false;
            }
        }

        // Block shell injection attempts
        if (preg_match('/[;&|`$]/', $command)) {
            return false;
        }

        return true;
    }

    protected function isCommandAllowed(string $command): bool
    {
        $baseCommand = explode(' ', $command)[0];

        foreach ($this->allowedCommands as $allowed) {
            if ($baseCommand === $allowed || str_starts_with($command, $allowed.' ')) {
                return true;
            }
        }

        return false;
    }

    protected function runCommand(string $command): string
    {
        $startTime = microtime(true);

        try {
            $result = Process::timeout($this->timeout)->run($command);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $output = "Command: {$command}\n";
            $output .= "Duration: {$duration}ms\n";
            $output .= "Exit Code: {$result->exitCode()}\n\n";

            if ($result->successful()) {
                $output .= "Output:\n";

                $stdout = trim($result->output());
                if (! empty($stdout)) {
                    // Limit output size
                    if (strlen($stdout) > 5000) {
                        $output .= substr($stdout, 0, 5000)."\n... (output truncated)";
                    } else {
                        $output .= $stdout;
                    }
                } else {
                    $output .= '(no output)';
                }
            } else {
                $output .= "Error:\n";
                $stderr = trim($result->errorOutput());
                $output .= ! empty($stderr) ? $stderr : '(no error message)';
            }

            return $output;
        } catch (\Illuminate\Process\Exceptions\ProcessTimedOutException $e) {
            return "Command timed out after {$this->timeout} seconds.";
        } catch (\Throwable $e) {
            return 'Error executing command: '.$e->getMessage();
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'command' => $schema->string()
                ->description('The shell command to execute (must be safe and allowed)'),
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

    /**
     * Add an allowed command to the whitelist.
     */
    public function allowCommand(string $command): self
    {
        if (! in_array($command, $this->allowedCommands)) {
            $this->allowedCommands[] = $command;
        }

        return $this;
    }

    /**
     * Set the command timeout in seconds.
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }
}
