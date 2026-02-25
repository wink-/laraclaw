<?php

namespace App\Laraclaw\Agents;

use App\Laraclaw\Skills\Contracts\SkillInterface;
use Illuminate\Support\Collection;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxTokens(4096)]
#[Temperature(0.7)]
class CoreAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    protected Lab $provider;

    protected string $model;

    protected ?string $instructionOverride = null;

    /**
     * @param  Collection<int, SkillInterface>  $skills
     */
    public function __construct(
        protected Collection $skills = new Collection,
        protected array $conversationHistory = [],
        protected ?string $memoryContext = null,
    ) {
        // Set provider and model from config
        $this->configureProvider();
    }

    /**
     * Configure the AI provider from config.
     */
    protected function configureProvider(): void
    {
        $provider = config('laraclaw.ai.provider', 'openai');
        $model = config('laraclaw.ai.model', 'gpt-4o-mini');

        // Map config provider to Lab enum
        $labProvider = match ($provider) {
            'openai' => Lab::OpenAI,
            'anthropic' => Lab::Anthropic,
            'gemini' => Lab::Gemini,
            'ollama' => Lab::Ollama,
            'groq' => Lab::Groq,
            'mistral' => Lab::Mistral,
            'deepseek' => Lab::DeepSeek,
            'xai' => Lab::xAI,
            'openrouter' => Lab::OpenRouter,
            default => Lab::OpenAI,
        };

        // Set via attributes using reflection or just store for prompt usage
        $this->provider = $labProvider;
        $this->model = $model;
    }

    /**
     * Get the configured provider. (Used by Promptable trait)
     */
    public function provider(): Lab
    {
        return $this->provider ?? Lab::OpenAI;
    }

    /**
     * Get the configured model. (Used by Promptable trait)
     */
    public function model(): string
    {
        return $this->model ?? 'gpt-4o-mini';
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        if ($this->instructionOverride) {
            $override = $this->instructionOverride;

            if ($this->memoryContext) {
                return $override."\n\n".$this->memoryContext;
            }

            return $override;
        }

        $baseInstructions = <<<'PROMPT'
You are Laraclaw, a helpful AI assistant powered by Laravel.

You can help users with a variety of tasks using your available tools. When a user asks you to do something that requires a tool, use it. Always be helpful, friendly, and concise in your responses.

If you need to remember something important about the user, you can use the memory tools to store it for later.
PROMPT;

        if ($this->memoryContext) {
            return $baseInstructions."\n\n".$this->memoryContext;
        }

        return $baseInstructions;
    }

    /**
     * Get the list of messages comprising the conversation so far.
     */
    public function messages(): iterable
    {
        return $this->conversationHistory;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return $this->skills
            ->map(fn (SkillInterface $skill) => $skill->toTool())
            ->filter()
            ->all();
    }

    /**
     * Set the conversation history.
     */
    public function setConversationHistory(array $messages): self
    {
        $this->conversationHistory = $messages;

        return $this;
    }

    /**
     * Set the memory context.
     */
    public function setMemoryContext(string $context): self
    {
        $this->memoryContext = $context;

        return $this;
    }

    /**
     * Set specialist instruction override.
     */
    public function setInstructionOverride(?string $instructions): self
    {
        $this->instructionOverride = $instructions;

        return $this;
    }

    /**
     * Add a skill to the agent.
     */
    public function addSkill(SkillInterface $skill): self
    {
        $this->skills->push($skill);

        return $this;
    }

    /**
     * Set all skills.
     */
    public function setSkills(Collection $skills): self
    {
        $this->skills = $skills;

        return $this;
    }

    /**
     * Prompt the agent with context.
     */
    public function promptWithContext(
        string $message,
        array $history = [],
        ?string $memories = null,
        ?string $instructionOverride = null,
    ): string {
        $this->setConversationHistory($history);
        $this->setInstructionOverride($instructionOverride);

        if ($memories) {
            $this->setMemoryContext($memories);
        }

        return (string) $this->prompt($message);
    }
}
