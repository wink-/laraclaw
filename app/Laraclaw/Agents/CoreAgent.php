<?php

namespace App\Laraclaw\Agents;

use App\Laraclaw\AI\ProviderCatalog;
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
    protected function configureProvider(?string $agentKey = null): void
    {
        $provider = config('laraclaw.ai.provider', 'openai');
        $model = config('laraclaw.ai.model', 'gpt-4o-mini');

        if ($agentKey !== null) {
            $agentProvider = config("laraclaw.ai.agents.{$agentKey}.provider");
            $agentModel = config("laraclaw.ai.agents.{$agentKey}.model");

            if (is_string($agentProvider) && $agentProvider !== '') {
                $provider = $agentProvider;
            }

            if (is_string($agentModel) && $agentModel !== '') {
                $model = $agentModel;
            }
        }

        // Resolve provider to Lab enum and apply config overrides
        $this->provider = $this->resolveProviderToLab($provider);
        $this->applyProviderConfigSwap($provider);
        $this->model = $model;
    }

    /**
     * Apply a per-request provider override (e.g. from chat settings).
     */
    public function applyProviderOverride(string $provider): void
    {
        $this->provider = $this->resolveProviderToLab($provider);
        $this->applyProviderConfigSwap($provider);
    }

    /**
     * Apply a per-request model override (e.g. from chat settings).
     */
    public function applyModelOverride(string $model): void
    {
        $this->model = $model;
    }

    /**
     * Resolve a provider string to its Lab enum value.
     * Checks built-in providers first, then custom providers via ProviderCatalog.
     */
    private function resolveProviderToLab(string $provider): Lab
    {
        return match ($provider) {
            'openai' => Lab::OpenAI,
            'anthropic' => Lab::Anthropic,
            'gemini' => Lab::Gemini,
            'ollama' => Lab::Ollama,
            'groq' => Lab::Groq,
            'mistral' => Lab::Mistral,
            'deepseek' => Lab::DeepSeek,
            'xai' => Lab::xAI,
            'openrouter' => Lab::OpenRouter,
            'cohere' => Lab::Cohere,
            'azure' => Lab::Azure,
            'nvidia' => Lab::Groq,
            'zai' => Lab::OpenAI,
            'zai-anthropic' => Lab::Anthropic,
            default => app(ProviderCatalog::class)->resolveLabForProvider($provider) ?? Lab::OpenAI,
        };
    }

    /**
     * Swap the target provider's config when using a provider that
     * proxies through another driver (ZAI, custom providers, etc.).
     */
    private function applyProviderConfigSwap(string $provider): void
    {
        if ($provider === 'nvidia') {
            $nvidiaConfig = config('ai.providers.nvidia');
            if ($nvidiaConfig) {
                config(['ai.providers.groq' => $nvidiaConfig]);
            }

            return;
        }

        if (str_starts_with($provider, 'zai')) {
            $zaiConfig = config('ai.providers.'.$provider);
            if ($zaiConfig) {
                $targetProvider = $provider === 'zai-anthropic' ? 'anthropic' : 'openai';
                config(['ai.providers.'.$targetProvider => $zaiConfig]);
            }

            return;
        }

        if (! in_array($provider, ProviderCatalog::BUILT_IN_PROVIDERS)) {
            $customConfig = config('ai.providers.'.$provider);
            if ($customConfig && isset($customConfig['driver'])) {
                config(['ai.providers.'.$customConfig['driver'] => $customConfig]);
            }
        }
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

    Memory Tool Usage (IMPORTANT):
    - You MUST use the memory tool with action="remember" when the user says things like "remind me", "remember this", "don't forget", "save this", or asks to track something for later.
    - For plans and preferences (for example: "watch Stranger Things", "buy milk", "call mom tomorrow"), store them as memories instead of only acknowledging conversationally.
    - Use action="recall" when the user asks about previously discussed details.
    - Use concise memory content that is easy to retrieve later.
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
    public function setMemoryContext(?string $context): self
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
     * Set provider/model resolution key for this request.
     */
    public function setAgentKey(?string $agentKey): self
    {
        $this->configureProvider($agentKey);

        return $this;
    }

    /**
     * Configure agent for intent-based routing.
     *
     * Combines instruction override and provider/model configuration
     * into a single call when intent routing is enabled.
     */
    public function configureForIntent(?array $intent): self
    {
        $this->setInstructionOverride($intent['specialist_prompt'] ?? null);
        $this->configureProvider($intent['intent'] ?? null);

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
        ?string $agentKey = null,
    ): string {
        $this->configureProvider($agentKey);
        $this->setConversationHistory($history);
        $this->setInstructionOverride($instructionOverride);

        $this->setMemoryContext($memories);

        return (string) $this->prompt($message);
    }
}
