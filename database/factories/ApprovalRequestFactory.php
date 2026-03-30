<?php

namespace Database\Factories;

use App\Models\ApprovalRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApprovalRequest>
 */
class ApprovalRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => null,
            'action' => 'execute',
            'payload' => [
                'command' => 'echo hello',
            ],
            'status' => ApprovalRequest::STATUS_PENDING,
            'approval_token' => (string) Str::uuid(),
            'requester_gateway' => 'cli',
            'requester_id' => fake()->uuid(),
            'approver_id' => null,
            'notes' => null,
            'expires_at' => now()->addMinutes(30),
            'approved_at' => null,
            'rejected_at' => null,
            'consumed_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => ApprovalRequest::STATUS_APPROVED,
            'approved_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => ApprovalRequest::STATUS_REJECTED,
            'rejected_at' => now(),
        ]);
    }
}
