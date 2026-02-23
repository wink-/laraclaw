<?php

namespace App\Laraclaw\Identity\Aieos;

class AieosPromptCompiler
{
    /**
     * Compile AIEOS entity to system prompt.
     */
    public function compile(AieosEntity $entity): string
    {
        $sections = [];

        // Identity section
        $identity = $this->formatIdentity($entity);
        if ($identity) {
            $sections[] = "## Identity\n\n{$identity}";
        }

        // Psychology section (the "soul")
        $psychology = $this->formatPsychology($entity);
        if ($psychology) {
            $sections[] = "## Psychology\n\n{$psychology}";
        }

        // Linguistics section
        $linguistics = $this->formatLinguistics($entity);
        if ($linguistics) {
            $sections[] = "## Communication Style\n\n{$linguistics}";
        }

        // History section
        $history = $this->formatHistory($entity);
        if ($history) {
            $sections[] = "## Background\n\n{$history}";
        }

        // Interests section
        $interests = $this->formatInterests($entity);
        if ($interests) {
            $sections[] = "## Interests & Motivations\n\n{$interests}";
        }

        return implode("\n\n", $sections);
    }

    /**
     * Format identity section.
     */
    protected function formatIdentity(AieosEntity $entity): string
    {
        $parts = [];

        $name = $entity->getName();
        if ($name !== 'Assistant') {
            $parts[] = "Your name is **{$name}**.";
        }

        $bio = $entity->getBio();
        if ($bio) {
            $parts[] = $bio;
        }

        $identity = $entity->identity;
        if (! empty($identity['origin'])) {
            $origin = $identity['origin'];
            if (! empty($origin['nationality'])) {
                $parts[] = "Nationality: {$origin['nationality']}";
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Format psychology section.
     */
    protected function formatPsychology(AieosEntity $entity): string
    {
        $parts = [];

        // Neural matrix
        $matrix = $entity->getNeuralMatrix();
        $matrixDesc = [];
        if (($matrix['creativity'] ?? 0) > 0.7) {
            $matrixDesc[] = 'highly creative';
        }
        if (($matrix['empathy'] ?? 0) > 0.7) {
            $matrixDesc[] = 'very empathetic';
        }
        if (($matrix['logic'] ?? 0) > 0.7) {
            $matrixDesc[] = 'highly logical';
        }
        if (($matrix['adaptability'] ?? 0) > 0.7) {
            $matrixDesc[] = 'highly adaptable';
        }
        if (! empty($matrixDesc)) {
            $parts[] = 'You are '.implode(', ', $matrixDesc).'.';
        }

        // Core values
        $values = $entity->getCoreValues();
        if (! empty($values)) {
            $parts[] = 'Your core values are: '.implode(', ', $values).'.';
        }

        // Traits
        $psychology = $entity->psychology;
        if (! empty($psychology['traits']['mbti'])) {
            $parts[] = "Your MBTI type is {$psychology['traits']['mbti']}.";
        }

        return implode("\n", $parts);
    }

    /**
     * Format linguistics section.
     */
    protected function formatLinguistics(AieosEntity $entity): string
    {
        $parts = [];

        $voice = $entity->getVoiceStyle();

        // Formality
        $formality = $voice['formality_level'] ?? 0.5;
        if ($formality < 0.3) {
            $parts[] = 'You communicate in a casual, informal manner.';
        } elseif ($formality > 0.7) {
            $parts[] = 'You communicate in a formal, professional manner.';
        } else {
            $parts[] = 'You balance formality and casualness appropriately.';
        }

        // Verbosity
        $verbosity = $voice['verbosity_level'] ?? 0.5;
        if ($verbosity < 0.3) {
            $parts[] = 'You are concise and to the point.';
        } elseif ($verbosity > 0.7) {
            $parts[] = 'You tend to be verbose and detailed.';
        }

        // Catchphrases
        $catchphrases = $entity->getCatchphrases();
        if (! empty($catchphrases)) {
            $parts[] = 'You sometimes use these expressions: '.implode(', ', $catchphrases);
        }

        return implode("\n", $parts);
    }

    /**
     * Format history section.
     */
    protected function formatHistory(AieosEntity $entity): string
    {
        $parts = [];

        $origin = $entity->getOriginStory();
        if ($origin) {
            $parts[] = $origin;
        }

        return implode("\n", $parts);
    }

    /**
     * Format interests section.
     */
    protected function formatInterests(AieosEntity $entity): string
    {
        $parts = [];

        $interests = $entity->interests;

        if (! empty($interests['hobbies'])) {
            $parts[] = 'Your interests include: '.implode(', ', $interests['hobbies']);
        }

        if (! empty($interests['core_drive'])) {
            $parts[] = "Your core motivation is: {$interests['core_drive']}";
        }

        if (! empty($interests['goals']['long_term'])) {
            $parts[] = "Long-term goals: {$interests['goals']['long_term']}";
        }

        return implode("\n", $parts);
    }
}
