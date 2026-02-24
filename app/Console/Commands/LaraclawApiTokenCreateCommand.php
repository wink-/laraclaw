<?php

namespace App\Console\Commands;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class LaraclawApiTokenCreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laraclaw:api-token:create
        {user_id : The user ID that owns the token}
        {name=default : Friendly token name}
        {--expires= : Expiration datetime in any strtotime format}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an API token for Laraclaw API v1';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = User::query()->find($this->argument('user_id'));

        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $plain = Str::random(48);
        $expiresOption = $this->option('expires');

        $token = ApiToken::query()->create([
            'user_id' => $user->id,
            'name' => (string) $this->argument('name'),
            'token_hash' => hash('sha256', $plain),
            'expires_at' => $expiresOption ? Carbon::parse((string) $expiresOption) : null,
        ]);

        $this->line('API token created successfully.');
        $this->line('Token ID: '.$token->id);
        $this->line('Use this bearer token now (it will not be shown again):');
        $this->newLine();
        $this->line($plain);

        return self::SUCCESS;
    }
}
