<?php

namespace App\Jobs;

use App\Services\GoogleCalendarSync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncGoogleCalendarVisit implements ShouldQueue
{
    use Queueable;

    public int $timeout = 55;

    public int $tries = 30;

    public function __construct(public int $connectionId, public int $visitId) {}

    public function handle(GoogleCalendarSync $sync): void
    {
        // Do not deduplicate while running: a second edit must not be lost.
        // Re-read the latest visit under the same lock as the periodic sync.
        if (! $sync->run($this->connectionId, $this->visitId)) {
            $this->release(5);
        }
    }
}
