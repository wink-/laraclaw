<?php

namespace App\Laraclaw\AI;

use Laravel\Ai\Gateway\Prism\PrismGateway;
use Laravel\Ai\Providers\Provider;
use Prism\Prism\Enums\Provider as PrismProvider;

class ZaiPrismGateway extends PrismGateway
{
    protected static function toPrismProvider(Provider $provider): PrismProvider
    {
        if ($provider->driver() === 'zai') {
            return PrismProvider::Z;
        }

        return parent::toPrismProvider($provider);
    }
}
