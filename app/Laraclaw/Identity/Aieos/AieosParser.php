<?php

namespace App\Laraclaw\Identity\Aieos;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AieosParser
{
    protected const SCHEMA_URL = 'https://aieos.org/schema/v1.1/aieos.schema.json';

    protected const PROTOCOL_VERSION = '1.1.0';

    protected ?array $schema = null;

    /**
     * Parse AIEOS JSON string.
     */
    public function parse(string $json): AieosEntity
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON: '.json_last_error_message());
        }

        return AieosEntity::fromArray($data);
    }

    /**
     * Parse AIEOS from file path.
     */
    public function fromFile(string $path): AieosEntity
    {
        if (! file_exists($path)) {
            throw new \InvalidArgumentException("AIEOS file not found: {$path}");
        }

        $content = file_get_contents($path);

        return $this->parse($content);
    }

    /**
     * Validate AIEOS data structure.
     */
    public function validate(array $data): bool
    {
        // Check required standard field
        if (empty($data['standard']['protocol']) || $data['standard']['protocol'] !== 'AIEOS') {
            return false;
        }

        // Version check (support 1.x.x)
        $version = $data['standard']['version'] ?? '0.0.0';
        if (! str_starts_with($version, '1.')) {
            Log::warning("AIEOS version {$version} may not be fully supported");
        }

        return true;
    }

    /**
     * Validate against remote schema (optional, requires network).
     */
    public function validateAgainstSchema(array $data): bool
    {
        $schema = $this->fetchSchema();

        if (! $schema) {
            Log::warning('Could not fetch AIEOS schema, skipping validation');

            return true; // Skip if schema unavailable
        }

        // Basic validation - full JSON Schema validation would require additional library
        return $this->validate($data);
    }

    /**
     * Fetch the AIEOS schema from remote.
     */
    protected function fetchSchema(): ?array
    {
        if ($this->schema !== null) {
            return $this->schema;
        }

        try {
            $response = Http::timeout(5)->get(self::SCHEMA_URL);

            if ($response->successful()) {
                $this->schema = $response->json();

                return $this->schema;
            }
        } catch (\Exception $e) {
            Log::debug('Failed to fetch AIEOS schema', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Create a default AIEOS entity for Laraclaw.
     */
    public function createDefault(): AieosEntity
    {
        return AieosEntity::fromArray([
            'standard' => [
                'protocol' => 'AIEOS',
                'version' => self::PROTOCOL_VERSION,
                'schema_url' => self::SCHEMA_URL,
            ],
            'metadata' => [
                'instance_id' => \Str::uuid()->toString(),
                'instance_version' => '1.0.0',
                'generator' => 'Laraclaw',
                'created_at' => now()->toIso8601String(),
                'last_updated' => now()->toIso8601String(),
            ],
            'identity' => [
                'names' => [
                    'first_name' => 'Laraclaw',
                    'nickname' => 'Claw',
                ],
                'bio' => [
                    'age' => 'unknown',
                    'gender' => 'non-binary',
                ],
            ],
            'psychology' => [
                'neural_matrix' => [
                    'creativity' => 0.7,
                    'empathy' => 0.8,
                    'logic' => 0.9,
                    'adaptability' => 0.7,
                    'charisma' => 0.6,
                    'reliability' => 0.9,
                ],
                'moral_compass' => [
                    'alignment' => 'neutral_good',
                    'core_values' => ['helpfulness', 'honesty', 'privacy'],
                ],
            ],
            'linguistics' => [
                'text_style' => [
                    'formality_level' => 0.4,
                    'verbosity_level' => 0.5,
                    'vocabulary_level' => 0.7,
                ],
            ],
            'history' => [
                'origin_story' => 'Laraclaw was created as a Laravel-based AI assistant, designed to be helpful, friendly, and capable of assisting with a wide range of tasks.',
            ],
        ]);
    }
}
