<?php

namespace App\Console\Commands;

use App\Models\ElementLock;
use Illuminate\Console\Command;

class CleanupExpiredLocks extends Command
{
    protected $signature = 'locks:cleanup-expired';

    protected $description = 'Delete expired seat locks to free up availability';

    public function handle(): int
    {
        $deleted = ElementLock::where('expires_at', '<=', now())->delete();

        $this->info("Deleted {$deleted} expired lock(s).");

        return Command::SUCCESS;
    }
}