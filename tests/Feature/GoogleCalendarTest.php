<?php

namespace Tests\Feature;

use App\Jobs\SyncGoogleCalendar;
use App\Jobs\SyncGoogleCalendarVisit;
use App\Models\GoogleCalendarConnection;
use App\Models\User;
use App\Models\Visit;
use App\Services\GoogleCalendarClient;
use App\Services\GoogleCalendarSync;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GoogleCalendarTest extends TestCase
{
    private array $events = [];

    private array $writes = [];

    private array $deletedEventIds = [];

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
            $t->timestamps();
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
                if (isset($this->events[$id]) || isset($this->deletedEventIds[$id])) {
                    return Http::response([], 409);
                }
            }
            if ($request->method() === 'PUT' && isset($this->deletedEventIds[$id])) {
                return Http::response([], 410);
            }
            if ($request->method() === 'PUT' && ! isset($this->events[$id])) {
                return Http::response([], 404);
            }
            $this->writes[] = [$request->method(), $id, $request->data()];
            if ($request->method() === 'DELETE') {
                unset($this->events[$id]);
                $this->deletedEventIds[$id] = true;

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

    public function test_sync_is_idempotent_private_and_keeps_all_statuses_on_the_same_event(): void
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
        $this->assertSame('[Pending] Manicure - Client', $event['summary']);
        $this->assertSame('Status: Pending', $event['description']);
        $this->assertSame('3', $event['colorId']);
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
        foreach (['completed' => '10', 'cancelled' => '11', 'pending' => '3'] as $status => $color) {
            DB::table('visits')->where('id', $id)->update(['status' => $status]);
            $sync->run($connection->id);
            $this->assertCount(1, $this->events);
            $this->assertSame($eventId, array_key_first($this->events));
            $event = $this->events[$eventId];
            $this->assertSame('['.ucfirst($status).'] Manicure - Client', $event['summary']);
            $this->assertSame($color, $event['colorId']);
            $this->assertSame('confirmed', $event['status']);
            $this->assertSame($status === 'cancelled' ? 'transparent' : 'opaque', $event['transparency']);
            $writes = count($this->writes);
            $sync->run($connection->id);
            $this->assertCount($writes, $this->writes);
        }
        $this->assertSame(1, DB::table('google_calendar_events')->count());
        $this->assertNotNull($connection->fresh()->last_synced_at);
    }

    public function test_statuses_are_localized_for_each_connections_locale(): void
    {
        $user = $this->user();
        $connection = $this->connection($user);
        $id = $this->visit($user);
        $labels = [
            'en' => ['Pending', 'Completed', 'Cancelled'],
            'uk' => ['В очікуванні', 'Виконано', 'Скасовано'],
            'pl' => ['Oczekują', 'Zakończone', 'Odwołane'],
            'cs' => ['Čeká', 'Dokončeno', 'Zrušeno'],
            'de' => ['Ausstehend', 'Abgeschlossen', 'Storniert'],
            'fr' => ['En attente', 'Terminé', 'Annulé'],
            'it' => ['In attesa', 'Completato', 'Annullato'],
            'es' => ['Pendiente', 'Completado', 'Cancelado'],
            'pt' => ['Pendente', 'Concluído', 'Cancelado'],
        ];
        foreach ($labels as $locale => $statuses) {
            $connection->update(['locale' => $locale]);
            foreach (array_combine(['pending', 'completed', 'cancelled'], $statuses) as $status => $label) {
                DB::table('visits')->where('id', $id)->update(['status' => $status]);
                app(GoogleCalendarSync::class)->run($connection->id);
                $this->assertCount(1, $this->events);
                $event = reset($this->events);
                $this->assertSame('['.$label.'] Manicure - Client', $event['summary']);
                $this->assertSame(trans('calendar.status_label', [], $locale).': '.$label, $event['description']);
            }
        }
        $visit = Visit::findOrFail($id);
        $visit->status = null;
        $this->assertSame('[Pending] Manicure - Client', GoogleCalendarSync::payload($visit, 'unsupported')['summary']);
    }

    public function test_legacy_events_update_in_place_and_previously_removed_cancellations_return(): void
    {
        $user = $this->user();
        $connection = $this->connection($user, ['locale' => 'uk']);
        $id = $this->visit($user, ['status' => 'completed']);
        $legacy = ['summary' => 'Manicure - Client'];
        $this->events['legacy-event'] = $legacy;
        DB::table('google_calendar_events')->insert([
            'connection_id' => $connection->id, 'visit_id' => $id, 'event_id' => 'legacy-event',
            'payload_hash' => hash('sha256', json_encode($legacy)),
        ]);
        $cancelled = $this->visit($user, ['status' => 'cancelled']);
        app(GoogleCalendarSync::class)->run($connection->id);
        $this->assertCount(2, $this->events);
        $this->assertSame('[Виконано] Manicure - Client', $this->events['legacy-event']['summary']);
        $mapping = DB::table('google_calendar_events')->where('visit_id', $cancelled)->first();
        $this->assertSame('[Скасовано] Manicure - Client', $this->events[$mapping->event_id]['summary']);
        app(GoogleCalendarSync::class)->run($connection->id);
        $this->assertCount(2, $this->writes);
    }

    public function test_cancelled_copy_is_recreated_when_google_retains_a_deleted_event_id(): void
    {
        $user = $this->user();
        $connection = $this->connection($user);
        $id = $this->visit($user);
        $sync = app(GoogleCalendarSync::class);
        $sync->run($connection->id);
        $oldEventId = array_key_first($this->events);
        unset($this->events[$oldEventId]);
        $this->deletedEventIds[$oldEventId] = true;
        DB::table('visits')->where('id', $id)->update(['status' => 'cancelled']);
        $sync->run($connection->id);
        $this->assertCount(1, $this->events);
        $this->assertNotSame($oldEventId, array_key_first($this->events));
        $this->assertSame('11', reset($this->events)['colorId']);
        $sync->run($connection->id);
        $this->assertCount(1, $this->events);
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
        $this->assertCount(101, $this->events);
        $mapping = DB::table('google_calendar_events')->where('visit_id', 1)->first();
        $this->assertSame('11', $this->events[$mapping->event_id]['colorId']);
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

    public function test_visit_edits_queue_only_after_commit_and_rollback_does_not_export(): void
    {
        $owner = $this->user();
        $connection = $this->connection($owner);
        $this->connection($this->user());
        $this->connection($this->user(['organization_id' => $owner->id]), ['status' => 'disconnected']);
        $id = $this->visit($owner);
        DB::beginTransaction();
        Visit::findOrFail($id)->update(['status' => 'completed']);
        Queue::assertNothingPushed();
        DB::rollBack();
        Queue::assertNothingPushed();
        $this->assertSame('pending', Visit::findOrFail($id)->status);

        DB::beginTransaction();
        Visit::findOrFail($id)->update(['status' => 'cancelled']);
        Queue::assertNothingPushed();
        DB::commit();
        Queue::assertPushed(SyncGoogleCalendarVisit::class, 1);
        Queue::assertPushed(SyncGoogleCalendarVisit::class,
            fn ($job) => $job->connectionId === $connection->id && $job->visitId === $id);
        Http::assertNothingSent();
    }

    public function test_create_delete_and_repeated_edits_queue_but_private_fields_do_not(): void
    {
        $owner = $this->user();
        $this->connection($owner);
        $visit = Visit::create([
            'user_id' => $owner->id, 'service_id' => 1, 'client_name' => 'Client',
            'status' => 'pending', 'starts_at' => '2026-10-25 09:00:00', 'ends_at' => '2026-10-25 10:00:00',
        ]);
        Queue::assertPushed(SyncGoogleCalendarVisit::class, 1);
        $visit->update(['comment' => 'Private note']);
        $visit->save();
        Queue::assertPushed(SyncGoogleCalendarVisit::class, 1);
        $visit->update(['status' => 'completed']);
        $visit->update(['status' => 'cancelled']);
        $visit->update(['starts_at' => '2026-10-25 11:00:00', 'ends_at' => '2026-10-25 12:00:00']);
        Queue::assertPushed(SyncGoogleCalendarVisit::class, 4);
        $visit->delete();
        Queue::assertPushed(SyncGoogleCalendarVisit::class, 5);
        Http::assertNothingSent();
    }

    public function test_targeted_sync_ignores_full_sync_cursor_and_reads_latest_state(): void
    {
        $owner = $this->user();
        $connection = $this->connection($owner, ['sync_cursor' => 10000]);
        $id = $this->visit($owner);
        $this->visit($owner);
        $job = new SyncGoogleCalendarVisit($connection->id, $id);
        $visit = Visit::findOrFail($id);
        $visit->update(['status' => 'completed']);
        $visit->update(['status' => 'cancelled']);
        $job->handle(app(GoogleCalendarSync::class));
        $this->assertCount(1, $this->events);
        $this->assertSame('11', reset($this->events)['colorId']);
        $this->assertSame(10000, $connection->fresh()->sync_cursor);
        $this->assertNull($connection->fresh()->last_synced_at);
        $job->handle(app(GoogleCalendarSync::class));
        $this->assertCount(1, $this->writes);
    }

    public function test_targeted_job_retries_lock_contention_and_provider_failure(): void
    {
        $owner = $this->user();
        $connection = $this->connection($owner);
        $id = $this->visit($owner);
        $job = (new SyncGoogleCalendarVisit($connection->id, $id))->withFakeQueueInteractions();
        $sync = app(GoogleCalendarSync::class);
        $lock = GoogleCalendarSync::lock($connection->id);
        $this->assertTrue($lock->get());
        try {
            $job->handle($sync);
            $job->assertReleased(5);
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
        $this->fail = true;
        $job = (new SyncGoogleCalendarVisit($connection->id, $id))->withFakeQueueInteractions();
        $job->handle($sync);
        $job->assertReleased(5);
        $this->fail = false;
        $job = (new SyncGoogleCalendarVisit($connection->id, $id))->withFakeQueueInteractions();
        $job->handle($sync);
        $job->assertNotReleased();
        $this->assertCount(1, $this->events);
    }

    public function test_reassignment_queues_both_owners_and_removes_only_out_of_scope_copy(): void
    {
        $old = $this->user();
        $new = $this->user();
        $oldConnection = $this->connection($old);
        $newConnection = $this->connection($new);
        $unrelated = $this->connection($this->user());
        $id = $this->visit($old);
        $other = $this->visit($old);
        $sync = app(GoogleCalendarSync::class);
        $sync->run($oldConnection->id);
        Visit::findOrFail($id)->update(['user_id' => $new->id]);
        Queue::assertPushed(SyncGoogleCalendarVisit::class, 2);
        foreach ([$oldConnection, $newConnection] as $connection) {
            Queue::assertPushed(SyncGoogleCalendarVisit::class, fn ($job) => $job->connectionId === $connection->id);
            $this->assertTrue($sync->run($connection->id, $id));
        }
        $this->assertTrue($sync->run($unrelated->id, $id));
        $this->assertCount(2, $this->events);
        $this->assertSame(0, DB::table('google_calendar_events')->where('connection_id', $oldConnection->id)->where('visit_id', $id)->count());
        $this->assertSame(1, DB::table('google_calendar_events')->where('connection_id', $oldConnection->id)->where('visit_id', $other)->count());
        Visit::findOrFail($id)->delete();
        $this->assertTrue($sync->run($newConnection->id, $id));
        $this->assertCount(1, $this->events);
    }

    public function test_targeted_staff_sync_rechecks_assignment_and_access(): void
    {
        $owner = $this->user();
        DB::table('staff')->insert(['id' => 1, 'user_id' => $owner->id, 'is_active' => true, 'permissions' => '{"base_access":true}']);
        $staff = $this->user(['organization_id' => $owner->id, 'staff_id' => 1]);
        $connection = $this->connection($staff);
        $id = $this->visit($owner, ['staff_id' => 1]);
        $sync = app(GoogleCalendarSync::class);
        $sync->run($connection->id, $id);
        $this->assertCount(1, $this->events);
        Visit::findOrFail($id)->update(['staff_id' => 2]);
        Queue::assertPushed(SyncGoogleCalendarVisit::class, fn ($job) => $job->connectionId === $connection->id);
        $sync->run($connection->id, $id);
        $this->assertCount(0, $this->events);
        Visit::findOrFail($id)->update(['staff_id' => 1]);
        DB::table('staff')->where('id', 1)->update(['is_active' => false]);
        $this->assertTrue($sync->run($connection->id, $id));
        $this->assertCount(0, $this->events);
        $this->assertSame('access_revoked', $connection->fresh()->status);
    }

    public function test_targeted_job_stops_after_disconnect(): void
    {
        $owner = $this->user();
        $connection = $this->connection($owner);
        $id = $this->visit($owner);
        $job = (new SyncGoogleCalendarVisit($connection->id, $id))->withFakeQueueInteractions();
        $connection->update(['status' => 'disconnected']);
        $job->handle(app(GoogleCalendarSync::class));
        $job->assertNotReleased();
        Http::assertNothingSent();
    }

    public function test_queue_outage_does_not_fail_a_committed_visit_change(): void
    {
        $owner = $this->user();
        $this->connection($owner);
        $id = $this->visit($owner);
        Queue::shouldReceive('connection')->once()->andReturnSelf();
        Queue::shouldReceive('push')->once()->andThrow(new \RuntimeException('queue unavailable'));
        Log::shouldReceive('warning')->once()->with('google_calendar_dispatch_failed', ['visit_id' => $id]);
        DB::transaction(fn () => Visit::findOrFail($id)->update(['status' => 'completed']));
        $this->assertSame('completed', Visit::findOrFail($id)->status);
        Http::assertNothingSent();
    }

    public function test_disabled_integration_does_not_queue_visit_changes(): void
    {
        $owner = $this->user();
        $this->connection($owner);
        $id = $this->visit($owner);
        config(['google_calendar.client_secret' => null]);
        Visit::findOrFail($id)->update(['status' => 'completed']);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }
}
