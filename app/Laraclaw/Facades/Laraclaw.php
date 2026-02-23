<?php

namespace App\Laraclaw\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Laraclaw\Memory\MemoryManager memory()
 * @method static \App\Laraclaw\Agents\CoreAgent agent()
 * @method static \App\Models\Conversation startConversation(?int $userId = null, string $gateway = 'cli', ?string $title = null)
 * @method static string chat(\App\Models\Conversation $conversation, string $message)
 * @method static string ask(string $message, ?int $userId = null)
 *
 * @see \App\Laraclaw\Laraclaw
 */
class Laraclaw extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laraclaw';
    }
}
