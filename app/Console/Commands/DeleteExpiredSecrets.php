<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Secret;

class DeleteExpiredSecrets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'secrets:delete-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired secrets from the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expiredSecrets = Secret::where('expires_at', '<', now())->get();

        if ($expiredSecrets->isEmpty()){
            $this->info('No expired secrets found');
            return 0;
        }

        $deleteCount = $expiredSecrets->count();
        Secret::where('expires_at','<',now())->delete();

        $this->info('Deleted {$deletedCount} expired secrets');
        return 0;
    }

}
