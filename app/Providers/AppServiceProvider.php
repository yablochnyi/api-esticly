<?php

namespace App\Providers;

use App\Models\DsarOperation;
use App\Models\SupportThread;
use App\Models\Visit;
use App\Observers\VisitCalendarObserver;
use App\Policies\DsarOperationPolicy;
use App\Policies\SupportThreadPolicy;
use App\Support\AdminAccess;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Visit::observe(VisitCalendarObserver::class);

        if (str_starts_with((string) config('app.url', ''), 'https://')) {
            URL::forceScheme('https');
        }

        // OTP anti-bruteforce / anti-spam limits.
        RateLimiter::for('otp-send', function (Request $request) {
            $phone = self::phoneKey((string) $request->input('phone', ''));
            $ip = (string) $request->ip();

            return [
                Limit::perMinute(3)->by('otp-send:phone:'.$phone),
                Limit::perMinute(10)->by('otp-send:ip:'.$ip),
                Limit::perHour(20)->by('otp-send:phone-hour:'.$phone),
            ];
        });

        RateLimiter::for('otp-verify', function (Request $request) {
            $phone = self::phoneKey((string) $request->input('phone', ''));
            $ip = (string) $request->ip();

            return [
                Limit::perMinute(6)->by('otp-verify:phone:'.$phone),
                Limit::perMinute(20)->by('otp-verify:ip:'.$ip),
                Limit::perHour(50)->by('otp-verify:phone-hour:'.$phone),
            ];
        });

        // Public booking/review anti-spam limits.
        RateLimiter::for('public-booking-view', function (Request $request) {
            $ip = (string) $request->ip();
            $slug = trim((string) ($request->route('slug') ?? ''));
            $slugKey = $slug !== '' ? $slug : 'none';

            return [
                Limit::perMinute(60)->by('booking-view:ip:'.$ip),
                Limit::perMinute(20)->by('booking-view:slug:'.$slugKey.':ip:'.$ip),
            ];
        });

        RateLimiter::for('public-booking-submit', function (Request $request) {
            $ip = (string) $request->ip();
            $slug = trim((string) ($request->route('slug') ?? ''));
            $slugKey = $slug !== '' ? $slug : 'none';

            return [
                Limit::perMinute(8)->by('booking-submit:slug:'.$slugKey.':ip:'.$ip),
                Limit::perHour(40)->by('booking-submit-hour:slug:'.$slugKey.':ip:'.$ip),
            ];
        });

        RateLimiter::for('public-review-view', function (Request $request) {
            $ip = (string) $request->ip();
            $slug = trim((string) ($request->route('slug') ?? ''));
            $slugKey = $slug !== '' ? $slug : 'none';

            return [
                Limit::perMinute(30)->by('review-view:slug:'.$slugKey.':ip:'.$ip),
            ];
        });

        RateLimiter::for('public-review-submit', function (Request $request) {
            $ip = (string) $request->ip();
            $slug = trim((string) ($request->route('slug') ?? ''));
            $slugKey = $slug !== '' ? $slug : 'none';

            return [
                Limit::perMinute(6)->by('review-submit:slug:'.$slugKey.':ip:'.$ip),
                Limit::perHour(24)->by('review-submit-hour:slug:'.$slugKey.':ip:'.$ip),
            ];
        });

        RateLimiter::for('waitlist-subscribe', function (Request $request) {
            $ip = (string) $request->ip();
            $email = mb_strtolower(trim((string) $request->input('email', '')));
            $emailKey = $email !== '' ? $email : 'empty';

            return [
                Limit::perMinute(8)->by('waitlist:ip:'.$ip),
                Limit::perHour(20)->by('waitlist:email:'.$emailKey),
            ];
        });

        // Restrict admin tools (Filament / Log Viewer / Horizon) by role.
        Gate::define('access-filament-admin', function ($user = null): bool {
            return AdminAccess::allows($user);
        });
        Gate::policy(DsarOperation::class, DsarOperationPolicy::class);
        Gate::policy(SupportThread::class, SupportThreadPolicy::class);

        $isAllowed = function (): bool {
            $u = Auth::user();
            return AdminAccess::allows($u);
        };

        // Log viewer auth (used by AuthorizeLogViewer middleware).
        LogViewer::auth(fn ($request) => $isAllowed());
    }

    private static function phoneKey(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', trim($raw));
        if (is_string($digits) && $digits !== '') {
            return $digits;
        }
        return 'empty';
    }
}
