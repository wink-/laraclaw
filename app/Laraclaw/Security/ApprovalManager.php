<?php

namespace App\Laraclaw\Security;

use App\Models\ApprovalRequest;
use Illuminate\Support\Str;

class ApprovalManager
{
    public function createRequest(
        string $action,
        array $payload = [],
        ?int $conversationId = null,
        ?string $requesterGateway = null,
        ?string $requesterId = null,
        ?int $ttlMinutes = null,
    ): ApprovalRequest {
        $ttl = $ttlMinutes ?? (int) config('laraclaw.security.approval_ttl_minutes', 30);

        return ApprovalRequest::query()->create([
            'conversation_id' => $conversationId,
            'action' => $action,
            'payload' => $payload,
            'status' => 'pending',
            'approval_token' => (string) Str::uuid(),
            'requester_gateway' => $requesterGateway,
            'requester_id' => $requesterId,
            'expires_at' => now()->addMinutes($ttl),
        ]);
    }

    public function approve(ApprovalRequest $request, ?string $approverId = null, ?string $notes = null): ApprovalRequest
    {
        $request->forceFill([
            'status' => 'approved',
            'approver_id' => $approverId,
            'notes' => $notes,
            'approved_at' => now(),
        ])->save();

        return $request->refresh();
    }

    public function reject(ApprovalRequest $request, ?string $approverId = null, ?string $notes = null): ApprovalRequest
    {
        $request->forceFill([
            'status' => 'rejected',
            'approver_id' => $approverId,
            'notes' => $notes,
            'rejected_at' => now(),
        ])->save();

        return $request->refresh();
    }

    public function findById(int $id): ?ApprovalRequest
    {
        return ApprovalRequest::query()->find($id);
    }

    public function findByToken(string $token): ?ApprovalRequest
    {
        return ApprovalRequest::query()->where('approval_token', $token)->first();
    }

    public function canProceed(int $approvalId, string $action, array $payload = []): bool
    {
        $request = $this->findById($approvalId);

        if (! $request || ! $request->isApproved()) {
            return false;
        }

        if ($request->consumed_at !== null) {
            return false;
        }

        if ($request->expires_at !== null && now()->greaterThan($request->expires_at)) {
            return false;
        }

        if ($request->action !== $action) {
            return false;
        }

        if (! empty($payload) && ($request->payload ?? []) !== $payload) {
            return false;
        }

        return true;
    }

    public function consume(int $approvalId): void
    {
        ApprovalRequest::query()
            ->whereKey($approvalId)
            ->whereNull('consumed_at')
            ->update([
                'consumed_at' => now(),
            ]);
    }

    /**
     * @return array<int, ApprovalRequest>
     */
    public function pending(int $limit = 20): array
    {
        return ApprovalRequest::query()
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->all();
    }
}
