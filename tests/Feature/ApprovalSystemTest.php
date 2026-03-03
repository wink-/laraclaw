<?php

use App\Laraclaw\Security\ApprovalManager;
use App\Laraclaw\Skills\ExecuteSkill;
use App\Models\ApprovalRequest;

it('creates approves and consumes approval requests', function () {
    $manager = app(ApprovalManager::class);

    $request = $manager->createRequest(
        action: 'execute',
        payload: ['command' => 'echo hello'],
        requesterGateway: 'cli',
        requesterId: 'tester',
        ttlMinutes: 10,
    );

    expect($request->isPending())->toBeTrue();

    $manager->approve($request, 'cli', 'approved for test');

    expect($request->fresh()?->isApproved())->toBeTrue();
    expect($manager->canProceed($request->id, 'execute', ['command' => 'echo hello']))->toBeTrue();

    $manager->consume($request->id);

    expect($manager->canProceed($request->id, 'execute', ['command' => 'echo hello']))->toBeFalse();
});

it('requires approval before executing commands in supervised autonomy', function () {
    config()->set('laraclaw.security.autonomy', 'supervised');

    $skill = app(ExecuteSkill::class);

    $firstAttempt = $skill->execute([
        'command' => 'echo approval-flow',
    ]);

    expect($firstAttempt)->toContain('Approval required');

    $approval = ApprovalRequest::query()->latest('id')->first();

    expect($approval)->not->toBeNull();

    app(ApprovalManager::class)->approve($approval, 'cli', 'ok');

    $approvedAttempt = $skill->execute([
        'command' => 'echo approval-flow',
        'approval_id' => $approval->id,
    ]);

    expect($approvedAttempt)->toContain('Output:');
    expect($approvedAttempt)->toContain('approval-flow');
});
