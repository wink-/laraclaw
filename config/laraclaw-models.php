<?php

return [

    'openai' => [
        'gpt-4o-mini' => ['name' => 'GPT-4o Mini', 'context' => 128000],
        'gpt-4o' => ['name' => 'GPT-4o', 'context' => 128000],
        'gpt-4.1-mini' => ['name' => 'GPT-4.1 Mini', 'context' => 1047576],
        'gpt-4.1' => ['name' => 'GPT-4.1', 'context' => 1047576],
        'gpt-4.1-nano' => ['name' => 'GPT-4.1 Nano', 'context' => 1047576],
        'o3-mini' => ['name' => 'o3 Mini', 'context' => 200000],
        'o4-mini' => ['name' => 'o4 Mini', 'context' => 200000],
    ],

    'anthropic' => [
        'claude-sonnet-4-20250514' => ['name' => 'Claude Sonnet 4', 'context' => 200000],
        'claude-opus-4-20250514' => ['name' => 'Claude Opus 4', 'context' => 200000],
        'claude-3-5-haiku-20241022' => ['name' => 'Claude 3.5 Haiku', 'context' => 200000],
        'claude-3-7-sonnet-20250219' => ['name' => 'Claude 3.7 Sonnet', 'context' => 200000],
    ],

    'gemini' => [
        'gemini-2.5-flash-preview-05-20' => ['name' => 'Gemini 2.5 Flash', 'context' => 1048576],
        'gemini-2.5-pro-preview-05-06' => ['name' => 'Gemini 2.5 Pro', 'context' => 1048576],
        'gemini-2.0-flash' => ['name' => 'Gemini 2.0 Flash', 'context' => 1048576],
        'gemini-2.0-flash-lite' => ['name' => 'Gemini 2.0 Flash Lite', 'context' => 1048576],
    ],

    'groq' => [
        'llama-3.3-70b-versatile' => ['name' => 'Llama 3.3 70B', 'context' => 128000],
        'llama-3.1-8b-instant' => ['name' => 'Llama 3.1 8B', 'context' => 128000],
        'mixtral-8x7b-32768' => ['name' => 'Mixtral 8x7B', 'context' => 32768],
        'gemma2-9b-it' => ['name' => 'Gemma 2 9B', 'context' => 8192],
    ],

    'nvidia' => [
        'moonshotai/kimi-k2-instruct' => ['name' => 'Kimi K2 Instruct', 'context' => 131072],
        'stepfun-ai/step-3.5-flash' => ['name' => 'Step 3.5 Flash', 'context' => 131072],
        'nvidia/nemotron-3-super-120b-a12b' => ['name' => 'Nemotron 3 Super 120B', 'context' => 1048576],
        'qwen/qwen3.5-122b-a10b' => ['name' => 'Qwen 3.5 122B', 'context' => 131072],
        'google/gemma-4-31b-it' => ['name' => 'Gemma 4 31B', 'context' => 131072],
        'mistralai/mistral-small-4-119b-2603' => ['name' => 'Mistral Small 4 119B', 'context' => 256000],
    ],

    'mistral' => [
        'mistral-large-latest' => ['name' => 'Mistral Large', 'context' => 128000],
        'mistral-medium-latest' => ['name' => 'Mistral Medium', 'context' => 32000],
        'mistral-small-latest' => ['name' => 'Mistral Small', 'context' => 32000],
        'codestral-latest' => ['name' => 'Codestral', 'context' => 256000],
    ],

    'deepseek' => [
        'deepseek-chat' => ['name' => 'DeepSeek V3', 'context' => 128000],
        'deepseek-reasoner' => ['name' => 'DeepSeek R1', 'context' => 128000],
    ],

    'xai' => [
        'grok-3' => ['name' => 'Grok 3', 'context' => 131072],
        'grok-3-mini' => ['name' => 'Grok 3 Mini', 'context' => 131072],
        'grok-2' => ['name' => 'Grok 2', 'context' => 131072],
    ],

    'ollama' => [
        'llama3.1' => ['name' => 'Llama 3.1', 'context' => 128000],
        'mistral' => ['name' => 'Mistral', 'context' => 32000],
        'codellama' => ['name' => 'Code Llama', 'context' => 16000],
        'phi3' => ['name' => 'Phi-3', 'context' => 128000],
        'gemma2' => ['name' => 'Gemma 2', 'context' => 8000],
        'qwen2.5' => ['name' => 'Qwen 2.5', 'context' => 131072],
    ],

    'openrouter' => [
        'openai/gpt-4o' => ['name' => 'GPT-4o', 'context' => 128000],
        'openai/gpt-4o-mini' => ['name' => 'GPT-4o Mini', 'context' => 128000],
        'openai/gpt-4.1-mini' => ['name' => 'GPT-4.1 Mini', 'context' => 1047576],
        'openai/gpt-4.1' => ['name' => 'GPT-4.1', 'context' => 1047576],
        'openai/o3-mini' => ['name' => 'o3 Mini', 'context' => 200000],
        'openai/o4-mini' => ['name' => 'o4 Mini', 'context' => 200000],
        'anthropic/claude-sonnet-4-20250514' => ['name' => 'Claude Sonnet 4', 'context' => 200000],
        'anthropic/claude-opus-4-20250514' => ['name' => 'Claude Opus 4', 'context' => 200000],
        'anthropic/claude-3.5-haiku' => ['name' => 'Claude 3.5 Haiku', 'context' => 200000],
        'google/gemini-2.5-flash-preview' => ['name' => 'Gemini 2.5 Flash', 'context' => 1048576],
        'google/gemini-2.5-pro-preview' => ['name' => 'Gemini 2.5 Pro', 'context' => 1048576],
        'meta-llama/llama-3.3-70b-instruct' => ['name' => 'Llama 3.3 70B', 'context' => 128000],
        'meta-llama/llama-4-maverick' => ['name' => 'Llama 4 Maverick', 'context' => 1048576],
        'meta-llama/llama-4-scout' => ['name' => 'Llama 4 Scout', 'context' => 10485760],
        'deepseek/deepseek-chat' => ['name' => 'DeepSeek V3', 'context' => 128000],
        'deepseek/deepseek-r1' => ['name' => 'DeepSeek R1', 'context' => 128000],
        'mistralai/mistral-large' => ['name' => 'Mistral Large', 'context' => 128000],
        'x-ai/grok-3' => ['name' => 'Grok 3', 'context' => 131072],
        'x-ai/grok-3-mini' => ['name' => 'Grok 3 Mini', 'context' => 131072],
        'qwen/qwen3-235b-a22b' => ['name' => 'Qwen3 235B', 'context' => 131072],
        'qwen/qwen3-32b' => ['name' => 'Qwen3 32B', 'context' => 131072],
    ],

    'zai' => [
        'glm-5.1' => ['name' => 'GLM-5.1', 'context' => 204800],
        'glm-5' => ['name' => 'GLM-5', 'context' => 204800],
        'glm-4.7' => ['name' => 'GLM-4.7', 'context' => 204800],
        'glm-4.6' => ['name' => 'GLM-4.6', 'context' => 128000],
        'glm-4.6v' => ['name' => 'GLM-4.6V (Vision)', 'context' => 128000],
        'glm-4.5-air' => ['name' => 'GLM-4.5 Air', 'context' => 128000],
        'glm-5-turbo' => ['name' => 'GLM-5-TURBO', 'context' => 200000],
    ],

    'zai-anthropic' => [
        'claude-sonnet-4-20250514' => ['name' => 'Claude Sonnet 4', 'context' => 200000],
        'claude-3-5-haiku-20241022' => ['name' => 'Claude 3.5 Haiku', 'context' => 200000],
    ],
];
