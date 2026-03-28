<?php

use App\Http\Controllers\Api\Mobile\AnalyticsController;
use App\Http\Controllers\Api\Mobile\AppleAppStoreNotificationsController;
use App\Http\Controllers\Api\Mobile\AuthController;
use App\Http\Controllers\Api\Mobile\BillingController;
use App\Http\Controllers\Api\Mobile\ClientController;
use App\Http\Controllers\Api\Mobile\GooglePlayRtdnController;
use App\Http\Controllers\Api\Mobile\ClientDsarController;
use App\Http\Controllers\Api\Mobile\ClientNoteController;
use App\Http\Controllers\Api\Mobile\CurrencyController;
use App\Http\Controllers\Api\Mobile\DashboardController;
use App\Http\Controllers\Api\Mobile\DeviceController;
use App\Http\Controllers\Api\Mobile\MarketingAutomationController;
use App\Http\Controllers\Api\Mobile\ProfileController;
use App\Http\Controllers\Api\Mobile\OnlineBookingController;
use App\Http\Controllers\Api\Mobile\PortfolioController;
use App\Http\Controllers\Api\Mobile\RegisterController;
use App\Http\Controllers\Api\Mobile\ReviewController;
use App\Http\Controllers\Api\Mobile\PromoCodeController;
use App\Http\Controllers\Api\Mobile\ServiceController;
use App\Http\Controllers\Api\Mobile\StaffController;
use App\Http\Controllers\Api\Mobile\SupportController;
use App\Http\Controllers\Api\Mobile\VisitController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile')->group(function () {
    // public
    Route::post('/auth/send-otp', [AuthController::class, 'sendOtp'])->middleware('throttle:otp-send');
    Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:otp-verify');
    Route::get('/currencies', [CurrencyController::class, 'index']);
    Route::post('/billing/google/rtdn', GooglePlayRtdnController::class);
    Route::post('/billing/apple/notifications', AppleAppStoreNotificationsController::class);

    // protected
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/register', [RegisterController::class, 'store']);
        Route::post('/billing/google/verify', [BillingController::class, 'verifyGooglePlay']);
        Route::post('/billing/apple/verify', [BillingController::class, 'verifyAppleAppStore']);
        Route::post('/billing/google/sync', [BillingController::class, 'syncGooglePlay']);

        Route::get('/clients', [ClientController::class, 'index']);
        Route::post('/clients', [ClientController::class, 'store']);
        Route::get('/clients/{client}', [ClientController::class, 'show']);
        Route::patch('/clients/{client}', [ClientController::class, 'update']);
        Route::post('/clients/{client}/export', [ClientDsarController::class, 'export']);
        Route::post('/clients/{client}/anonymize', [ClientDsarController::class, 'anonymize']);

        Route::get('/clients/{client}/notes', [ClientNoteController::class, 'index']);
        Route::post('/clients/{client}/notes', [ClientNoteController::class, 'store']);
        Route::patch('/client-notes/{note}', [ClientNoteController::class, 'update']);

        Route::get('/me', [ProfileController::class, 'me']);
        Route::get('/me/staff', [ProfileController::class, 'staffMe']);
        Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
        Route::post('/profile', [ProfileController::class, 'update']);
        Route::post('/devices', [DeviceController::class, 'store']);

        Route::get('/services', [ServiceController::class, 'index']);
        Route::post('/services', [ServiceController::class, 'store']);
        Route::get('/services/{service}', [ServiceController::class, 'show']);
        Route::patch('/services/{service}', [ServiceController::class, 'update']);

        Route::patch('/profile/currency', [ProfileController::class, 'updateCurrency']);
        Route::patch('/profile/language', [ProfileController::class, 'updateLanguage']);

        Route::get('/staff', [StaffController::class, 'index']);
        Route::post('/staff', [StaffController::class, 'store']);
        Route::get('/staff/{staff}', [StaffController::class, 'show']);
        Route::patch('/staff/{staff}', [StaffController::class, 'update']);
        Route::delete('/staff/{staff}', [StaffController::class, 'destroy']);

        Route::get('/profile/schedule', [ProfileController::class, 'schedule']);
        Route::patch('/profile/schedule', [ProfileController::class, 'updateSchedule']);
        Route::get('/profile/reminders', [ProfileController::class, 'reminders']);
        Route::patch('/profile/reminders', [ProfileController::class, 'updateReminders']);
        Route::get('/profile/online-booking', [OnlineBookingController::class, 'show']);
        Route::patch('/profile/online-booking', [OnlineBookingController::class, 'update']);

        Route::get('/reviews', [ReviewController::class, 'index']);

        Route::get('/promo-codes', [PromoCodeController::class, 'index']);
        Route::put('/promo-codes/{code}', [PromoCodeController::class, 'upsert']);
        Route::delete('/promo-codes/{code}', [PromoCodeController::class, 'destroy']);
        Route::post('/promo-codes/validate', [PromoCodeController::class, 'validateCode']);

        Route::get('/support/thread', [SupportController::class, 'thread']);
        Route::get('/support/messages', [SupportController::class, 'messages']);
        Route::post('/support/messages', [SupportController::class, 'send']);
        Route::get('/support/threads', [SupportController::class, 'threads']);
        Route::post('/support/threads', [SupportController::class, 'createThread']);
        Route::get('/support/threads/{threadId}/messages', [SupportController::class, 'threadMessages']);
        Route::post('/support/threads/{threadId}/messages', [SupportController::class, 'sendToThread']);

        Route::get('/marketing/automations', [MarketingAutomationController::class, 'index']);
        Route::put('/marketing/automations/{id}', [MarketingAutomationController::class, 'upsert']);

        Route::get('/visits', [VisitController::class, 'index']);
        Route::get('/visits/counts', [VisitController::class, 'counts']);
        Route::post('/visits', [VisitController::class, 'store']);
        Route::get('/visits/{visit}', [VisitController::class, 'show']);
        Route::get('/visits/{visit}/agreement', [VisitController::class, 'agreement']);
        Route::post('/visits/{visit}/agreement/sign', [VisitController::class, 'signAgreement']);
        Route::match(['PATCH', 'POST'], '/visits/{visit}', [VisitController::class, 'update']);

        Route::get('/portfolio', [PortfolioController::class, 'index']);
        Route::post('/portfolio', [PortfolioController::class, 'store']);
        Route::delete('/portfolio/{photo}', [PortfolioController::class, 'destroy']);

        Route::get('/staff/{staff}/schedule', [StaffController::class, 'schedule']);
        Route::patch('/staff/{staff}/schedule', [StaffController::class, 'updateSchedule']);
        Route::get('/staff/{staff}/timeoffs', [StaffController::class, 'timeOffs']);
        Route::post('/staff/{staff}/timeoffs', [StaffController::class, 'upsertTimeOff']);
        Route::delete('/staff/{staff}/timeoffs', [StaffController::class, 'deleteTimeOff']);

        Route::get('/analytics', [AnalyticsController::class, 'index']);
    });
});
