<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Jobs\SyncGoogleCalendar;
use App\Models\GoogleCalendarConnection;
use App\Services\GoogleCalendarClient;
use App\Services\GoogleCalendarFailure;
use App\Services\GoogleCalendarSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GoogleCalendarController extends Controller
{
    public function __construct(private GoogleCalendarClient $google) {}

    private function connection(Request $request): GoogleCalendarConnection
    {
        abort_unless(GoogleCalendarSync::allowed($request->user()), 403);

        return GoogleCalendarConnection::firstOrCreate(['user_id' => $request->user()->id]);
    }

    public function show(Request $request)
    {
        $connection = $this->connection($request);

        return response()->json([
            'available' => $this->google->configured(),
            'status' => $connection->status,
            'email' => $connection->email,
            'last_synced_at' => $connection->last_synced_at?->toIso8601String(),
            'last_error' => $connection->last_error,
            'confirmation_id' => $connection->status === 'pending_confirmation' ? $connection->confirmation_id : null,
        ])->header('Cache-Control', 'no-store');
    }

    public function connect(Request $request)
    {
        abort_unless($this->google->configured(), 503, 'calendar_not_configured');
        $data = $request->validate(['language' => ['required', 'in:en,uk,pl,cs,de,fr,it,es,pt']]);
        $connection = $this->connection($request);
        $state = bin2hex(random_bytes(32));
        $this->locked($connection, function () use ($connection, $state, $data) {
            $connection->refresh()->update([
                'state_hash' => hash('sha256', $state), 'state_expires_at' => now()->addMinutes(10),
                'locale' => $data['language'],
            ]);
        });

        return response()->json(['url' => $this->google->authorizationUrl($state)])->header('Cache-Control', 'no-store');
    }

    public function callback(Request $request)
    {
        $state = (string) $request->query('state', '');
        $connection = preg_match('/^[a-f0-9]{64}$/', $state)
            ? GoogleCalendarConnection::where('state_hash', hash('sha256', $state))->first() : null;
        $locale = $connection?->locale ?? 'en';
        $success = false;
        try {
            if (! $connection || ! $this->google->configured()) {
                throw new GoogleCalendarFailure('authorization_failed');
            }
            GoogleCalendarSync::lock($connection->id)->block(3, function () use ($connection, $state, $request) {
                $connection->refresh();
                if (! hash_equals($connection->state_hash ?? '', hash('sha256', $state))
                    || ! $connection->state_expires_at?->isFuture()
                    || ! $connection->user || ! GoogleCalendarSync::allowed($connection->user)) {
                    throw new GoogleCalendarFailure('authorization_failed');
                }
                $connection->update(['state_hash' => null, 'state_expires_at' => null]);
                $code = $request->query('code');
                if (! is_string($code) || $code === '' || strlen($code) > 4096 || $request->has('error')) {
                    throw new GoogleCalendarFailure('authorization_failed');
                }
                $tokens = $this->google->exchange($code);
                $identity = $this->google->identity($tokens['access_token']);
                DB::transaction(function () use ($connection, $tokens, $identity) {
                    if ($connection->google_subject !== $identity['sub']) {
                        $connection->calendar_id = null;
                        DB::table('google_calendar_events')->where('connection_id', $connection->id)->delete();
                        $connection->last_synced_at = null;
                    }
                    $connection->fill([
                        'google_subject' => $identity['sub'], 'email' => $identity['email'],
                        'access_token' => $tokens['access_token'], 'refresh_token' => $tokens['refresh_token'],
                        'token_expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)),
                        // Confirm in the authenticated app before exporting any client data.
                        'status' => 'pending_confirmation', 'last_error' => null, 'sync_cursor' => 0,
                        'confirmation_id' => (string) \Illuminate\Support\Str::uuid(),
                    ])->save();
                });
            });
            $success = true;
        } catch (\Throwable) {
            // Do not render/log OAuth codes, tokens, or provider error bodies.
        }

        return response()->view('integrations.google-calendar-result', [
            'message' => trans('calendar.'.($success ? 'return_to_app' : 'failed'), [], $locale),
            'locale' => $locale,
        ], $success ? 200 : 400)->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function confirm(Request $request)
    {
        $data = $request->validate(['confirmation_id' => ['required', 'uuid']]);
        $connection = $this->connection($request);
        abort_unless($this->google->configured(), 503);
        $this->locked($connection, function () use ($connection, $data) {
            $connection->refresh();
            abort_unless($connection->status === 'pending_confirmation', 409);
            abort_unless(hash_equals($connection->confirmation_id ?? '', $data['confirmation_id']), 409);
            if ($connection->calendar_id) {
                $response = $this->google->request($connection, 'GET', 'calendars/'.rawurlencode($connection->calendar_id));
                if (in_array($response->status(), [404, 410], true)) {
                    $connection->update(['calendar_id' => null, 'last_synced_at' => null]);
                    DB::table('google_calendar_events')->where('connection_id', $connection->id)->delete();
                } elseif (! $response->successful()) {
                    abort(503);
                }
            }
            if (! $connection->calendar_id) {
                $response = $this->google->request($connection, 'POST', 'calendars', [
                    'summary' => 'Esticly', 'timeZone' => 'UTC',
                ]);
                abort_unless($response->successful() && $response->json('id'), 503);
                $connection->calendar_id = $response->json('id');
            }
            $connection->fill(['status' => 'connected', 'sync_cursor' => 0, 'last_error' => null, 'confirmation_id' => null])->save();
        });
        SyncGoogleCalendar::dispatch($connection->id);

        return $this->show($request);
    }

    public function sync(Request $request)
    {
        $connection = $this->connection($request);
        abort_unless($connection->status === 'connected' && $this->google->configured(), 409);
        SyncGoogleCalendar::dispatch($connection->id);

        return response()->json(['queued' => true], 202);
    }

    public function disconnect(Request $request)
    {
        $connection = $this->connection($request);
        $this->locked($connection, function () use ($connection) {
            $connection->refresh()->update([
                'status' => 'disconnected', 'access_token' => null, 'refresh_token' => null,
                'token_expires_at' => null, 'state_hash' => null, 'state_expires_at' => null,
                'last_error' => null,
                'confirmation_id' => null,
            ]);
        });

        return $this->show($request);
    }

    private function locked(GoogleCalendarConnection $connection, callable $callback): void
    {
        try {
            GoogleCalendarSync::lock($connection->id)->block(3, $callback);
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            abort(409, 'calendar_busy');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable) {
            // Never send credentials/provider payloads to the error renderer or telemetry.
            abort(503, 'calendar_unavailable');
        }
    }
}
