<?php

namespace App\Laraclaw\Agents;

class IntentRouter
{
    /**
     * @return array{intent: string, specialist_prompt: ?string}
     */
    public function route(string $message): array
    {
        $normalized = mb_strtolower(trim($message));

        foreach ($this->intentPatterns() as $intent => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($normalized, $pattern)) {
                    return [
                        'intent' => $intent,
                        'specialist_prompt' => config("laraclaw.intent_routing.prompts.{$intent}"),
                    ];
                }
            }
        }

        return [
            'intent' => 'general',
            'specialist_prompt' => config('laraclaw.intent_routing.prompts.general'),
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function intentPatterns(): array
    {
        return [
            'builder' => [
                'build me',
                'create app',
                'make a blog',
                'scaffold',
            ],
            'shopping' => [
                'shopping list',
                'grocery',
                'buy ',
            ],
            'scheduling' => [
                'schedule',
                'remind me',
                'calendar',
                'due ',
            ],
            'entertainment' => [
                'what should i watch',
                'shows should i watch',
                'show to watch',
                'shows to watch',
                'show ',
                'shows ',
                'movie',
                'series',
                'watchlist',
            ],
            'memory' => [
                'remember ',
                'recall ',
                'what did i say',
            ],
        ];
    }
}
