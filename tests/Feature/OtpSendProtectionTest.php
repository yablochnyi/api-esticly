<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Mobile\AuthController;
use App\Services\OtpSendGuard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;
use Twilio\Http\Client as HttpClient;
use Twilio\Http\Response;
use Twilio\Rest\Client;

class OtpSendProtectionTest extends TestCase
{
    private array $providerCalls = [];

    private array $providerResponses = [];

    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\Schema::dropIfExists('staff');
        \Illuminate\Support\Facades\Schema::dropIfExists('users');
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Schema::create('users', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->string('phone');
            $table->string('phone_hash')->nullable();
        });
        \Illuminate\Support\Facades\Schema::create('staff', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->string('phone');
            $table->string('phone_hash')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('permissions')->nullable();
            $table->softDeletes();
        });
        config([
            'app.url' => 'http://localhost',
            'app_check.enforce' => false,
            'otp.cache_store' => 'array',
            'otp.ip_hourly_limit' => 5,
            'otp.phone_hourly_limit' => 5,
            'otp.global_daily_limit' => 20,
            'otp.daily_timezone' => 'Europe/Warsaw',
            'otp.sending_enabled' => true,
            'otp.paused_allowed_phone_hashes' => [],
            'services.twilio.verify_service_sid' => 'VA_test',
            'services.twilio.from' => '+12025550100',
            'services.twilio.messaging_service_sid' => null,
        ]);
        Cache::store('array')->flush();
        Log::spy();

        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('request')->andReturnUsing(function ($method, $url) {
            $this->providerCalls[] = $url;

            return array_shift($this->providerResponses) ?? new Response(201, '{"sid":"VE_test","status":"pending"}');
        });
        $client = new Client('AC_test', 'test-token', 'AC_test', null, $http);
        $controller = new class($client) extends AuthController
        {
            public function __construct(private Client $client) {}

            protected function twilioClient(): ?Client
            {
                return $this->client;
            }
        };
        $this->app->instance(AuthController::class, $controller);
    }

    public function test_sixth_phone_from_same_ip_is_blocked_before_provider(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->send('+1202555010'.$i)->assertOk()->assertJson(['ok' => true, 'channel' => 'twilio']);
        }
        $this->send('+12025550106')->assertStatus(429)->assertJson(['ok' => false, 'code' => 'otp_rate_limited'])
            ->assertHeader('Retry-After');
        $this->assertCount(5, $this->providerCalls);
        $this->send('+12025550106', '192.0.2.2')->assertOk();
        $this->assertCount(6, $this->providerCalls);
    }

    public function test_global_daily_limit_blocks_the_twenty_first_distinct_phone_and_ip(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->send('+1202555'.sprintf('%04d', $i), '192.0.2.'.$i)->assertOk();
        }
        $this->send('+12025550199', '192.0.2.99')->assertStatus(429)
            ->assertJson(['code' => 'otp_rate_limited'])->assertHeader('Retry-After');
        $this->assertCount(20, $this->providerCalls);
        $this->assertSame(20, Cache::get('otp-send:global-day:'.now('Europe/Warsaw')->format('Y-m-d')));
    }

    public function test_daily_limit_counts_provider_failures_and_both_channels(): void
    {
        config(['otp.global_daily_limit' => 2]);
        $this->providerResponses[] = new Response(401, '{"code":20003,"message":"denied"}');
        $this->send('+12025550101')->assertStatus(503);
        config(['services.twilio.verify_service_sid' => null]);
        $this->send('+12025550102', '192.0.2.2')->assertOk();
        $this->send('+12025550103', '192.0.2.3')->assertStatus(429);
        $this->assertCount(2, $this->providerCalls);
        $this->assertStringContainsString('verify.twilio.com', $this->providerCalls[0]);
        $this->assertStringContainsString('/Messages.json', $this->providerCalls[1]);
    }

    public function test_daily_budget_resets_at_warsaw_midnight_not_utc_midnight(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-09-30 21:59:59', 'UTC'));
        config(['otp.global_daily_limit' => 1]);
        $this->send('+12025550101')->assertOk();
        $this->send('+12025550102', '192.0.2.2')->assertStatus(429)->assertHeader('Retry-After', '1');
        $this->travel(1)->seconds();
        $this->send('+12025550102', '192.0.2.2')->assertOk();
        $this->assertSame(1, Cache::get('otp-send:global-day:2026-09-30'));
        $this->assertSame(1, Cache::get('otp-send:global-day:2026-10-01'));
        $this->assertCount(2, $this->providerCalls);
    }

    public function test_daily_retry_after_respects_the_long_day_at_dst_end(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-24 22:00:00', 'UTC'));
        config(['otp.global_daily_limit' => 0]);
        $this->send('+12025550101')->assertStatus(429)->assertHeader('Retry-After', '90000');
        $this->assertCount(0, $this->providerCalls);
    }

    public function test_backfilled_daily_usage_and_pause_exceptions_cannot_bypass_cap(): void
    {
        Cache::put('otp-send:global-day:'.now('Europe/Warsaw')->format('Y-m-d'), 80, 86400);
        config(['otp.sending_enabled' => false,
            'otp.paused_allowed_phone_hashes' => [hash('sha256', '+12025550101')]]);
        $this->send('+12025550101')->assertStatus(429);
        $this->assertCount(0, $this->providerCalls);
    }

    public function test_local_rejections_do_not_consume_daily_budget(): void
    {
        config(['otp.global_daily_limit' => 2, 'otp.ip_hourly_limit' => 1]);
        $this->send('+12025550101')->assertOk();
        $this->send('+12025550101', '192.0.2.2')->assertStatus(429);
        $this->send('+12025550102')->assertStatus(429);
        $this->send('invalid')->assertStatus(422);
        $this->send('+12025550102', '192.0.2.2')->assertOk();
        $this->assertCount(2, $this->providerCalls);
    }

    public function test_corrupt_daily_counter_and_contended_global_lock_fail_closed(): void
    {
        $key = 'otp-send:global-day:'.now('Europe/Warsaw')->format('Y-m-d');
        Cache::put($key, 'invalid', 3600);
        $this->send('+12025550101')->assertStatus(503);
        Cache::forget($key);
        $lock = Cache::store('array')->lock('otp-send:global-day:lock', 10);
        $this->assertTrue($lock->get());
        try {
            $this->send('+12025550102', '192.0.2.2')->assertStatus(503);
        } finally {
            $lock->release();
        }
        $this->assertCount(0, $this->providerCalls);
    }

    public function test_concurrent_distinct_ips_share_the_last_daily_slot(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl is required for the multiprocess cache-lock test');
        }
        $path = sys_get_temp_dir().'/otp-daily-test-'.bin2hex(random_bytes(8));
        config([
            'cache.stores.otp_daily_test' => ['driver' => 'file', 'path' => $path, 'lock_path' => $path],
            'otp.cache_store' => 'otp_daily_test',
        ]);
        Cache::store('otp_daily_test')->put('otp-send:global-day:'.now('Europe/Warsaw')->format('Y-m-d'), 19, 86400);
        $children = [];
        $results = [];
        try {
            for ($i = 1; $i <= 10; $i++) {
                $pid = pcntl_fork();
                if ($pid === -1) {
                    $this->fail('Could not fork cache-lock test worker');
                }
                if ($pid === 0) {
                    $decision = (new OtpSendGuard)->reserve('192.0.2.'.$i, '+1202555'.sprintf('%04d', $i));
                    exit($decision === null ? 0 : ($decision['status'] === 429 ? 2 : 3));
                }
                $children[] = $pid;
            }
        } finally {
            foreach ($children as $pid) {
                pcntl_waitpid($pid, $status);
                $results[] = pcntl_wifexited($status) ? pcntl_wexitstatus($status) : -1;
            }
            File::deleteDirectory($path);
        }
        $this->assertCount(10, $results);
        $this->assertCount(1, array_filter($results, fn ($code) => $code === 0));
        $this->assertSame([], array_values(array_diff($results, [0, 2, 3])));
    }

    public function test_paused_sends_never_contact_either_provider_channel(): void
    {
        config(['otp.sending_enabled' => false]);
        $this->send('+12025550101')->assertStatus(503)
            ->assertJson(['ok' => false, 'code' => 'otp_unavailable'])
            ->assertHeader('Retry-After', '3600');
        config(['services.twilio.verify_service_sid' => null]);
        $this->send('+12025550102', '192.0.2.2')->assertStatus(503);
        $this->assertCount(0, $this->providerCalls);
        $this->assertNull(Cache::get('otp:12025550102'));
        config(['otp.sending_enabled' => true]);
        $this->assertNull((new OtpSendGuard)->reserve('192.0.2.1', '+12025550101'));
    }

    public function test_review_numbers_remain_available_during_paid_send_pause(): void
    {
        $env = \Illuminate\Support\Env::getRepository();
        $previous = $env->get('REVIEW_OTP_PHONES');
        $env->set('REVIEW_OTP_PHONES', '+12025550101,+12025550102');
        config(['otp.sending_enabled' => false]);
        try {
            foreach (['+12025550101', '+1 (202) 555-0102'] as $phone) {
                $this->send($phone)->assertOk()->assertJson(['ok' => true, 'channel' => 'review']);
            }
            $this->send('+12025550103')->assertStatus(503);
            $this->assertCount(0, $this->providerCalls);
        } finally {
            if ($previous === null) {
                $env->clear('REVIEW_OTP_PHONES');
            } else {
                $env->set('REVIEW_OTP_PHONES', $previous);
            }
        }
    }

    public function test_allowlisted_phone_during_pause_uses_real_sms_and_keeps_cooldown(): void
    {
        config(['otp.sending_enabled' => false,
            'otp.paused_allowed_phone_hashes' => [hash('sha256', '+12025550101')]]);
        $this->send('+1 (202) 555-0101')->assertOk()->assertJson(['channel' => 'twilio']);
        $this->send('+12025550101', '192.0.2.2')->assertStatus(429);
        $this->send('+12025550102')->assertStatus(503);
        $this->assertCount(1, $this->providerCalls);
        $this->assertNull(Cache::get('otp:12025550101'));
    }

    public function test_pause_exception_keeps_ip_limits_and_provider_failure_protection(): void
    {
        config(['otp.sending_enabled' => false,
            'otp.paused_allowed_phone_hashes' => [hash('sha256', '+12025550101')]]);
        for ($i = 0; $i < 5; $i++) {
            $this->providerResponses[] = new Response(401, '{"code":20003,"message":"not active"}');
            $this->send('+12025550101')->assertStatus(503);
            $this->travel(61)->seconds();
        }
        $this->send('+12025550101')->assertStatus(429);
        $this->assertCount(5, $this->providerCalls);
    }

    public function test_allowance_is_rolling_not_reset_at_the_clock_hour(): void
    {
        $this->travelTo(now()->startOfHour()->addMinutes(59));
        for ($i = 1; $i <= 5; $i++) {
            $this->send('+1202555010'.$i)->assertOk();
        }
        $this->travel(2)->minutes();
        $this->send('+12025550106')->assertStatus(429);
        $this->travel(59)->minutes();
        $this->send('+12025550106')->assertOk();
        $this->assertCount(6, $this->providerCalls);
    }

    public function test_only_expired_reservations_are_released(): void
    {
        $guard = new OtpSendGuard;
        $this->assertNull($guard->reserve('192.0.2.1', '+12025550101'));
        $this->travel(30)->minutes();
        for ($i = 0; $i < 4; $i++) {
            $this->assertNull($guard->reserve('192.0.2.1', '+1202555011'.$i));
        }
        $this->travel(31)->minutes();
        $this->assertNull($guard->reserve('192.0.2.1', '+12025550101'));
        $denied = $guard->reserve('192.0.2.1', '+12025550102');
        $this->assertSame(429, $denied['status']);
        $this->assertSame(29 * 60, $denied['retry_after']);
    }

    public function test_verify_fraud_rejection_never_falls_back_to_messages(): void
    {
        $this->providerResponses[] = new Response(403, '{"code":60410,"message":"blocked phone +12025550101"}');
        $this->send('+12025550101')->assertStatus(503)->assertJson(['ok' => false, 'code' => 'otp_unavailable']);
        $this->assertCount(1, $this->providerCalls);
        $this->assertStringContainsString('verify.twilio.com', $this->providerCalls[0]);
        $this->assertNull(Cache::get('otp:12025550101'));
        Log::shouldHaveReceived('warning')->withArgs(function ($event, $context) {
            return $event === 'otp_send_result' && $context['provider_code'] === 60410
                && $context['ip'] === '192.0.2.1' && isset($context['phone_fingerprint'], $context['request_id'])
                && ! str_contains(json_encode($context), '+12025550101');
        })->once();
    }

    public function test_failed_sends_also_consume_ip_allowance_without_a_global_pause(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->providerResponses[] = new Response(401, '{"code":20003,"message":"not active"}');
            $this->send('+1202555010'.$i)->assertStatus(503);
        }
        $this->send('+12025550106')->assertStatus(429);
        $this->assertCount(5, $this->providerCalls);
        $this->send('+12025550106', '192.0.2.2')->assertOk();
        $this->assertCount(6, $this->providerCalls);
    }

    public function test_messages_only_configuration_still_works(): void
    {
        config(['services.twilio.verify_service_sid' => null]);
        $this->send('+12025550101')->assertOk();
        $this->assertStringContainsString('/Messages.json', $this->providerCalls[0]);
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', Cache::get('otp:12025550101'));
    }

    public function test_failed_messages_send_removes_cached_code_and_returns_error(): void
    {
        config(['services.twilio.verify_service_sid' => null]);
        $this->providerResponses[] = new Response(400, '{"code":21211,"message":"invalid destination"}');
        $this->send('+12025550101')->assertStatus(503);
        $this->assertNull(Cache::get('otp:12025550101'));
        $this->assertCount(1, $this->providerCalls);
    }

    public function test_invalid_phone_does_not_consume_budget(): void
    {
        foreach (['abc', '+000000000', '+123', '+12025550101000000'] as $phone) {
            $this->send($phone)->assertStatus(422);
        }
        $this->assertCount(0, $this->providerCalls);
        for ($i = 1; $i <= 5; $i++) {
            $this->send('+1202555010'.$i)->assertOk();
        }
    }

    public function test_guard_fails_closed_when_cache_is_unavailable(): void
    {
        config(['otp.cache_store' => 'nonexistent']);
        $this->send('+12025550101')->assertStatus(503);
        $this->assertCount(0, $this->providerCalls);
    }

    public function test_contended_reservation_lock_does_not_allow_a_send(): void
    {
        $key = 'otp-send:ip-hour:'.hash('sha256', inet_pton('192.0.2.1'));
        $lock = Cache::store('array')->lock($key.':lock', 10);
        $this->assertTrue($lock->get());
        try {
            $this->send('+12025550101')->assertStatus(503);
            $this->assertCount(0, $this->providerCalls);
        } finally {
            $lock->release();
        }
    }

    public function test_guard_shares_reserved_budget_between_instances_and_ipv6_spellings(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->assertNull((new OtpSendGuard)->reserve('2001:db8::1', '+1202555010'.$i));
        }
        $this->assertSame(429, (new OtpSendGuard)->reserve('2001:0db8:0000:0000:0000:0000:0000:0001', '+12025550106')['status']);
    }

    public function test_phone_cooldown_applies_across_ips_and_phone_formats(): void
    {
        $this->travelTo(now()->startOfMinute()->addSeconds(59));
        $this->send('+12025550101')->assertOk();
        $this->travel(59)->seconds();
        $this->send('+1 (202) 555-0101', '192.0.2.2')->assertStatus(429)
            ->assertJson(['code' => 'otp_rate_limited', 'retry_after' => 1])
            ->assertHeader('Retry-After', '1');
        $this->travel(1)->seconds();
        $this->send('12025550101', '192.0.2.3')->assertOk();
        $this->assertCount(2, $this->providerCalls);
    }

    public function test_different_numbers_do_not_share_the_phone_cooldown(): void
    {
        $this->send('+12025550101')->assertOk();
        $this->send('+12025550102')->assertOk();
        $this->assertCount(2, $this->providerCalls);
    }

    public function test_failed_provider_call_keeps_phone_cooldown_without_consuming_another_ip_slot(): void
    {
        $this->providerResponses[] = new Response(503, '{"message":"Unavailable"}');
        $this->send('+12025550101')->assertStatus(503);
        $this->send('+12025550101', '192.0.2.2')->assertStatus(429)->assertHeader('Retry-After');
        for ($i = 2; $i <= 6; $i++) {
            $this->send('+1202555010'.$i, '192.0.2.2')->assertOk();
        }
        $this->assertCount(6, $this->providerCalls);
    }

    public function test_untrusted_forwarded_prefix_does_not_change_ip_behind_nginx(): void
    {
        // Host nginx appends the real client address after any supplied X-Forwarded-For.
        for ($i = 1; $i <= 6; $i++) {
            $response = $this->withHeaders(['X-Forwarded-For' => '198.51.100.'.$i.', 192.0.2.1'])
                ->withServerVariables(['REMOTE_ADDR' => '172.18.0.1'])
                ->postJson('/api/mobile/auth/send-otp', ['phone' => '+1202555010'.$i]);
            $response->assertStatus($i <= 5 ? 200 : 429);
        }
        $this->assertCount(5, $this->providerCalls);
    }

    public function test_concurrent_workers_cannot_reserve_more_than_two_sends(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl is required for the multiprocess cache-lock test');
        }
        $path = sys_get_temp_dir().'/otp-guard-test-'.bin2hex(random_bytes(8));
        config([
            'cache.stores.otp_test_file' => ['driver' => 'file', 'path' => $path, 'lock_path' => $path],
            'otp.cache_store' => 'otp_test_file',
            'otp.ip_hourly_limit' => 2,
            'otp.phone_hourly_limit' => 2,
        ]);
        $children = [];
        $results = [];
        try {
            for ($i = 0; $i < 10; $i++) {
                $pid = pcntl_fork();
                if ($pid === -1) {
                    $this->fail('Could not fork cache-lock test worker');
                }
                if ($pid === 0) {
                    $decision = (new OtpSendGuard)->reserve('192.0.2.1', '+1202555011'.$i);
                    exit($decision === null ? 0 : ($decision['status'] === 429 ? 2 : 3));
                }
                $children[] = $pid;
            }
        } finally {
            foreach ($children as $pid) {
                pcntl_waitpid($pid, $status);
                $results[] = pcntl_wifexited($status) ? pcntl_wexitstatus($status) : -1;
            }
            File::deleteDirectory($path);
        }
        $this->assertCount(10, $results);
        $this->assertCount(2, array_filter($results, fn ($code) => $code === 0));
        // Contention can safely return 503 instead of 429 after the bounded lock wait.
        $this->assertSame([], array_values(array_diff($results, [0, 2, 3])));
    }

    private function send(string $phone, string $ip = '192.0.2.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/mobile/auth/send-otp', ['phone' => $phone]);
    }

    public function test_two_per_phone_per_rolling_hour_across_ips_and_formats(): void
    {
        config(['otp.phone_hourly_limit' => 2, 'otp.ip_hourly_limit' => 2]);
        $this->travelTo(now()->startOfHour()->addMinutes(59));
        $this->send('+12025550101')->assertOk();
        $this->travel(60)->seconds();
        $this->send('+1 (202) 555-0101', '192.0.2.2')->assertOk();
        $this->travel(60)->seconds();
        $this->send('12025550101', '192.0.2.3')->assertStatus(429)
            ->assertHeader('Retry-After', '3480');
        $this->travel(3480)->seconds();
        $this->send('+12025550101', '192.0.2.3')->assertOk();
        $this->travel(60)->seconds();
        $this->send('+12025550101', '192.0.2.4')->assertOk();
        $this->assertCount(4, $this->providerCalls);
    }

    public function test_two_per_ip_per_rolling_hour_shared_by_all_phones(): void
    {
        config(['otp.phone_hourly_limit' => 2, 'otp.ip_hourly_limit' => 2]);
        $this->travelTo(now()->startOfHour()->addMinutes(59));
        $this->send('+12025550101')->assertOk();
        $this->travel(2)->minutes();
        $this->send('+12025550102')->assertOk();
        $this->send('+12025550103')->assertStatus(429)->assertHeader('Retry-After', '3480');
        $this->send('+12025550103', '192.0.2.2')->assertOk();
        $this->travel(58)->minutes();
        $this->send('+12025550104')->assertOk();
        $this->send('+12025550105')->assertStatus(429)->assertHeader('Retry-After', '120');
        $this->assertCount(4, $this->providerCalls);
    }

    public function test_failed_provider_calls_consume_phone_hourly_allowance(): void
    {
        config(['otp.phone_hourly_limit' => 2, 'otp.ip_hourly_limit' => 2]);
        for ($i = 1; $i <= 2; $i++) {
            $this->providerResponses[] = new Response(503, '{"message":"Unavailable"}');
            $this->send('+12025550101', '192.0.2.'.$i)->assertStatus(503);
            $this->travel(60)->seconds();
        }
        $this->send('+12025550101', '192.0.2.3')->assertStatus(429);
        $this->assertCount(2, $this->providerCalls);
    }

    public function test_reduced_ip_limit_keeps_existing_reservations(): void
    {
        $this->travelTo(now()->startOfHour());
        for ($i = 1; $i <= 5; $i++) {
            $this->send('+1202555010'.$i)->assertOk();
            $this->travel(60)->seconds();
        }
        config(['otp.ip_hourly_limit' => 2]);
        $this->send('+12025550106')->assertStatus(429)->assertHeader('Retry-After', '3480');
        $this->assertCount(5, $this->providerCalls);
    }

    public function test_concurrent_ips_cannot_reserve_the_same_phone_twice(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl is required for the multiprocess cache-lock test');
        }
        $path = sys_get_temp_dir().'/otp-phone-test-'.bin2hex(random_bytes(8));
        config([
            'cache.stores.otp_test_file' => ['driver' => 'file', 'path' => $path, 'lock_path' => $path],
            'otp.cache_store' => 'otp_test_file',
        ]);
        $children = [];
        $results = [];
        try {
            for ($i = 0; $i < 10; $i++) {
                $pid = pcntl_fork();
                if ($pid === -1) {
                    $this->fail('Could not fork cache-lock test worker');
                }
                if ($pid === 0) {
                    $decision = (new OtpSendGuard)->reserve('192.0.2.'.($i + 1), '+12025550101');
                    exit($decision === null ? 0 : ($decision['status'] === 429 ? 2 : 3));
                }
                $children[] = $pid;
            }
        } finally {
            foreach ($children as $pid) {
                pcntl_waitpid($pid, $status);
                $results[] = pcntl_wifexited($status) ? pcntl_wexitstatus($status) : -1;
            }
            File::deleteDirectory($path);
        }
        $this->assertCount(10, $results);
        $this->assertCount(1, array_filter($results, fn ($code) => $code === 0));
        $this->assertSame([], array_values(array_diff($results, [0, 2, 3])));
    }
}
