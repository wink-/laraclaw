<?php

use App\Laraclaw\Facades\Laraclaw;
use App\Models\ApiToken;
use App\Models\Conversation;
use App\Models\LaraclawNotification;
use App\Models\TokenUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects api requests without bearer token', function () {
    $this->getJson('/api/v1/conversations')
        ->assertUnauthorized();
});

it('allows api message flow with token and records token usage', function () {
    $user = User::factory()->create();
    $conversation = Conversation::query()->create([
        'user_id' => $user->id,
        'gateway' => 'api',
        'title' => 'API Test',
    ]);

    $plainToken = 'phase12-test-token';

    ApiToken::query()->create([
        'user_id' => $user->id,
        'name' => 'test',
        'token_hash' => hash('sha256', $plainToken),
    ]);

    Laraclaw::shouldReceive('chat')
        ->once()
        ->andReturnUsing(function (Conversation $targetConversation, string $message) {
            $assistantMessage = $targetConversation->messages()->create([
                'role' => 'assistant',
                'content' => 'Mocked: '.$message,
                'metadata' => ['response_mode' => 'single'],
            ]);

            TokenUsage::query()->create([
                'conversation_id' => $targetConversation->id,
                'message_id' => $assistantMessage->id,
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'total_tokens' => 30,
                'cost_usd' => 0.000010,
            ]);

            return 'Mocked: '.$message;
        });

    $this->withHeaders([
        'Authorization' => 'Bearer '.$plainToken,
    ])->postJson('/api/v1/conversations/'.$conversation->id.'/messages', [
        'message' => 'hello api',
    ])->assertSuccessful()
        ->assertJsonPath('response', 'Mocked: hello api');

    expect(TokenUsage::query()->count())->toBe(1);
});

it('enforces api rate limiting', function () {
    config()->set('laraclaw.rate_limits.api_per_minute', 1);

    $user = User::factory()->create();
    $plainToken = 'phase12-limit-token';

    ApiToken::query()->create([
        'user_id' => $user->id,
        'name' => 'limit-test',
        'token_hash' => hash('sha256', $plainToken),
    ]);

    $headers = [
        'Authorization' => 'Bearer '.$plainToken,
    ];

    $this->withHeaders($headers)
        ->getJson('/api/v1/conversations')
        ->assertSuccessful();

    $this->withHeaders($headers)
        ->getJson('/api/v1/conversations')
        ->assertStatus(429);
});

it('marks unsupported proactive notifications as failed when dispatch runs', function () {
    LaraclawNotification::query()->create([
        'gateway' => 'unsupported',
        'channel_id' => '123',
        'message' => 'test notification',
        'status' => 'pending',
    ]);

    $this->artisan('laraclaw:dispatch-notifications')
        ->assertSuccessful();

    expect(LaraclawNotification::query()->first()->status)->toBe('failed');
});
