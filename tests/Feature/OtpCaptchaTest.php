<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Mobile\AuthController;
use App\Services\OtpCaptcha;
use App\Support\PhoneIndex;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;
use Twilio\Http\Client as HttpClient;
use Twilio\Http\Response;
use Twilio\Rest\Client;

class OtpCaptchaTest extends TestCase
{
    private int $sends = 0;
    private array $siteverifyResult = [];
    private int $siteverifyStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.url' => 'https://esticly.test',
            'app_check.enforce' => false,
            'otp.cache_store' => 'array',
            'otp.sending_enabled' => true,
            'otp.ip_hourly_limit' => 2,
            'otp.phone_hourly_limit' => 2,
            'otp.captcha.enabled' => true,
            'otp.captcha.site_key' => 'public-test-key',
            'otp.captcha.secret_key' => 'private-test-secret',
            'services.twilio.verify_service_sid' => 'VA_test',
        ]);
        Cache::store('array')->flush();
        Http::preventStrayRequests();
        Http::fake(['challenges.cloudflare.com/*' => fn () => Http::response($this->siteverifyResult, $this->siteverifyStatus)]);
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('phone');
            $table->string('phone_hash')->nullable();
        });
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('phone');
            $table->string('phone_hash')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('permissions')->nullable();
            $table->softDeletes();
        });
        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('request')->andReturnUsing(function () {
            $this->sends++;

            return new Response(201, '{"sid":"VE_test","status":"pending"}');
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

    protected function tearDown(): void
    {
        Schema::dropIfExists('staff');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    private function send(array $data = [], string $ip = '192.0.2.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/mobile/auth/send-otp', $data + ['phone' => '+12025550101']);
    }

    private function challenge(string $phone = '+12025550101'): string
    {
        return $this->send(['phone' => $phone])->assertForbidden()
            ->assertJsonPath('code', 'captcha_required')->json('captcha.id');
    }

    private function allow(string $id, array $overrides = []): void
    {
        $this->siteverifyResult = $overrides + [
            'success' => true, 'action' => OtpCaptcha::ACTION, 'hostname' => 'esticly.test', 'cdata' => $id,
        ];
    }

    public function test_old_clients_and_direct_api_calls_cannot_send_without_captcha(): void
    {
        $id = $this->challenge();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $id);
        $this->assertSame(0, $this->sends);
        Http::assertNothingSent();
        $this->get('/auth/otp-challenge/'.$id.'?lang=uk')->assertOk()
            ->assertSee('public-test-key')->assertSee('lang="uk"', false)
            ->assertDontSee('private-test-secret')->assertDontSee('+12025550101')
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_successful_challenge_sends_once_and_cannot_be_replayed(): void
    {
        $id = $this->challenge();
        $this->allow($id);
        $data = ['captcha_id' => $id, 'captcha_token' => 'valid-token'];
        $this->send($data)->assertOk();
        $this->send($data, '192.0.2.2')->assertForbidden()->assertJsonPath('code', 'captcha_invalid');
        $this->assertSame(1, $this->sends);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['secret'] === 'private-test-secret'
            && $request['response'] === 'valid-token' && $request['remoteip'] === '192.0.2.1');
        $this->get('/auth/otp-challenge/'.$id)->assertNotFound();
    }

    public function test_challenge_is_bound_to_normalized_phone(): void
    {
        $id = $this->challenge();
        $this->allow($id);
        $data = ['captcha_id' => $id, 'captcha_token' => 'valid-token'];
        $this->send($data + ['phone' => '+12025550102'])->assertForbidden();
        Http::assertNothingSent();
        $this->send($data + ['phone' => '+1 (202) 555-0101'])->assertOk();
        $this->assertSame(1, $this->sends);
    }

    public function test_expired_challenge_never_contacts_provider(): void
    {
        $id = $this->challenge();
        $this->travel(301)->seconds();
        $this->send(['captcha_id' => $id, 'captcha_token' => 'token'])->assertForbidden();
        $this->get('/auth/otp-challenge/'.$id)->assertNotFound();
        $this->assertSame(0, $this->sends);
        Http::assertNothingSent();
    }

    public function test_provider_failure_or_wrong_hostname_action_or_binding_never_sends_sms(): void
    {
        foreach ([['success' => false], ['hostname' => 'other.test'], ['action' => 'other'], ['cdata' => 'other']] as $index => $override) {
            $this->travel(61)->seconds();
            $phone = '+1202555010'.($index + 1);
            $id = $this->challenge($phone);
            $this->allow($id, $override);
            $this->send(['phone' => $phone, 'captcha_id' => $id, 'captcha_token' => 'token'])->assertForbidden();
        }
        $this->assertSame(0, $this->sends);
    }

    public function test_missing_keys_http_failure_and_cache_failure_fail_closed(): void
    {
        config(['otp.captcha.secret_key' => '']);
        $this->send()->assertStatus(503)->assertJsonPath('code', 'captcha_unavailable');
        config(['otp.captcha.secret_key' => 'private-test-secret']);
        $id = $this->challenge();
        $this->siteverifyStatus = 503;
        $this->send(['captcha_id' => $id, 'captcha_token' => 'token'])->assertStatus(503);
        $this->travel(61)->seconds();
        config(['otp.cache_store' => 'nonexistent']);
        $this->send()->assertStatus(503)->assertJsonPath('code', 'captcha_unavailable');
        $this->assertSame(0, $this->sends);
    }

    public function test_existing_user_and_active_staff_keep_login_but_not_unlimited_sends(): void
    {
        DB::table('users')->insert(['phone' => '+12025550101', 'phone_hash' => PhoneIndex::hash('+12025550101')]);
        $this->send()->assertOk();
        $this->send()->assertStatus(429);
        DB::table('staff')->insert(['phone' => '+12025550102', 'phone_hash' => PhoneIndex::hash('+12025550102'), 'permissions' => '{"base_access":true}']);
        $this->send(['phone' => '+12025550102'])->assertOk();
        Http::assertNothingSent();
        $this->assertSame(2, $this->sends);
    }

    public function test_registered_numbers_bypass_exhausted_daily_budget_with_captcha_disabled(): void
    {
        config(['otp.captcha.enabled' => false]);
        $key = 'otp-send:global-day:'.now('Europe/Warsaw')->format('Y-m-d');
        Cache::put($key, 80, 86400);
        DB::table('users')->insert(['phone' => '+12025550101', 'phone_hash' => PhoneIndex::hash('+12025550101')]);
        DB::table('staff')->insert(['phone' => '+12025550102', 'phone_hash' => PhoneIndex::hash('+12025550102'), 'permissions' => '{"base_access":true}']);
        $this->send()->assertOk();
        $this->send()->assertStatus(429);
        $this->send(['phone' => '+12025550102'], '192.0.2.2')->assertOk();
        $this->send(['phone' => '+12025550103', 'registered' => true], '192.0.2.3')->assertStatus(429);
        $this->assertSame(80, Cache::get($key));
        $this->assertSame(2, $this->sends);
        Http::assertNothingSent();
    }

    public function test_registered_only_mode_blocks_new_numbers_but_allows_existing_logins(): void
    {
        config(['otp.captcha.enabled' => false, 'otp.registered_only' => true, 'otp.global_daily_limit' => 0]);
        $this->send(['registered' => true])->assertStatus(503)->assertJsonPath('code', 'otp_unavailable');
        DB::table('users')->insert(['phone' => '+12025550101', 'phone_hash' => PhoneIndex::hash('+12025550101')]);
        $this->send()->assertOk();
        DB::table('staff')->insert(['phone' => '+12025550102', 'phone_hash' => PhoneIndex::hash('+12025550102'), 'permissions' => '{"base_access":true}']);
        $this->send(['phone' => '+12025550102'], '192.0.2.2')->assertOk();
        $this->travel(61)->seconds();
        $this->send()->assertOk();
        $this->travel(61)->seconds();
        $this->send([], '192.0.2.3')->assertStatus(429);
        $this->assertSame(3, $this->sends);
        Http::assertNothingSent();
    }

    public function test_staff_without_access_does_not_bypass_daily_budget(): void
    {
        config(['otp.captcha.enabled' => false, 'otp.global_daily_limit' => 0]);
        foreach ([['is_active' => false, 'permissions' => '{"base_access":true}'], ['is_active' => true, 'permissions' => '{}'], ['is_active' => true, 'permissions' => '{"base_access":true}', 'deleted_at' => now()]] as $row) {
            DB::table('staff')->delete();
            DB::table('staff')->insert($row + ['phone' => '+12025550101', 'phone_hash' => PhoneIndex::hash('+12025550101')]);
            $this->send()->assertStatus(429);
        }
        $this->assertSame(0, $this->sends);
    }

    public function test_inactive_or_deleted_staff_does_not_bypass_captcha(): void
    {
        DB::table('staff')->insert(['phone' => '+12025550101', 'phone_hash' => PhoneIndex::hash('+12025550101'), 'is_active' => false, 'permissions' => '{"base_access":true}']);
        $this->challenge();
        DB::table('staff')->update(['is_active' => true, 'deleted_at' => now()]);
        $this->challenge();
        $this->assertSame(0, $this->sends);
    }

    public function test_captcha_does_not_bypass_app_check_or_paused_sends(): void
    {
        config(['app_check.enforce' => true]);
        $this->send()->assertForbidden();
        config(['app_check.enforce' => false]);
        $id = $this->challenge();
        $this->allow($id);
        config(['otp.sending_enabled' => false, 'otp.paused_allowed_phone_hashes' => []]);
        $this->send(['captcha_id' => $id, 'captcha_token' => 'token'])->assertStatus(503)
            ->assertJsonPath('code', 'otp_unavailable');
        $this->assertSame(0, $this->sends);
    }

    public function test_fresh_captcha_does_not_bypass_phone_cooldown(): void
    {
        $id = $this->challenge();
        $this->allow($id);
        $this->send(['captcha_id' => $id, 'captcha_token' => 'first'])->assertOk();
        $id = app(OtpCaptcha::class)->check('+12025550101', '192.0.2.2', null, null)['captcha']['id'];
        $this->allow($id);
        $this->send(['captcha_id' => $id, 'captcha_token' => 'second'], '192.0.2.2')->assertStatus(429);
        $this->assertSame(1, $this->sends);
    }

    public function test_locked_challenge_cannot_send(): void
    {
        $id = $this->challenge();
        $lock = Cache::store('array')->lock('otp-captcha:'.$id.':lock', 20);
        $this->assertTrue($lock->get());
        try {
            $this->send(['captcha_id' => $id, 'captcha_token' => 'token'])->assertStatus(503);
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
        $this->assertSame(0, $this->sends);
    }

    public function test_malformed_or_oversized_captcha_is_rejected_without_provider_calls(): void
    {
        $this->send(['captcha_id' => '../bad', 'captcha_token' => 'token'])->assertUnprocessable();
        $this->send(['captcha_id' => str_repeat('a', 64), 'captcha_token' => str_repeat('x', 2049)])->assertUnprocessable();
        $this->assertSame(0, $this->sends);
        Http::assertNothingSent();
    }

    public function test_review_number_never_requires_captcha_or_paid_sms(): void
    {
        $env = \Illuminate\Support\Env::getRepository();
        $previous = $env->get('REVIEW_OTP_PHONES');
        $env->set('REVIEW_OTP_PHONES', '+12025550101');
        try {
            $this->send()->assertOk()->assertJsonPath('channel', 'review');
            $this->assertSame(0, $this->sends);
            Http::assertNothingSent();
        } finally {
            $previous === null ? $env->clear('REVIEW_OTP_PHONES') : $env->set('REVIEW_OTP_PHONES', $previous);
        }
    }
}
