<?php

namespace App\Console\Commands;

use App\Services\BillingPaymentImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RefreshBillingAnalytics extends Command
{
    protected $signature = 'billing:refresh-analytics {--force : Refresh all known Google orders}';

    protected $description = 'Refresh the read-only billing analytics projection';

    public function handle(BillingPaymentImporter $importer): int
    {
        $lock = Cache::lock('billing:refresh-analytics', 3600);
        if (! $lock->get()) {
            $this->warn('Billing analytics refresh is already running.');

            return self::SUCCESS;
        }
        try {
            $result = $importer->refresh((bool) $this->option('force'));
            $this->info(json_encode($result));

            return $result['lookup_errors'] > 0 ? self::FAILURE : self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
