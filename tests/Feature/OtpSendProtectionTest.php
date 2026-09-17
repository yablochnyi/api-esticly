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

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.url' => 'http://localhost',
            'app_check.enforce' => false,
            'otp.cache_store' => 'array',
            'otp.ip_hourly_limit' => 5,
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
        $this->assertNull($guard->reserve('192.0.2.1'));
        $this->travel(30)->minutes();
        for ($i = 0; $i < 4; $i++) {
            $this->assertNull($guard->reserve('192.0.2.1'));
        }
        $this->travel(31)->minutes();
        $this->assertNull($guard->reserve('192.0.2.1'));
        $denied = $guard->reserve('192.0.2.1');
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
            $this->assertNull((new OtpSendGuard)->reserve('2001:db8::1'));
        }
        $this->assertSame(429, (new OtpSendGuard)->reserve('2001:0db8:0000:0000:0000:0000:0000:0001')['status']);
    }

    public function test_existing_phone_limit_still_applies_across_ips(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->send('+12025550101', '192.0.2.'.$i)->assertOk();
        }
        $this->send('+12025550101', '192.0.2.4')->assertStatus(429);
        $this->assertCount(3, $this->providerCalls);
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

    public function test_concurrent_workers_cannot_reserve_more_than_five_sends(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl is required for the multiprocess cache-lock test');
        }
        $path = sys_get_temp_dir().'/otp-guard-test-'.bin2hex(random_bytes(8));
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
                    $decision = (new OtpSendGuard)->reserve('192.0.2.1');
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
        $this->assertCount(5, array_filter($results, fn ($code) => $code === 0));
        // Contention can safely return 503 instead of 429 after the bounded lock wait.
        $this->assertSame([], array_values(array_diff($results, [0, 2, 3])));
    }

    private function send(string $phone, string $ip = '192.0.2.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/mobile/auth/send-otp', ['phone' => $phone]);
    }
}
