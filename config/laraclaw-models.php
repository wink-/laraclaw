<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Model Catalog
    |--------------------------------------------------------------------------
    |
    | Curated list of models per provider. Each model entry has:
    |   - name: Human-readable label for UI display
    |   - context: Context window in tokens
    |
    | Add or remove models here — the chat settings dropdown and the
    | laraclaw:test-model command will pick them up automatically.
    |
    */

    'openai' => [
        'gpt-4o-mini' => ['name' => 'GPT-4o Mini', 'context' => 128_000],
        'gpt-4o' => ['name' => 'GPT-4o', 'context' => 128_000],
        'gpt-4.1-mini' => ['name' => 'GPT-4.1 Mini', 'context' => 1_047_576],
        'gpt-4.1' => ['name' => 'GPT-4.1', 'context' => 1_047_576],
        'gpt-4.1-nano' => ['name' => 'GPT-4.1 Nano', 'context' => 1_047_576],
        'o3-mini' => ['name' => 'o3 Mini', 'context' => 200_000],
        'o4-mini' => ['name' => 'o4 Mini', 'context' => 200_000],
    ],

    'anthropic' => [
        'claude-sonnet-4-20250514' => ['name' => 'Claude Sonnet 4', 'context' => 200_000],
        'claude-opus-4-20250514' => ['name' => 'Claude Opus 4', 'context' => 200_000],
        'claude-3-5-haiku-20241022' => ['name' => 'Claude 3.5 Haiku', 'context' => 200_000],
        'claude-3-7-sonnet-20250219' => ['name' => 'Claude 3.7 Sonnet', 'context' => 200_000],
    ],

    'gemini' => [
        'gemini-2.5-flash-preview-05-20' => ['name' => 'Gemini 2.5 Flash', 'context' => 1_048_576],
        'gemini-2.5-pro-preview-05-06' => ['name' => 'Gemini 2.5 Pro', 'context' => 1_048_576],
        'gemini-2.0-flash' => ['name' => 'Gemini 2.0 Flash', 'context' => 1_048_576],
        'gemini-2.0-flash-lite' => ['name' => 'Gemini 2.0 Flash Lite', 'context' => 1_048_576],
    ],

    'groq' => [
        'llama-3.3-70b-versatile' => ['name' => 'Llama 3.3 70B', 'context' => 128_000],
        'llama-3.1-8b-instant' => ['name' => 'Llama 3.1 8B', 'context' => 128_000],
        'mixtral-8x7b-32768' => ['name' => 'Mixtral 8x7B', 'context' => 32_768],
        'gemma2-9b-it' => ['name' => 'Gemma 2 9B', 'context' => 8_192],
    ],

    'mistral' => [
        'mistral-large-latest' => ['name' => 'Mistral Large', 'context' => 128_000],
        'mistral-medium-latest' => ['name' => 'Mistral Medium', 'context' => 32_000],
        'mistral-small-latest' => ['name' => 'Mistral Small', 'context' => 32_000],
        'codestral-latest' => ['name' => 'Codestral', 'context' => 256_000],
    ],

    'deepseek' => [
        'deepseek-chat' => ['name' => 'DeepSeek V3', 'context' => 128_000],
        'deepseek-reasoner' => ['name' => 'DeepSeek R1', 'context' => 128_000],
    ],

    'xai' => [
        'grok-3' => ['name' => 'Grok 3', 'context' => 131_072],
        'grok-3-mini' => ['name' => 'Grok 3 Mini', 'context' => 131_072],
        'grok-2' => ['name' => 'Grok 2', 'context' => 131_072],
    ],

    'ollama' => [
        'llama3.1' => ['name' => 'Llama 3.1', 'context' => 128_000],
        'mistral' => ['name' => 'Mistral', 'context' => 32_000],
        'codellama' => ['name' => 'Code Llama', 'context' => 16_000],
        'phi3' => ['name' => 'Phi-3', 'context' => 128_000],
        'gemma2' => ['name' => 'Gemma 2', 'context' => 8_000],
        'qwen2.5' => ['name' => 'Qwen 2.5', 'context' => 131_072],
    ],

    'openrouter' => [
        // OpenAI via OpenRouter
        'openai/gpt-4o' => ['name' => 'GPT-4o', 'context' => 128_000],
        'openai/gpt-4o-mini' => ['name' => 'GPT-4o Mini', 'context' => 128_000],
        'openai/gpt-4.1-mini' => ['name' => 'GPT-4.1 Mini', 'context' => 1_047_576],
        'openai/gpt-4.1' => ['name' => 'GPT-4.1', 'context' => 1_047_576],
        'openai/o3-mini' => ['name' => 'o3 Mini', 'context' => 200_000],
        'openai/o4-mini' => ['name' => 'o4 Mini', 'context' => 200_000],
        // Anthropic via OpenRouter
        'anthropic/claude-sonnet-4-20250514' => ['name' => 'Claude Sonnet 4', 'context' => 200_000],
        'anthropic/claude-opus-4-20250514' => ['name' => 'Claude Opus 4', 'context' => 200_000],
        'anthropic/claude-3.5-haiku' => ['name' => 'Claude 3.5 Haiku', 'context' => 200_000],
        // Google via OpenRouter
        'google/gemini-2.5-flash-preview' => ['name' => 'Gemini 2.5 Flash', 'context' => 1_048_576],
        'google/gemini-2.5-pro-preview' => ['name' => 'Gemini 2.5 Pro', 'context' => 1_048_576],
        // Meta via OpenRouter
        'meta-llama/llama-3.3-70b-instruct' => ['name' => 'Llama 3.3 70B', 'context' => 128_000],
        'meta-llama/llama-4-maverick' => ['name' => 'Llama 4 Maverick', 'context' => 1_048_576],
        'meta-llama/llama-4-scout' => ['name' => 'Llama 4 Scout', 'context' => 10_485_760],
        // DeepSeek via OpenRouter
        'deepseek/deepseek-chat' => ['name' => 'DeepSeek V3', 'context' => 128_000],
        'deepseek/deepseek-r1' => ['name' => 'DeepSeek R1', 'context' => 128_000],
        // Mistral via OpenRouter
        'mistralai/mistral-large' => ['name' => 'Mistral Large', 'context' => 128_000],
        // xAI via OpenRouter
        'x-ai/grok-3' => ['name' => 'Grok 3', 'context' => 131_072],
        'x-ai/grok-3-mini' => ['name' => 'Grok 3 Mini', 'context' => 131_072],
        // Qwen via OpenRouter
        'qwen/qwen3-235b-a22b' => ['name' => 'Qwen3 235B', 'context' => 131_072],
        'qwen/qwen3-32b' => ['name' => 'Qwen3 32B', 'context' => 131_072],
    ],

    'zai' => [
        'glm-4-flash' => ['name' => 'GLM-4 Flash', 'context' => 128_000],
        'glm-4-plus' => ['name' => 'GLM-4 Plus', 'context' => 128_000],
        'glm-4-air' => ['name' => 'GLM-4 Air', 'context' => 128_000],
        'glm-4-long' => ['name' => 'GLM-4 Long', 'context' => 1_000_000],
        'glm-4v-flash' => ['name' => 'GLM-4V Flash (Vision)', 'context' => 8_000],
    ],

    'zai-anthropic' => [
        'claude-sonnet-4-20250514' => ['name' => 'Claude Sonnet 4', 'context' => 200_000],
        'claude-3-5-haiku-20241022' => ['name' => 'Claude 3.5 Haiku', 'context' => 200_000],
    ],

];
