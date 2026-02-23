<?php

namespace App\Laraclaw\Skills;

use App\Laraclaw\Skills\Contracts\SkillInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class WebSearchSkill implements SkillInterface, Tool
{
    public function name(): string
    {
        return 'web_search';
    }

    public function description(): Stringable|string
    {
        return 'Search the web for information. Returns a summary of search results for the given query.';
    }

    public function execute(array $parameters): string
    {
        $query = $parameters['query'] ?? '';
        $limit = $parameters['limit'] ?? 5;

        if (empty($query)) {
            return 'Error: No search query provided.';
        }

        try {
            // Use DuckDuckGo Instant Answer API (no API key required)
            $response = Http::get('https://api.duckduckgo.com/', [
                'q' => $query,
                'format' => 'json',
                'no_html' => 1,
                'skip_disambig' => 1,
            ]);

            $data = $response->json();

            $results = [];

            // Get the abstract if available
            if (! empty($data['Abstract'])) {
                $results[] = [
                    'source' => $data['AbstractSource'] ?? 'DuckDuckGo',
                    'title' => $data['Heading'] ?? 'Summary',
                    'content' => $data['Abstract'],
                    'url' => $data['AbstractURL'] ?? null,
                ];
            }

            // Get related topics
            if (! empty($data['RelatedTopics'])) {
                foreach (array_slice($data['RelatedTopics'], 0, $limit - count($results)) as $topic) {
                    if (isset($topic['Text']) && isset($topic['FirstURL'])) {
                        $results[] = [
                            'source' => 'DuckDuckGo',
                            'title' => $topic['Text'] ?? 'Related',
                            'content' => $topic['Text'],
                            'url' => $topic['FirstURL'],
                        ];
                    }
                }
            }

            if (empty($results)) {
                return "No results found for '{$query}'. Try a different search term.";
            }

            $output = "Search results for '{$query}':\n\n";

            foreach ($results as $i => $result) {
                $num = $i + 1;
                $output .= "{$num}. **{$result['title']}**\n";
                $output .= "   {$result['content']}\n";
                if ($result['url']) {
                    $output .= "   Source: {$result['url']}\n";
                }
                $output .= "\n";
            }

            return $output;
        } catch (\Throwable $e) {
            return "Error performing web search: {$e->getMessage()}";
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required()->description('The search query'),
            'limit' => $schema->integer()->min(1)->max(10)->description('Maximum number of results to return'),
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
}
