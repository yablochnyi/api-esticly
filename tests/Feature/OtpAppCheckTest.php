<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Mobile\AuthController;
use App\Services\AppCheckRejected;
use App\Services\FirebaseAppCheck;
use Firebase\JWT\JWT;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Twilio\Http\Client as HttpClient;
use Twilio\Http\Response;
use Twilio\Rest\Client;

class OtpAppCheckTest extends TestCase
{
    private static string $privateKey = '';

    private static array $jwks;

    private int $sends = 0;

    private bool $providerFails = false;

    private bool $googleFails = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, self::$privateKey);
        $rsa = openssl_pkey_get_details($key)['rsa'];
        self::$jwks = ['keys' => [[
            'kty' => 'RSA', 'kid' => 'test-key', 'alg' => 'RS256', 'use' => 'sig',
            'n' => JWT::urlsafeB64Encode($rsa['n']), 'e' => JWT::urlsafeB64Encode($rsa['e']),
        ]]];
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app_check.enforce' => true,
            'app_check.project_number' => '725624745298',
            'app_check.app_ids' => ['1:725624745298:android:1e5c5292ccde1bb3c2de8b', '1:725624745298:ios:63d7fc410b69b9c1c2de8b'],
            'app_check.cache_store' => 'array',
            'otp.cache_store' => 'array',
            'otp.ip_hourly_limit' => 5,
            'services.twilio.verify_service_sid' => 'VA_test',
        ]);
        Cache::store('array')->flush();
        Http::preventStrayRequests();
        Http::fake([FirebaseAppCheck::JWKS_URL => fn () => Http::response(
            $this->googleFails ? [] : self::$jwks, $this->googleFails ? 503 : 200
        )]);
        Log::spy();
        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('request')->andReturnUsing(function () {
            $this->sends++;

            return $this->providerFails
                ? new Response(403, '{"code":60410,"message":"Blocked"}')
                : new Response(201, '{"sid":"VE_test","status":"pending"}');
        });
        $client = new Client('AC_test', 'test-token', 'AC_test', null, $http);
        $this->app->instance(AuthController::class, new class($client) extends AuthController
        {
            public function __construct(private Client $client) {}

            protected function twilioClient(): ?Client
            {
                return $this->client;
            }
        });
    }

    private function token(array $overrides = [], array $headers = []): string
    {
        return JWT::encode(array_merge([
            'iss' => 'https://firebaseappcheck.googleapis.com/725624745298',
            'aud' => ['projects/725624745298'],
            'sub' => config('app_check.app_ids')[0],
            'iat' => time() - 10, 'exp' => time() + 3600, 'jti' => bin2hex(random_bytes(16)),
        ], $overrides), self::$privateKey, 'RS256', $headers['kid'] ?? 'test-key', $headers);
    }

    private function send(?string $token, string $ip = '192.0.2.1', string $phone = '+12025550101')
    {
        $this->flushHeaders();
        if ($token !== null) {
            $this->withHeader('X-Firebase-AppCheck', $token);
        }

        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/mobile/auth/send-otp', ['phone' => $phone]);
    }

    public function test_missing_and_malformed_tokens_do_not_reach_provider_or_google(): void
    {
        foreach ([null, '', 'invalid', 'a.b.c', str_repeat('x', 16385)] as $i => $token) {
            $this->send($token, '192.0.2.'.($i + 1), '+1202555010'.$i)->assertForbidden()->assertJson(['code' => 'app_check_required']);
        }
        $this->assertSame(0, $this->sends);
        Http::assertNothingSent();
    }

    public function test_both_registered_apps_are_accepted_with_fresh_tokens(): void
    {
        foreach (config('app_check.app_ids') as $appId) {
            $this->send($this->token(['sub' => $appId]))->assertOk();
        }
        $this->assertSame(2, $this->sends);
        Http::assertSentCount(1);
    }

    public static function invalidClaims(): array
    {
        return [
            'issuer' => [['iss' => 'https://attacker.example']],
            'audience' => [['aud' => ['projects/another']]],
            'audience type' => [['aud' => 'projects/725624745298']],
            'other app' => [['sub' => '1:725624745298:android:other']],
            'missing app' => [['sub' => null]],
            'missing limited-use id' => [['jti' => null]],
            'empty limited-use id' => [['jti' => '']],
            'expired' => [['exp' => 1]],
            'missing expiry' => [['exp' => null]],
            'expiry type' => [['exp' => '9999999999']],
            'missing issued time' => [['iat' => null]],
            'future issued time' => [['iat' => 9999999999]],
            'excessive lifetime' => [['iat' => 1]],
            'not active yet' => [['nbf' => 9999999999]],
        ];
    }

    #[DataProvider('invalidClaims')]
    public function test_invalid_claims_are_rejected_before_sms(array $claims): void
    {
        $this->send($this->token($claims))->assertForbidden();
        $this->assertSame(0, $this->sends);
    }

    public function test_wrong_signature_and_algorithm_and_type_are_rejected(): void
    {
        $token = $this->token();
        $parts = explode('.', $token);
        $parts[2] = JWT::urlsafeB64Encode(str_repeat('x', 256));
        $this->send(implode('.', $parts))->assertForbidden();
        $this->send(JWT::encode(['exp' => time() + 3600], str_repeat('x', 32), 'HS256', 'test-key'))->assertForbidden();
        $this->send($this->token([], ['typ' => 'OTHER']))->assertForbidden();
        $this->assertSame(0, $this->sends);
    }

    public function test_same_token_cannot_be_reused_with_another_ip_or_phone(): void
    {
        $token = $this->token();
        $this->send($token)->assertOk();
        $this->send($token, '192.0.2.2', '+12025550102')->assertForbidden();
        $this->assertSame(1, $this->sends);
        Log::shouldHaveReceived('notice')->with('otp_app_check_rejected', ['reason' => 'replayed', 'ip' => '192.0.2.2'])->once();
    }

    public function test_same_jti_in_a_different_signed_token_is_still_replay(): void
    {
        $this->send($this->token(['jti' => 'one-use']))->assertOk();
        $this->send($this->token(['jti' => 'one-use', 'exp' => time() + 3000]), '192.0.2.2')->assertForbidden();
        $this->assertSame(1, $this->sends);
    }

    public function test_failed_sms_does_not_release_token(): void
    {
        $this->providerFails = true;
        $token = $this->token();
        $this->send($token)->assertStatus(503);
        $this->send($token, '192.0.2.2')->assertForbidden();
        $this->assertSame(1, $this->sends);
    }

    public function test_valid_attestation_does_not_bypass_hourly_ip_limit(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->send($this->token(), '192.0.2.1', '+1202555010'.$i)->assertStatus($i <= 5 ? 200 : 429);
        }
        $this->assertSame(5, $this->sends);
    }

    public function test_google_outage_is_fail_closed_and_refreshes_are_bounded(): void
    {
        $this->googleFails = true;
        $this->send($this->token())->assertStatus(503)->assertJson(['code' => 'app_check_unavailable']);
        $this->send($this->token(), '192.0.2.2')->assertStatus(503);
        Http::assertSentCount(1);
        $this->assertSame(0, $this->sends);
    }

    public function test_cache_failure_is_fail_closed(): void
    {
        config(['app_check.cache_store' => 'missing']);
        $this->send($this->token())->assertStatus(503);
        $this->assertSame(0, $this->sends);
    }

    public function test_key_rotation_refreshes_once_and_unknown_keys_do_not_amplify_requests(): void
    {
        Cache::put('app-check:jwks', ['jwks' => ['keys' => [array_merge(self::$jwks['keys'][0], ['kid' => 'old'])]], 'fetched_at' => time() - 61], 3600);
        $this->send($this->token())->assertOk();
        $this->send($this->token([], ['kid' => 'attacker-1']), '192.0.2.2')->assertForbidden();
        $this->send($this->token([], ['kid' => 'attacker-2']), '192.0.2.3')->assertForbidden();
        Http::assertSentCount(1);
        $this->assertSame(1, $this->sends);
    }

    public function test_disabling_enforcement_is_not_allowed_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app_check.enforce' => false]);
        $this->send(null)->assertForbidden();
        $this->assertSame(0, $this->sends);
    }

    public function test_otp_verification_is_not_intercepted_by_app_check(): void
    {
        $this->postJson('/api/mobile/auth/verify-otp', [])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_database_cache_reserves_tokens_and_locks_key_refresh(): void
    {
        config([
            'database.connections.app_check_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.stores.app_check_database' => [
                'driver' => 'database', 'connection' => 'app_check_test', 'table' => 'cache',
                'lock_connection' => 'app_check_test', 'lock_table' => 'cache_locks',
            ],
            'app_check.cache_store' => 'app_check_database',
        ]);
        Schema::connection('app_check_test')->create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });
        Schema::connection('app_check_test')->create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
        $token = $this->token();
        $this->send($token)->assertOk();
        $this->send($token, '192.0.2.2')->assertForbidden();
        $this->assertSame(1, $this->sends);
        Http::assertSentCount(1);
    }

    public function test_concurrent_workers_can_consume_a_token_only_once(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl is required');
        }
        $path = sys_get_temp_dir().'/app-check-test-'.bin2hex(random_bytes(8));
        config([
            'cache.stores.app_check_test' => ['driver' => 'file', 'path' => $path, 'lock_path' => $path],
            'app_check.cache_store' => 'app_check_test',
        ]);
        Cache::store('app_check_test')->put('app-check:jwks', ['jwks' => self::$jwks, 'fetched_at' => time()], 3600);
        $token = $this->token();
        $children = [];
        $results = [];
        try {
            for ($i = 0; $i < 10; $i++) {
                $pid = pcntl_fork();
                if ($pid === -1) {
                    $this->fail('Could not fork test worker');
                }
                if ($pid === 0) {
                    try {
                        (new FirebaseAppCheck)->verifyAndConsume($token);
                        exit(0);
                    } catch (AppCheckRejected $e) {
                        exit($e->reason === 'replayed' ? 2 : 3);
                    } catch (\Throwable) {
                        exit(3);
                    }
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
        $this->assertCount(1, array_filter($results, fn ($code) => $code === 0));
        $this->assertCount(9, array_filter($results, fn ($code) => $code === 2));
    }
}
