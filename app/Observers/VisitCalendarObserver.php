<?php

namespace App\Observers;

use App\Jobs\SyncGoogleCalendarVisit;
use App\Models\GoogleCalendarConnection;
use App\Models\Visit;
use App\Services\GoogleCalendarClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class VisitCalendarObserver
{
    public function created(Visit $visit): void
    {
        $this->queue($visit);
    }

    public function updated(Visit $visit): void
    {
        if ($visit->wasChanged(['user_id', 'staff_id', 'service_id', 'client_name', 'starts_at', 'ends_at', 'status'])) {
            $this->queue($visit);
        }
    }

    public function deleted(Visit $visit): void
    {
        $this->queue($visit);
    }

    private function queue(Visit $visit): void
    {
        if (! app(GoogleCalendarClient::class)->configured()) {
            return;
        }
        // Capture the old owner before Eloquent resets its original attributes.
        $owners = array_unique(array_filter([$visit->user_id, $visit->getOriginal('user_id')]));
        $visitId = (int) $visit->id;
        $visit->getConnection()->afterCommit(function () use ($owners, $visitId) {
            try {
                GoogleCalendarConnection::query()->where('status', 'connected')
                    ->whereHas('user', fn (Builder $query) => $query->whereIn('id', $owners)->orWhereIn('organization_id', $owners))
                    ->select('id')->chunkById(100, function ($connections) use ($visitId) {
                        foreach ($connections as $connection) {
                            SyncGoogleCalendarVisit::dispatch($connection->id, $visitId);
                        }
                    });
            } catch (\Throwable) {
                // The visit is already committed. Periodic reconciliation can recover.
                Log::warning('google_calendar_dispatch_failed', ['visit_id' => $visitId]);
            }
        });
    }
}
