<?php

namespace App\Laraclaw\Voice;

use Illuminate\Support\Facades\Log;
use Laravel\Ai\Audio;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Transcription;

class VoiceService
{
    protected string $voicePath;

    protected array $ttsConfig = [];

    protected array $sttConfig = [];

    public function __construct()
    {
        $this->voicePath = config('laraclaw.voice.path', storage_path('laraclaw/voice'));
        $this->ttsConfig = config('audio', []);
    }

    /**
     * Convert text to speech.
     */
    public function speak(string $text, array $options = []): string
    {
        $provider = $options['provider'] ?? config('laraclaw.voice.tts_provider', 'openai');

        try {
            $audio = Audio::text($text)
                ->withVoice($options['voice'] ?? 'nova')
                ->provider($this->getLabEnum($provider))
                ->generate();

            // Save audio file
            $filename = 'tts-'.uniqid().'.mp3';
            $path = $this->voicePath.'/'.$filename;

            $this->ensureDirectoryExists();

            file_put_contents($path, $audio);

            return $path;
        } catch (\Exception $e) {
            Log::error('TTS failed', ['error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Convert speech to text (transcription).
     */
    public function transcribe(string $audioPath, array $options = []): string
    {
        $provider = $options['provider'] ?? config('laraclaw.voice.stt_provider', 'openai');

        if (! file_exists($audioPath)) {
            throw new \InvalidArgumentException("Audio file not found: {$audioPath}");
        }

        try {
            $transcription = Transcription::audio($audioPath)
                ->provider($this->getLabEnum($provider))
                ->transcribe();

            return $transcription;
        } catch (\Exception $e) {
            Log::error('STT failed', ['error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Save voice recording.
     */
    public function saveRecording($audioData, ?string $filename = null): string
    {
        $this->ensureDirectoryExists();

        $filename = $filename ?? 'recording-'.uniqid().'.webm';
        $path = $this->voicePath.'/'.$filename;

        if (is_resource($audioData)) {
            $data = stream_get_contents($audioData);
            fclose($audioData);
            file_put_contents($path, $data);
        } else {
            file_put_contents($path, $audioData);
        }

        return $path;
    }

    /**
     * Get available voices for TTS.
     */
    public function getAvailableVoices(string $provider = 'openai'): array
    {
        // This would typically call the provider's API to get available voices
        // For now, return a static list based on provider
        return match ($provider) {
            'openai' => [
                ['id' => 'alloy', 'name' => 'Alloy'],
                ['id' => 'echo', 'name' => 'Echo'],
                ['id' => 'fable', 'name' => 'Fable'],
                ['id' => 'onyx', 'name' => 'Onyx'],
                ['id' => 'nova', 'name' => 'Nova'],
                ['id' => 'shimmer', 'name' => 'Shimmer'],
            ],
            'elevenlabs' => [
                ['id' => 'rachel', 'name' => 'Rachel'],
                ['id' => 'domi', 'name' => 'Domi'],
                ['id' => 'bella', 'name' => 'Bella'],
                ['id' => 'antoni', 'name' => 'Antoni'],
            ],
            default => [
                ['id' => 'default', 'name' => 'Default'],
            ],
        };
    }

    /**
     * Get the Lab enum for a provider.
     */
    protected function getLabEnum(string $provider): Lab
    {
        return match ($provider) {
            'elevenlabs' => Lab::ElevenLabs,
            'openai' => Lab::OpenAI,
            'mistral' => Lab::Mistral,
            default => Lab::OpenAI,
        };
    }

    /**
     * Ensure the voice directory exists.
     */
    protected function ensureDirectoryExists(): void
    {
        if (! is_dir($this->voicePath)) {
            mkdir($this->voicePath, 0755, true);
        }
    }
}
