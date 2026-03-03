<?php

namespace App\Console\Commands;

use App\Laraclaw\Security\ApprovalManager;
use Illuminate\Console\Command;

class LaraclawApproveCommand extends Command
{
    protected $signature = 'laraclaw:approval
        {id? : Approval request ID}
        {--approve : Approve the request}
        {--reject : Reject the request}
        {--notes= : Optional decision notes}
        {--approver=cli : Approver identifier}';

    protected $description = 'List, approve, or reject Laraclaw approval requests';

    /**
     * Execute the console command.
     */
    public function handle(ApprovalManager $approvals): int
    {
        $id = $this->argument('id');

        if ($id === null) {
            $pending = $approvals->pending();

            if ($pending === []) {
                $this->info('No pending approval requests.');

                return self::SUCCESS;
            }

            $this->table(
                ['ID', 'Action', 'Requester', 'Gateway', 'Expires At'],
                collect($pending)->map(fn ($request) => [
                    $request->id,
                    $request->action,
                    $request->requester_id,
                    $request->requester_gateway,
                    optional($request->expires_at)?->toDateTimeString(),
                ])->all()
            );

            return self::SUCCESS;
        }

        $request = $approvals->findById((int) $id);

        if (! $request) {
            $this->error('Approval request not found.');

            return self::FAILURE;
        }

        if ($this->option('approve')) {
            $approvals->approve(
                request: $request,
                approverId: (string) $this->option('approver'),
                notes: $this->option('notes') ? (string) $this->option('notes') : null,
            );

            $this->info("Approved request #{$request->id}.");

            return self::SUCCESS;
        }

        if ($this->option('reject')) {
            $approvals->reject(
                request: $request,
                approverId: (string) $this->option('approver'),
                notes: $this->option('notes') ? (string) $this->option('notes') : null,
            );

            $this->info("Rejected request #{$request->id}.");

            return self::SUCCESS;
        }

        $this->line("ID: {$request->id}");
        $this->line("Status: {$request->status}");
        $this->line("Action: {$request->action}");
        $this->line('Payload: '.json_encode($request->payload));

        return self::SUCCESS;
    }
}
