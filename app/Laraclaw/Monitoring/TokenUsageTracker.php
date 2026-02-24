<?php

namespace App\Laraclaw\Monitoring;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\TokenUsage;

class TokenUsageTracker
{
    public function record(Conversation $conversation, ?Message $message, string $prompt, string $completion, array $metadata = []): TokenUsage
    {
        $provider = (string) config('laraclaw.ai.provider', 'openai');
        $model = (string) config('laraclaw.ai.model', 'gpt-4o-mini');

        $promptTokens = $this->estimateTokens($prompt);
        $completionTokens = $this->estimateTokens($completion);
        $totalTokens = $promptTokens + $completionTokens;

        $pricing = (array) config("laraclaw.token_usage.pricing.{$provider}", []);
        $inputPerMillion = (float) ($pricing['input_per_million'] ?? 0.0);
        $outputPerMillion = (float) ($pricing['output_per_million'] ?? 0.0);

        $cost = (($promptTokens / 1_000_000) * $inputPerMillion) + (($completionTokens / 1_000_000) * $outputPerMillion);

        return TokenUsage::query()->create([
            'conversation_id' => $conversation->id,
            'message_id' => $message?->id,
            'provider' => $provider,
            'model' => $model,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
            'cost_usd' => $cost,
            'metadata' => $metadata,
        ]);
    }

    public function estimateTokens(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        return (int) max(1, ceil(mb_strlen($text) / 4));
    }
}
