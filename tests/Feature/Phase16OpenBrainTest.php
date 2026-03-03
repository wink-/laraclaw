<?php

use App\Jobs\ProcessNewMemoryJob;
use App\Laraclaw\Gateways\SlackGateway;
use App\Models\ApiToken;
use App\Models\Conversation;
use App\Models\Memory;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('queues new memory processing from dedicated web slack webhook route', function () {
    Queue::fake();

    config()->set('services.slack.signing_secret', 'test-secret');

    $payload = [
        'type' => 'event_callback',
        'event' => [
            'type' => 'message',
            'user' => 'U999',
            'text' => 'Remember to ship Open Brain this week',
            'channel' => 'C999',
            'ts' => '1710000000.200000',
        ],
    ];

    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $timestamp = (string) time();
    $signature = 'v0='.hash_hmac('sha256', 'v0:'.$timestamp.':'.$body, 'test-secret');

    $this->withHeaders([
        'X-Slack-Request-Timestamp' => $timestamp,
        'X-Slack-Signature' => $signature,
        'Content-Type' => 'application/json',
    ])->postJson('/laraclaw/webhooks/slack', $payload)
        ->assertStatus(202)
        ->assertJsonPath('status', 'queued');

    Queue::assertPushed(ProcessNewMemoryJob::class, function (ProcessNewMemoryJob $job) {
        return $job->platform === 'slack'
            && str_contains($job->content, 'Open Brain');
    });
});

it('queues new memory processing from slack webhook', function () {
    Queue::fake();

    config()->set('services.slack.signing_secret', 'test-secret');

    $payload = [
        'type' => 'event_callback',
        'event' => [
            'type' => 'message',
            'user' => 'U123',
            'text' => 'Remember we picked Stripe for payments',
            'channel' => 'C123',
            'ts' => '1710000000.100000',
        ],
    ];

    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $timestamp = (string) time();
    $signature = 'v0='.hash_hmac('sha256', 'v0:'.$timestamp.':'.$body, 'test-secret');

    $this->withHeaders([
        'X-Slack-Request-Timestamp' => $timestamp,
        'X-Slack-Signature' => $signature,
        'Content-Type' => 'application/json',
    ])->postJson('/api/webhooks/slack', $payload)
        ->assertStatus(202)
        ->assertJsonPath('status', 'queued');

    Queue::assertPushed(ProcessNewMemoryJob::class, function (ProcessNewMemoryJob $job) {
        return $job->platform === 'slack'
            && str_contains($job->content, 'Stripe');
    });
});

it('processes memory job and stores memory entry', function () {
    $conversation = Conversation::factory()->create([
        'gateway' => 'slack',
        'gateway_conversation_id' => 'C123:1710000000.100000',
    ]);

    $slackGateway = \Mockery::mock(SlackGateway::class);
    $slackGateway->shouldReceive('sendMessage')->once()->andReturnTrue();
    app()->instance(SlackGateway::class, $slackGateway);

    $job = new ProcessNewMemoryJob(
        conversationId: $conversation->id,
        platform: 'slack',
        content: 'Had a meeting with Sarah. TODO follow up on Stripe setup.',
        parsedMessage: ['sender_id' => 'U123'],
    );

    $job->handle(app(\App\Laraclaw\Memory\MemoryStore::class));

    $memory = Memory::query()->first();

    expect($memory)->not->toBeNull()
        ->and($memory->platform_source)->toBe('slack')
        ->and($memory->conversation_id)->toBe($conversation->id)
        ->and($memory->content)->toContain('meeting with Sarah')
        ->and($memory->metadata)->toBeArray();
});

it('serves mcp memory search, recent, and stats endpoints', function () {
    $user = User::factory()->create();

    $plainToken = 'phase16-token';

    ApiToken::query()->create([
        'user_id' => $user->id,
        'name' => 'phase16',
        'token_hash' => hash('sha256', $plainToken),
    ]);

    Memory::query()->create([
        'user_id' => $user->id,
        'platform_source' => 'slack',
        'content' => 'We decided to use Stripe for the payments API.',
        'metadata' => ['topics' => ['stripe', 'payments']],
    ]);

    Memory::query()->create([
        'user_id' => $user->id,
        'platform_source' => 'telegram',
        'content' => 'Follow up with Sarah tomorrow.',
        'metadata' => ['topics' => ['follow-up']],
    ]);

    $headers = [
        'Authorization' => 'Bearer '.$plainToken,
    ];

    $this->withHeaders($headers)
        ->postJson('/api/mcp/search', ['query' => 'Stripe', 'limit' => 5])
        ->assertSuccessful()
        ->assertJsonPath('tool', 'semantic_search')
        ->assertJsonCount(1, 'results');

    $this->withHeaders($headers)
        ->getJson('/api/mcp/recent?days=30&limit=10')
        ->assertSuccessful()
        ->assertJsonPath('tool', 'list_recent')
        ->assertJsonCount(2, 'results');

    $this->withHeaders($headers)
        ->getJson('/api/mcp/stats?days=30')
        ->assertSuccessful()
        ->assertJsonPath('tool', 'stats')
        ->assertJsonPath('stats.total_memories', 2);
});
