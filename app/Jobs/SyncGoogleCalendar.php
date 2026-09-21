<?php

namespace App\Jobs;

use App\Services\GoogleCalendarSync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class SyncGoogleCalendar implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 55;

    public int $tries = 1;

    public int $uniqueFor = 90;

    public function __construct(public int $connectionId) {}

    public function uniqueId(): string
    {
        return (string) $this->connectionId;
    }

    public function uniqueVia()
    {
        return Cache::store(config('google_calendar.cache_store'));
    }

    public function handle(GoogleCalendarSync $sync): void
    {
        $sync->run($this->connectionId);
    }
}
