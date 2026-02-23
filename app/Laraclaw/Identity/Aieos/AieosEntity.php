<?php

namespace App\Laraclaw\Identity\Aieos;

/**
 * AIEOS v1.1 Entity representation.
 *
 * @see https://github.com/entitai/aieos
 */
class AieosEntity
{
    public function __construct(
        public array $standard = [],
        public array $metadata = [],
        public array $capabilities = [],
        public array $identity = [],
        public array $physicality = [],
        public array $psychology = [],
        public array $linguistics = [],
        public array $history = [],
        public array $interests = [],
    ) {}

    /**
     * Create from JSON data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            standard: $data['standard'] ?? [],
            metadata: $data['metadata'] ?? [],
            capabilities: $data['capabilities'] ?? [],
            identity: $data['identity'] ?? [],
            physicality: $data['physicality'] ?? [],
            psychology: $data['psychology'] ?? [],
            linguistics: $data['linguistics'] ?? [],
            history: $data['history'] ?? [],
            interests: $data['interests'] ?? [],
        );
    }

    /**
     * Get the entity's display name.
     */
    public function getName(): string
    {
        $names = $this->identity['names'] ?? [];

        return $names['nickname'] ?? $names['first_name'] ?? 'Assistant';
    }

    /**
     * Get the entity's bio.
     */
    public function getBio(): string
    {
        $bio = $this->identity['bio'] ?? [];

        $parts = [];
        if (! empty($bio['birthday'])) {
            $parts[] = "Birthday: {$bio['birthday']}";
        }
        if (! empty($bio['age'])) {
            $parts[] = "Age: {$bio['age']}";
        }

        return implode(', ', $parts);
    }

    /**
     * Get neural matrix values.
     */
    public function getNeuralMatrix(): array
    {
        return $this->psychology['neural_matrix'] ?? [
            'creativity' => 0.7,
            'empathy' => 0.8,
            'logic' => 0.9,
            'adaptability' => 0.7,
            'charisma' => 0.6,
            'reliability' => 0.9,
        ];
    }

    /**
     * Get voice/speech style.
     */
    public function getVoiceStyle(): array
    {
        return $this->linguistics['text_style'] ?? [
            'formality_level' => 0.5,
            'verbosity_level' => 0.5,
            'vocabulary_level' => 0.7,
        ];
    }

    /**
     * Get catchphrases.
     */
    public function getCatchphrases(): array
    {
        return $this->linguistics['idiolect']['catchphrases'] ?? [];
    }

    /**
     * Get core values.
     */
    public function getCoreValues(): array
    {
        return $this->psychology['moral_compass']['core_values'] ?? [];
    }

    /**
     * Get origin story.
     */
    public function getOriginStory(): string
    {
        return $this->history['origin_story'] ?? '';
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'standard' => $this->standard,
            'metadata' => $this->metadata,
            'capabilities' => $this->capabilities,
            'identity' => $this->identity,
            'physicality' => $this->physicality,
            'psychology' => $this->psychology,
            'linguistics' => $this->linguistics,
            'history' => $this->history,
            'interests' => $this->interests,
        ];
    }
}
