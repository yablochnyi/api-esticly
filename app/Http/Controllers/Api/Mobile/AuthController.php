<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\User;
use App\Support\PhoneIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;
use Twilio\Rest\Client as TwilioClient;

class AuthController extends Controller
{
    private function reviewOtpPhones(): array
    {
        $phones = [];

        $legacy = trim((string) env('REVIEW_OTP_PHONE', ''));
        if ($legacy !== '') {
            $phones[] = $legacy;
        }

        $list = (string) env('REVIEW_OTP_PHONES', '');
        foreach (preg_split('/[,;\s]+/', $list) ?: [] as $phone) {
            $phone = trim((string) $phone);
            if ($phone !== '') {
                $phones[] = $phone;
            }
        }

        return array_values(array_unique($phones));
    }

    private function reviewOtpCode(): string
    {
        return trim((string) env('REVIEW_OTP_CODE', '123456'));
    }

    private function isReviewOtpPhone(string $phone): bool
    {
        $configured = $this->reviewOtpPhones();
        if (empty($configured)) {
            return false;
        }

        $normalized = $this->normalizeE164($phone);
        foreach ($configured as $reviewPhone) {
            if ($normalized === $this->normalizeE164($reviewPhone)) {
                return true;
            }
        }

        return false;
    }

    public function logout(Request $request)
    {
        $token = $request->user()?->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return response()->json(['ok' => true]);
    }

    private function phoneDigits(?string $phone): string
    {
        $phone = trim((string)$phone);
        $digits = preg_replace('/\D+/', '', $phone);
        return is_string($digits) ? $digits : '';
    }

    private function phoneVariants(string $phone): array
    {
        $phone = trim($phone);
        $digits = $this->phoneDigits($phone);
        $variants = array_filter([
            $phone,
            $digits,
            $digits ? ('+' . $digits) : null,
        ], fn ($v) => is_string($v) && trim($v) !== '');

        // uniq + stable order
        $out = [];
        foreach ($variants as $v) {
            if (!in_array($v, $out, true)) $out[] = $v;
        }
        return $out;
    }

    private function findUserByPhone(string $phone): ?User
    {
        $variants = $this->phoneVariants($phone);
        if (empty($variants)) return null;

        if (!Schema::hasColumn('users', 'phone_hash')) {
            return User::query()->whereIn('phone', $variants)->first();
        }

        $phoneHash = PhoneIndex::hash($phone);
        if (!$phoneHash) return null;

        return User::query()
            ->where('phone_hash', $phoneHash)
            ->first();
    }

    private function findStaffByPhone(string $phone): ?Staff
    {
        $variants = $this->phoneVariants($phone);
        if (empty($variants)) return null;

        if (!Schema::hasColumn('staff', 'phone_hash')) {
            return Staff::withTrashed()->whereIn('phone', $variants)->orderByDesc('is_active')->first();
        }

        $phoneHash = PhoneIndex::hash($phone);
        if (!$phoneHash) return null;

        $staff = Staff::withTrashed()
            ->where('phone_hash', $phoneHash)
            ->orderByDesc('is_active')
            ->first();

        return $staff ?: null;
    }

    private function hasAmbiguousStaffPhone(string $phone): bool
    {
        if (!Schema::hasColumn('staff', 'phone_hash')) {
            return false;
        }

        $phoneHash = PhoneIndex::hash($phone);
        if (!$phoneHash) return false;

        return Staff::withTrashed()
            ->where('phone_hash', $phoneHash)
            ->limit(2)
            ->count() > 1;
    }

    private function healSelfOwnedOrganizationUser(User $user): void
    {
        if (!$user->organization_id || (int) $user->organization_id !== (int) $user->id) {
            return;
        }

        $staffId = $user->staff_id ? (int) $user->staff_id : null;

        $user->organization_id = null;
        $user->staff_id = null;
        $user->save();

        if ($staffId) {
            Staff::query()
                ->where('id', $staffId)
                ->where('staff_user_id', $user->id)
                ->update(['staff_user_id' => null]);
        }

        try {
            $user->removeRole('staff');
        } catch (Throwable) {
            // Role may not exist in some environments.
        }
    }

    private function otpCacheKey(string $phone): string
    {
        $digits = $this->phoneDigits($phone);
        $key = $digits !== '' ? $digits : trim($phone);
        return 'otp:' . $key;
    }

    private function normalizeE164(string $phone): string
    {
        $digits = $this->phoneDigits($phone);
        if ($digits !== '') return '+' . $digits;
        return trim($phone);
    }

    private function twilioClient(): ?TwilioClient
    {
        $sid = (string)config('services.twilio.sid'); // AC...
        if (trim($sid) === '') return null;

        $token = (string)config('services.twilio.token'); // Auth Token
        $apiKeySid = (string)config('services.twilio.api_key_sid'); // SK...
        $apiKeySecret = (string)config('services.twilio.api_key_secret');

        if (trim($apiKeySid) !== '' && trim($apiKeySecret) !== '') {
            return new TwilioClient($apiKeySid, $apiKeySecret, $sid);
        }
        if (trim($token) !== '') {
            return new TwilioClient($sid, $token);
        }
        return null;
    }

    private function sendOtpViaTwilio(string $toE164, string $body): bool
    {
        $from = (string)config('services.twilio.from');
        $mg = (string)config('services.twilio.messaging_service_sid');

        $client = $this->twilioClient();
        if (!$client) return false;

        $payload = ['body' => $body];
        if (trim($mg) !== '') {
            $payload['messagingServiceSid'] = $mg;
        } else {
            if (trim($from) === '') return false;
            $payload['from'] = $from;
        }

        $client->messages->create($toE164, $payload);
        return true;
    }

    private function normalizeTwilioLocale(?string $language): string
    {
        $lang = strtolower(trim((string) $language));
        if ($lang === '') {
            return 'en';
        }

        // map app locales to Twilio Verify locales
        if (str_starts_with($lang, 'uk')) return 'uk';
        if (str_starts_with($lang, 'pl')) return 'pl';
        if (str_starts_with($lang, 'en')) return 'en';
        if (str_starts_with($lang, 'it')) return 'it';
        if (str_starts_with($lang, 'fr')) return 'fr';
        if (str_starts_with($lang, 'pt')) return 'pt';
        if (str_starts_with($lang, 'de')) return 'de';
        if (str_starts_with($lang, 'es')) return 'es';
        if (str_starts_with($lang, 'cs')) return 'cs';

        return 'en';
    }

    private function sendOtpViaTwilioVerify(string $toE164, ?string $language = null): bool
    {
        $va = (string)config('services.twilio.verify_service_sid');
        if (trim($va) === '') return false;

        $client = $this->twilioClient();
        if (!$client) return false;

        $params = ['locale' => $this->normalizeTwilioLocale($language)];
        $client->verify->v2->services($va)->verifications->create($toE164, 'sms', $params);
        return true;
    }

    private function checkOtpViaTwilioVerify(string $toE164, string $code): bool
    {
        $va = (string)config('services.twilio.verify_service_sid');
        if (trim($va) === '') return false;

        $client = $this->twilioClient();
        if (!$client) return false;

        $check = $client->verify->v2->services($va)->verificationChecks->create([
            'to' => $toE164,
            'code' => $code,
        ]);
        return ($check->status ?? null) === 'approved';
    }

    public function sendOtp(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'country' => ['nullable', 'string'],
            'language' => ['nullable', 'string'],
        ]);

        $to = $this->normalizeE164($data['phone']);

        if ($this->isReviewOtpPhone($data['phone'])) {
            Cache::put($this->otpCacheKey($data['phone']), $this->reviewOtpCode(), now()->addMinutes(10));

            return response()->json([
                'ok' => true,
                'expires_in' => 600,
                'channel' => 'review',
            ]);
        }

        // Prefer Twilio Verify if configured: no FROM needed, Twilio manages OTP.
        $sentViaTwilio = false;
        try {
            $sentViaTwilio = $this->sendOtpViaTwilioVerify($to, $data['language'] ?? null);
        } catch (Throwable $e) {
            report($e);
            $sentViaTwilio = false;
        }

        // Fallback: our own OTP + Twilio Messages (requires FROM or Messaging Service SID).
        if (!$sentViaTwilio) {
            $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $key = $this->otpCacheKey($data['phone']);
            Cache::put($key, $otp, now()->addMinutes(10));

            $lang = strtolower((string)($data['language'] ?? ''));
            $body = match (true) {
                str_starts_with($lang, 'uk') => "Ваш код підтвердження: {$otp}",
                str_starts_with($lang, 'pl') => "Twój kod weryfikacyjny: {$otp}",
                default => "Your verification code: {$otp}",
            };

            try {
                $sentViaTwilio = $this->sendOtpViaTwilio($to, $body);
            } catch (Throwable $e) {
                report($e);
                $sentViaTwilio = false;
            }

            if (!$sentViaTwilio) {
                logger()->warning('OTP not sent via Twilio (check env)', ['to' => $to]);
            }
        }

        return response()->json([
            'ok' => true,
            'expires_in' => 600,
            'channel' => $sentViaTwilio ? 'twilio' : 'log',
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        $to = $this->normalizeE164($data['phone']);
        $code = (string)$data['code'];

        if ($this->isReviewOtpPhone($data['phone'])) {
            if ($code !== $this->reviewOtpCode()) {
                return response()->json(['message' => 'Invalid code'], 422);
            }
        } else {

            // Prefer Twilio Verify check when configured.
            $va = (string)config('services.twilio.verify_service_sid');
            if (trim($va) !== '') {
                try {
                    $ok = $this->checkOtpViaTwilioVerify($to, $code);
                } catch (Throwable $e) {
                    report($e);
                    $ok = false;
                }
                if (!$ok) {
                    return response()->json(['message' => 'Invalid code'], 422);
                }
            } else {
                // Fallback to cached OTP.
                $key = $this->otpCacheKey($data['phone']);
                $expected = Cache::get($key);
                if (!$expected || $expected !== $code) {
                    return response()->json(['message' => 'Invalid code'], 422);
                }
                Cache::forget($key);
            }
        }

        $user = $this->findUserByPhone($data['phone']);
        $registered = $user !== null;

        if (!$user) {
            if ($this->hasAmbiguousStaffPhone($data['phone'])) {
                return response()->json(['message' => 'phone_ambiguous'], 409);
            }

            // If phone belongs to a staff member, log in to the organization as staff.
            $staff = $this->findStaffByPhone($data['phone']);

            if ($staff) {
                $baseAccess = (bool)($staff->permissions['base_access'] ?? false);
                if (!$baseAccess) {
                    return response()->json(['message' => 'access_denied'], 403);
                }
                if (!$staff->is_active || $staff->deleted_at) {
                    return response()->json(['message' => 'user_disabled'], 403);
                }

                // Reuse already linked staff-user if exists (prevents duplicates).
                $user = null;
                if ($staff->staff_user_id) {
                    $user = User::find($staff->staff_user_id);
                }
                if (!$user) {
                    $user = User::where('staff_id', $staff->id)->first();
                }
                if (!$user) {
                    // Create staff-user record.
                    $variants = $this->phoneVariants($data['phone']);
                    $digits = $this->phoneDigits($data['phone']);
                    $preferredPhone = $digits ? ('+' . $digits) : ($variants[0] ?? $data['phone']);

                    $user = User::create([
                        'phone' => $preferredPhone,
                        'password' => bcrypt(Str::random(32)),
                        'phone_verified_at' => now(),
                    ]);
                }

                if (!$user->phone_verified_at) {
                    $user->phone_verified_at = now();
                }
                $user->name = $staff->name;
                $user->organization_id = $staff->user_id; // org owner id
                $user->staff_id = $staff->id;
                $user->save();
                $user->syncRoles(['staff']);

                if ($staff->staff_user_id !== $user->id) {
                    $staff->staff_user_id = $user->id;
                    $staff->save();
                }

                // Staff accounts should not go through org registration onboarding.
                $registered = true;
            } else {
                // Новый пользователь => организация
                $digits = $this->phoneDigits($data['phone']);
                $phone = $digits ? ('+' . $digits) : $data['phone'];
                $user = User::create([
                    'phone' => $phone,
                    'password' => bcrypt(Str::random(32)),
                    'phone_verified_at' => now(),
                ]);
                $user->syncRoles(['organization']);
            }
        } else {
            if (!$user->phone_verified_at) {
                $user->phone_verified_at = now();
                $user->save();
            }
        }

        $this->healSelfOwnedOrganizationUser($user);
        $user->refresh();

        // If this is a staff account, enforce staff active + base_access.
        if ($user->staff_id) {
            $staff = Staff::withTrashed()->find($user->staff_id);
            if (!$staff) {
                return response()->json(['message' => 'user_disabled'], 403);
            }

            // Heal org link if needed
            if (!$user->organization_id && $staff->user_id) {
                $user->organization_id = $staff->user_id;
                $user->save();
            }

            $baseAccess = (bool)($staff->permissions['base_access'] ?? false);
            if (!$baseAccess) {
                return response()->json(['message' => 'access_denied'], 403);
            }
            if (!$staff->is_active || $staff->deleted_at) {
                return response()->json(['message' => 'user_disabled'], 403);
            }
        }

        $token = $user->createToken('mobile')->plainTextToken;

        $userType = $user->staff_id ? 'staff' : 'organization';

        return response()->json([
            'token' => $token,
            'registered' => $registered,
            'user_type' => $userType,
            'staff_id' => $user->staff_id,
            'organization_id' => $user->organization_id,
        ]);
    }
}
