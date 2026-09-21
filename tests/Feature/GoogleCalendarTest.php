<?php

namespace Tests\Feature;

use App\Jobs\SyncGoogleCalendar;
use App\Models\GoogleCalendarConnection;
use App\Models\User;
use App\Services\GoogleCalendarClient;
use App\Services\GoogleCalendarSync;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GoogleCalendarTest extends TestCase
{
    private array $events = [];

    private array $writes = [];

    private bool $fail = false;

    private bool $invalidGrant = false;

    private bool $loseInsertReply = false;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'google_calendar.client_id' => 'test-client',
            'google_calendar.client_secret' => 'test-secret',
            'google_calendar.redirect_uri' => 'https://esticly.test/integrations/google-calendar/callback',
            'google_calendar.cache_store' => 'array',
        ]);
        Cache::flush();
        Queue::fake();
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('organization_id')->nullable();
            $t->unsignedBigInteger('staff_id')->nullable();
            $t->timestamps();
        });
        Schema::create('staff', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->boolean('is_active');
            $t->json('permissions');
            $t->softDeletes();
        });
        Schema::create('services', function (Blueprint $t) {
            $t->id();
            $t->string('name');
        });
        Schema::create('visits', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('staff_id')->nullable();
            $t->unsignedBigInteger('service_id')->nullable();
            $t->string('client_name')->nullable();
            $t->string('client_phone')->nullable();
            $t->string('comment')->nullable();
            $t->timestamp('starts_at');
            $t->timestamp('ends_at');
            $t->string('status')->nullable();
        });
        (require database_path('migrations/2026_09_21_000001_create_google_calendar_connections.php'))->up();
        DB::table('services')->insert(['id' => 1, 'name' => 'Manicure']);
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                if ($this->invalidGrant) {
                    return Http::response(['error' => 'invalid_grant'], 400);
                }

                return Http::response(['access_token' => 'secret-access', 'refresh_token' => 'secret-refresh', 'expires_in' => 3600, 'scope' => GoogleCalendarClient::SCOPE]);
            }
            if ($request->url() === 'https://openidconnect.googleapis.com/v1/userinfo') {
                return Http::response(['sub' => 'google-person', 'email' => 'person@example.test', 'email_verified' => true]);
            }
            if ($this->fail) {
                return Http::response([], 503);
            }
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/calendars')) {
                return Http::response(['id' => 'calendar@test']);
            }
            if (! str_contains($request->url(), '/events')) {
                return Http::response(['id' => 'calendar@test']);
            }
            $id = basename(parse_url($request->url(), PHP_URL_PATH));
            if ($request->method() === 'POST') {
                $id = $request['id'];
                if (isset($this->events[$id])) {
                    return Http::response([], 409);
                }
            }
            if ($request->method() === 'PUT' && ! isset($this->events[$id])) {
                return Http::response([], 404);
            }
            $this->writes[] = [$request->method(), $id, $request->data()];
            if ($request->method() === 'DELETE') {
                unset($this->events[$id]);

                return Http::response('', 204);
            }
            $this->events[$id] = $request->data();
            if ($this->loseInsertReply && $request->method() === 'POST') {
                $this->loseInsertReply = false;

                return Http::response([], 503);
            }

            return Http::response(['id' => $id]);
        });
    }

    private function user(array $attributes = []): User
    {
        $user = new User($attributes);
        $user->saveQuietly();

        return $user;
    }

    private function connection(User $user, array $attributes = []): GoogleCalendarConnection
    {
        return GoogleCalendarConnection::create([
            'user_id' => $user->id, 'status' => 'connected', 'access_token' => 'secret-access',
            'refresh_token' => 'secret-refresh', 'token_expires_at' => now()->addHour(),
            'calendar_id' => 'calendar@test', ...$attributes,
        ]);
    }

    private function visit(User $user, array $attributes = []): int
    {
        return DB::table('visits')->insertGetId([
            'user_id' => $user->id, 'service_id' => 1, 'client_name' => 'Client',
            'client_phone' => 'private-phone', 'comment' => 'private-note', 'status' => 'pending',
            'starts_at' => '2026-10-25 00:30:00', 'ends_at' => '2026-10-25 01:30:00', ...$attributes,
        ]);
    }

    public function test_oauth_is_bound_to_user_single_use_and_requires_in_app_confirmation(): void
    {
        $user = $this->user();
        $this->actingAs($user);
        $url = $this->postJson('/api/mobile/integrations/google-calendar/connect', ['language' => 'uk'])->assertOk()->json('url');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertStringContainsString(GoogleCalendarClient::SCOPE, $query['scope']);
        $this->assertStringNotContainsString('/auth/calendar ', $query['scope']);
        $connection = GoogleCalendarConnection::first();
        $this->assertNotSame($query['state'], $connection->state_hash);
        $callback = '/integrations/google-calendar/callback?state='.$query['state'].'&code=test';
        $this->get($callback)->assertOk()->assertSee('Поверніться');
        $this->assertSame('pending_confirmation', $connection->fresh()->status);
        Queue::assertNothingPushed();
        $this->get($callback)->assertStatus(400);
        $status = $this->getJson('/api/mobile/integrations/google-calendar')->assertOk()->json();
        $this->assertArrayNotHasKey('access_token', $status);
        $this->postJson('/api/mobile/integrations/google-calendar/confirm', ['confirmation_id' => (string) \Illuminate\Support\Str::uuid()])->assertStatus(409);
        $this->postJson('/api/mobile/integrations/google-calendar/confirm', ['confirmation_id' => $status['confirmation_id']])->assertOk();
        Queue::assertPushed(SyncGoogleCalendar::class, fn ($job) => $job->connectionId === $connection->id);
        $raw = DB::table('google_calendar_connections')->first();
        $this->assertStringNotContainsString('secret-refresh', $raw->refresh_token);
        $this->assertStringNotContainsString('secret-access', $raw->access_token);
    }

    public function test_unknown_expired_and_cancelled_oauth_do_not_export(): void
    {
        $this->get('/integrations/google-calendar/callback?state=invalid&code=test')->assertStatus(400);
        $user = $this->user();
        $connection = $this->connection($user, ['state_hash' => hash('sha256', str_repeat('a', 64)), 'state_expires_at' => now()->subMinute()]);
        $this->get('/integrations/google-calendar/callback?state='.str_repeat('a', 64).'&code=test')->assertStatus(400);
        $connection->update(['state_expires_at' => now()->addMinute()]);
        $this->get('/integrations/google-calendar/callback?state='.str_repeat('a', 64).'&error=access_denied')->assertStatus(400);
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_sync_is_idempotent_private_and_updates_time_then_removes_cancelled_visit(): void
    {
        $user = $this->user();
        $connection = $this->connection($user);
        $id = $this->visit($user);
        $this->visit($this->user());
        $sync = app(GoogleCalendarSync::class);
        $sync->run($connection->id);
        $this->assertCount(1, $this->events);
        $eventId = array_key_first($this->events);
        $event = $this->events[$eventId];
        $this->assertSame('Manicure - Client', $event['summary']);
        $this->assertSame('2026-10-25T00:30:00+00:00', $event['start']['dateTime']);
        $this->assertSame('2026-10-25T01:30:00+00:00', $event['end']['dateTime']);
        $this->assertStringNotContainsString('private-', json_encode($event));
        $this->assertSame('private', $event['visibility']);
        $sync->run($connection->id);
        $this->assertCount(1, $this->writes);
        DB::table('visits')->where('id', $id)->update(['starts_at' => '2026-10-26 09:00:00', 'ends_at' => '2026-10-26 10:00:00']);
        $sync->run($connection->id);
        $this->assertCount(1, $this->events);
        $this->assertSame('2026-10-26T09:00:00+00:00', $this->events[$eventId]['start']['dateTime']);
        DB::table('visits')->where('id', $id)->update(['status' => 'cancelled']);
        $sync->run($connection->id);
        $this->assertCount(0, $this->events);
        $this->assertSame(0, DB::table('google_calendar_events')->count());
        $this->assertNotNull($connection->fresh()->last_synced_at);
    }

    public function test_staff_only_syncs_assigned_visits_and_stops_when_access_is_revoked(): void
    {
        $owner = $this->user();
        DB::table('staff')->insert(['id' => 1, 'user_id' => $owner->id, 'is_active' => true, 'permissions' => '{"base_access":true}']);
        $staff = $this->user(['organization_id' => $owner->id, 'staff_id' => 1]);
        $connection = $this->connection($staff);
        $own = $this->visit($owner, ['staff_id' => 1]);
        $this->visit($owner, ['staff_id' => 2]);
        $sync = app(GoogleCalendarSync::class);
        $sync->run($connection->id);
        $this->assertCount(1, $this->events);
        DB::table('visits')->where('id', $own)->update(['staff_id' => 2]);
        $sync->run($connection->id);
        $this->assertCount(0, $this->events);
        DB::table('staff')->where('id', 1)->update(['is_active' => false]);
        $sync->run($connection->id);
        $this->assertSame('access_revoked', $connection->fresh()->status);
        $this->assertNull($connection->fresh()->refresh_token);
        $this->actingAs($staff)->getJson('/api/mobile/integrations/google-calendar')->assertForbidden();
    }

    public function test_failure_keeps_pending_work_and_refresh_revocation_requires_reconnect(): void
    {
        $user = $this->user();
        $connection = $this->connection($user);
        $this->visit($user);
        $this->fail = true;
        $sync = app(GoogleCalendarSync::class);
        $sync->run($connection->id);
        $this->assertNull($connection->fresh()->last_synced_at);
        $this->assertSame('provider_unavailable', $connection->fresh()->last_error);
        $this->fail = false;
        $sync->run($connection->id);
        $this->assertCount(1, $this->events);
        $connection->refresh()->update(['token_expires_at' => now()->subMinute()]);
        $this->invalidGrant = true;
        $sync->run($connection->id);
        $this->assertSame('needs_reconnect', $connection->fresh()->status);
    }

    public function test_disconnect_erases_tokens_invalidates_oauth_and_preserves_google_copy(): void
    {
        $user = $this->user();
        $connection = $this->connection($user);
        $this->visit($user);
        app(GoogleCalendarSync::class)->run($connection->id);
        $this->actingAs($user)->deleteJson('/api/mobile/integrations/google-calendar')->assertOk();
        $this->assertNull($connection->fresh()->refresh_token);
        $this->assertNull($connection->fresh()->access_token);
        $this->assertSame('disconnected', $connection->fresh()->status);
        app(GoogleCalendarSync::class)->run($connection->id);
        $this->assertCount(1, $this->events);
    }

    public function test_pending_connection_and_lock_do_not_sync_and_users_are_isolated(): void
    {
        $user = $this->user();
        $connection = $this->connection($user, ['status' => 'pending_confirmation']);
        $this->visit($user);
        app(GoogleCalendarSync::class)->run($connection->id);
        Http::assertNothingSent();
        $connection->update(['status' => 'connected']);
        $lock = GoogleCalendarSync::lock($connection->id);
        $lock->get();
        try {
            app(GoogleCalendarSync::class)->run($connection->id);
        } finally {
            $lock->release();
        }
        Http::assertNothingSent();
        $this->actingAs($this->user())->getJson('/api/mobile/integrations/google-calendar')->assertJson(['status' => 'disconnected']);
    }

    public function test_hard_deleted_visits_are_removed_and_deleted_users_drop_credentials(): void
    {
        $user = $this->user();
        $connection = $this->connection($user);
        $id = $this->visit($user);
        $sync = app(GoogleCalendarSync::class);
        $sync->run($connection->id);
        DB::table('visits')->where('id', $id)->delete();
        $sync->run($connection->id);
        $this->assertCount(0, $this->events);
        DB::table('users')->where('id', $user->id)->delete();
        $this->assertSame(0, GoogleCalendarConnection::count());
    }

    public function test_missing_configuration_does_not_start_oauth(): void
    {
        config(['google_calendar.client_secret' => null]);
        $this->actingAs($this->user())->postJson('/api/mobile/integrations/google-calendar/connect', ['language' => 'en'])->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_lost_insert_response_does_not_duplicate_event_on_retry(): void
    {
        $user = $this->user();
        $connection = $this->connection($user);
        $this->visit($user);
        $this->loseInsertReply = true;
        $sync = app(GoogleCalendarSync::class);
        $sync->run($connection->id);
        $this->assertCount(1, $this->events);
        $this->assertNull(DB::table('google_calendar_events')->first()->payload_hash);
        $sync->run($connection->id);
        $this->assertCount(1, $this->events);
        $this->assertNotNull(DB::table('google_calendar_events')->first()->payload_hash);
    }

    public function test_sync_resumes_large_calendars_and_recreates_restored_visits(): void
    {
        $user = $this->user();
        $connection = $this->connection($user);
        for ($i = 0; $i < 101; $i++) {
            $this->visit($user);
        }
        $sync = app(GoogleCalendarSync::class);
        $sync->run($connection->id);
        $this->assertCount(100, $this->events);
        $this->assertNull($connection->fresh()->last_synced_at);
        $sync->run($connection->id);
        $this->assertCount(101, $this->events);
        $this->assertSame(0, $connection->fresh()->sync_cursor);
        DB::table('visits')->where('id', 1)->update(['status' => 'cancelled']);
        $sync->run($connection->id);
        $this->assertCount(100, $this->events);
        DB::table('visits')->where('id', 1)->update(['status' => 'pending']);
        $sync->run($connection->id);
        $sync->run($connection->id);
        $this->assertCount(101, $this->events);
    }

    public function test_scheduler_queues_only_active_connections(): void
    {
        $active = $this->connection($this->user());
        $this->connection($this->user(), ['status' => 'disconnected']);
        $this->connection($this->user(), ['status' => 'pending_confirmation']);
        $this->artisan('calendar:sync')->assertSuccessful();
        Queue::assertPushed(SyncGoogleCalendar::class, 1);
        Queue::assertPushed(SyncGoogleCalendar::class, fn ($job) => $job->connectionId === $active->id);
    }
}
