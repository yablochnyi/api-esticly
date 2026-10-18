<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\GooglePlaySubscriptions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncGoogleSubscriptions extends Command
{
    protected $signature = 'billing:sync-google {--user= : Reconcile one organization} {--force : Ignore freshness interval}';

    protected $description = 'Reconcile Google subscriptions independently of RTDN delivery';

    public function handle(): int
    {
        if (! GooglePlaySubscriptions::isConfigured()) {
            $this->error('Google Play is not configured.');

            return self::FAILURE;
        }
        $lock = Cache::lock('billing:sync-google', 3600);
        if (! $lock->get()) {
            return self::SUCCESS;
        }
        $checked = 0;
        $failed = 0;
        try {
            User::whereIn('id', \App\Models\Subscription::where('provider', GooglePlaySubscriptions::PROVIDER)->select('user_id'))
                ->when($this->option('user'), fn ($q, $id) => $q->whereKey($id))
                ->chunkById(50, function ($users) use (&$checked, &$failed) {
                    foreach ($users as $org) {
                        if (! $this->option('force') && ! GooglePlaySubscriptions::shouldSyncOrg($org)) {
                            continue;
                        }
                        try {
                            GooglePlaySubscriptions::syncLatestForOrg($org);
                            $checked++;
                        } catch (\Throwable $e) {
                            $failed++;
                            Log::warning('google_play_periodic_sync_failed', ['user_id' => $org->id, 'exception_type' => $e::class]);
                        }
                    }
                });
        } finally {
            $lock->release();
        }
        $this->info(json_encode(['checked' => $checked, 'failed' => $failed]));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
