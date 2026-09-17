<?php

namespace App\Http\Middleware;

use App\Services\AppCheckRejected;
use App\Services\FirebaseAppCheck;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RequireOtpAppCheck
{
    public function handle(Request $request, Closure $next): Response
    {
        // Disabling attestation is only permitted on local/test backends.
        if (! config('app_check.enforce') && app()->environment(['local', 'testing'])) {
            return $next($request);
        }
        try {
            $appId = app(FirebaseAppCheck::class)->verifyAndConsume((string) $request->header('X-Firebase-AppCheck', ''));
        } catch (AppCheckRejected $e) {
            Log::notice('otp_app_check_rejected', ['reason' => $e->reason, 'ip' => $request->ip()]);

            return response()->json(['ok' => false, 'code' => 'app_check_required'], 403)
                ->header('Cache-Control', 'no-store');
        } catch (Throwable $e) {
            Log::error('otp_app_check_unavailable', ['exception_type' => $e::class]);

            return response()->json(['ok' => false, 'code' => 'app_check_unavailable', 'retry_after' => 60], 503)
                ->header('Retry-After', '60')->header('Cache-Control', 'no-store');
        }
        Log::info('otp_app_check_verified', ['app_id' => $appId, 'ip' => $request->ip()]);

        return $next($request);
    }
}
