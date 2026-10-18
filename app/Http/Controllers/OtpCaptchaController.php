<?php

namespace App\Http\Controllers;

use App\Services\OtpCaptcha;
use Illuminate\Http\Request;

class OtpCaptchaController extends Controller
{
    public function __invoke(Request $request, string $challenge, OtpCaptcha $captcha)
    {
        abort_unless(config('otp.captcha.enabled') && $captcha->configured() && $captcha->exists($challenge), 404);
        $locale = $request->query('lang', 'en');
        $locale = in_array($locale, ['en', 'uk', 'pl', 'cs', 'de', 'fr', 'it', 'es', 'pt'], true) ? $locale : 'en';
        $nonce = base64_encode(random_bytes(18));

        return response()->view('auth.otp-captcha', [
            'siteKey' => config('otp.captcha.site_key'),
            'challenge' => $challenge,
            'action' => OtpCaptcha::ACTION,
            'locale' => $locale,
            'nonce' => $nonce,
        ])->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Content-Security-Policy' => "default-src 'none'; script-src 'nonce-{$nonce}' https://challenges.cloudflare.com; style-src 'nonce-{$nonce}'; frame-src https://challenges.cloudflare.com; connect-src https://challenges.cloudflare.com; img-src data:; base-uri 'none'; form-action 'none'; frame-ancestors 'none'",
        ]);
    }
}
