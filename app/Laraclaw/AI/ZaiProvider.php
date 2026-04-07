<?php

namespace App\Laraclaw\AI;

use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Providers\Concerns\GeneratesText;
use Laravel\Ai\Providers\Concerns\HasTextGateway;
use Laravel\Ai\Providers\Concerns\StreamsText;
use Laravel\Ai\Providers\Provider;

class ZaiProvider extends Provider implements TextProvider
{
    use GeneratesText, HasTextGateway, StreamsText;

    public function defaultTextModel(): string
    {
        return $this->config['models']['text']['default'] ?? 'glm-5.1';
    }

    public function cheapestTextModel(): string
    {
        return $this->config['models']['text']['cheapest'] ?? 'glm-4.5-air';
    }

    public function smartestTextModel(): string
    {
        return $this->config['models']['text']['smartest'] ?? 'glm-5.1';
    }
}
