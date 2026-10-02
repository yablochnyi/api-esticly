<?php

namespace App\Services;

use App\Models\GoogleCalendarConnection;
use App\Models\Staff;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GoogleCalendarSync
{
    public function __construct(private GoogleCalendarClient $google) {}

    public static function lock(int $id)
    {
        return Cache::store(config('google_calendar.cache_store'))->lock('google-calendar:'.$id, 90);
    }

    public static function allowed(User $user): bool
    {
        if (! $user->staff_id) {
            return ! $user->organization_id || (int) $user->organization_id === (int) $user->id;
        }
        $staff = Staff::find($user->staff_id);

        return $staff && $staff->is_active
            && (int) $staff->user_id === (int) $user->organization_id
            && (bool) ($staff->permissions['base_access'] ?? false);
    }

    public static function visits(User $user): Builder
    {
        return Visit::query()->where('user_id', $user->organization_id ?: $user->id)
            ->when($user->staff_id, fn (Builder $query) => $query->where('staff_id', $user->staff_id));
    }

    public static function payload(Visit $visit, string $locale = 'en'): array
    {
        $status = in_array($visit->status, ['completed', 'cancelled'], true) ? $visit->status : 'pending';
        $locale = in_array($locale, ['en', 'uk', 'pl', 'cs', 'de', 'fr', 'it', 'es', 'pt'], true) ? $locale : 'en';
        $label = trans('calendar.statuses.'.$status, [], $locale);
        $parts = array_filter([$visit->service?->name, $visit->client_name], fn ($value) => filled($value));
        $title = trim(preg_replace('/[\r\n]+/u', ' ', implode(' - ', $parts))) ?: 'Esticly';

        return [
            'summary' => mb_substr('['.$label.'] '.$title, 0, 500),
            'description' => trans('calendar.status_label', [], $locale).': '.$label,
            'colorId' => match ($status) {
                'completed' => '10', // Google event palette: basil.
                'cancelled' => '11', // Tomato.
                default => '3', // Grape.
            },
            // Google's "cancelled" status hides/deletes the event. Keep the
            // visible copy confirmed and express the visit status in its text.
            'status' => 'confirmed',
            'start' => ['dateTime' => $visit->starts_at->copy()->utc()->toRfc3339String()],
            'end' => ['dateTime' => $visit->ends_at->copy()->utc()->toRfc3339String()],
            'visibility' => 'private',
            'transparency' => $status === 'cancelled' ? 'transparent' : 'opaque',
            'reminders' => ['useDefault' => false],
            'extendedProperties' => ['private' => ['esticlyVisitId' => (string) $visit->id]],
        ];
    }

    public function run(int $id, ?int $visitId = null): bool
    {
        if (! $this->google->configured()) {
            return true;
        }
        $lock = self::lock($id);
        if (! $lock->get()) {
            return false;
        }
        try {
            $connection = GoogleCalendarConnection::find($id);
            if (! $connection || $connection->status !== 'connected') {
                return true;
            }
            if (! $connection->user || ! self::allowed($connection->user)) {
                $connection->update(['status' => 'access_revoked', 'last_error' => 'access_revoked',
                    'access_token' => null, 'refresh_token' => null]);

                return true;
            }
            $operation = 'sync.prepare';
            try {
                $this->syncPage($connection, $visitId, $operation);
            } catch (\Throwable $e) {
                // Provider exceptions can contain access tokens and client data. Never log them.
                $reason = $e instanceof GoogleCalendarFailure ? $e->reason : 'provider_unavailable';
                $connection->update([
                    'last_error' => $reason,
                    'status' => in_array($reason, ['reconnect_required', 'calendar_missing'], true)
                        ? 'needs_reconnect' : 'connected',
                ]);
                $diagnostics = $e instanceof GoogleCalendarFailure ? $e->diagnostics : [];
                Log::warning('google_calendar_sync_failed', $diagnostics + [
                    'connection_id' => $id, 'user_id' => $connection->user_id,
                    'visit_id' => $visitId, 'reason' => $reason, 'operation' => $operation,
                    'exception_type' => ($e->getPrevious() ?? $e)::class,
                ]);

                return $connection->status !== 'connected';
            }

            return true;
        } finally {
            $lock->release();
        }
    }

    private function syncPage(GoogleCalendarConnection $connection, ?int $visitId, string &$operation): void
    {
        $deadline = microtime(true) + 20;
        $calendarPath = 'calendars/'.rawurlencode($connection->calendar_id);
        $operation = 'calendar.read';
        $check = $this->google->request($connection, 'GET', $calendarPath);
        if (in_array($check->status(), [404, 410], true)) {
            throw GoogleCalendarFailure::fromResponse('calendar_missing', $operation, $check);
        }
        if (! $check->successful()) {
            throw GoogleCalendarFailure::fromResponse('provider_unavailable', $operation, $check);
        }
        $eventPath = $calendarPath.'/events';
        $operation = 'events.cleanup';
        $visible = self::visits($connection->user);
        $stale = DB::table('google_calendar_events')->where('connection_id', $connection->id)
            ->when($visitId !== null, fn ($query) => $query->where('visit_id', $visitId))
            ->whereNotIn('visit_id', (clone $visible)->select('id'));
        foreach ((clone $stale)->orderBy('id')->limit(25)->get() as $mapping) {
            if ($visitId === null && microtime(true) >= $deadline) {
                return;
            }
            $response = $this->google->request($connection, 'DELETE', $eventPath.'/'.$mapping->event_id.'?sendUpdates=none');
            if (! $response->successful() && ! in_array($response->status(), [404, 410], true)) {
                throw GoogleCalendarFailure::fromResponse('provider_unavailable', 'event.delete', $response);
            }
            DB::table('google_calendar_events')->where('id', $mapping->id)->delete();
        }
        if ($stale->exists()) {
            return;
        }
        $operation = 'events.load';
        $visits = (clone $visible)->with('service')
            ->when($visitId !== null, fn ($query) => $query->whereKey($visitId),
                fn ($query) => $query->where('id', '>', $connection->sync_cursor))
            ->orderBy('id')->limit(100)->get();
        foreach ($visits as $visit) {
            if ($visitId === null && microtime(true) >= $deadline) {
                return;
            }
            $operation = 'event.prepare';
            // Persist the chosen Google ID before I/O: retrying an ambiguous insert cannot duplicate a visit.
            DB::table('google_calendar_events')->insertOrIgnore([
                'connection_id' => $connection->id, 'visit_id' => $visit->id,
                'event_id' => bin2hex(random_bytes(16)), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $mapping = DB::table('google_calendar_events')->where('connection_id', $connection->id)->where('visit_id', $visit->id)->first();
            $operation = 'event.payload';
            $payload = self::payload($visit, $connection->locale ?? 'en');
            $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
            if ($mapping->payload_hash !== $hash) {
                $operation = 'event.update';
                $response = $this->google->request($connection, 'PUT', $eventPath.'/'.$mapping->event_id.'?sendUpdates=none', $payload);
                if ($response->status() === 410) {
                    // Google retains deleted event IDs. A restored visit needs a new, durable ID.
                    $mapping->event_id = bin2hex(random_bytes(16));
                    DB::table('google_calendar_events')->where('id', $mapping->id)->update(['event_id' => $mapping->event_id, 'payload_hash' => null]);
                }
                if (in_array($response->status(), [404, 410], true)) {
                    $operation = 'event.create';
                    $response = $this->google->request($connection, 'POST', $eventPath.'?sendUpdates=none', ['id' => $mapping->event_id, ...$payload]);
                    if ($response->status() === 409) {
                        $operation = 'event.update';
                        $response = $this->google->request($connection, 'PUT', $eventPath.'/'.$mapping->event_id.'?sendUpdates=none', $payload);
                    }
                }
                if (! $response->successful()) {
                    throw GoogleCalendarFailure::fromResponse('provider_unavailable', $operation, $response);
                }
                $operation = 'event.persist';
                DB::table('google_calendar_events')->where('id', $mapping->id)->update(['payload_hash' => $hash, 'updated_at' => now()]);
            }
            $operation = 'sync.checkpoint';
            if ($visitId === null) {
                $connection->update(['sync_cursor' => $visit->id]);
            }
        }
        $operation = 'sync.complete';
        if ($visitId === null && $visits->count() < 100) {
            $connection->update(['sync_cursor' => 0, 'last_synced_at' => now(), 'last_error' => null]);
        }
    }
}
