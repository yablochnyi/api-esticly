<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\App;
use App\Http\Controllers\Admin\DsarExportController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\PublicLaunchWaitlistController;
use App\Http\Controllers\PublicReviewController;
use App\Http\Controllers\PublicShortLinkController;
use App\Support\PublicLocale;
use App\Support\PublicPageViewData;

$siteLocales = config('site_locales.supported', []);
$siteLocaleCodes = array_keys($siteLocales);
$siteLocalePattern = implode('|', array_map('preg_quote', $siteLocaleCodes));
$siteDefaultLocale = config('site_locales.default', 'pl');
$siteXDefaultLocale = config('site_locales.x_default', $siteDefaultLocale);

$buildLandingSeo = function (string $locale) use ($siteLocales, $siteLocaleCodes, $siteXDefaultLocale): array {
    $localizedUrls = [];
    foreach ($siteLocaleCodes as $code) {
        $localizedUrls[$code] = route('marketing.localized', ['locale' => $code]);
    }

    $alternateUrls = [];
    foreach ($siteLocaleCodes as $code) {
        $alternateUrls[$siteLocales[$code]['hreflang'] ?? $code] = $localizedUrls[$code];
    }

    return [
        'localized_urls' => $localizedUrls,
        'alternate_urls' => $alternateUrls,
        'canonical_url' => $localizedUrls[$locale] ?? route('marketing.localized', ['locale' => $locale]),
        'x_default_url' => $localizedUrls[$siteXDefaultLocale] ?? route('marketing.localized', ['locale' => $siteXDefaultLocale]),
    ];
};

$renderMarketing = function (string $locale) use ($siteLocales, $siteLocaleCodes, $buildLandingSeo) {
    if (! in_array($locale, $siteLocaleCodes, true)) {
        abort(404);
    }

    App::setLocale($locale);

    $seo = $buildLandingSeo($locale);

    return view('marketing', PublicPageViewData::marketing($locale, $siteLocales, $seo));
};

$resolvePublicLocale = function (\Illuminate\Http\Request $request): string {
    return PublicLocale::resolve($request);
};

$buildLegalAlternates = function (string $routeName) use ($siteLocales, $siteLocaleCodes): array {
    $alternateUrls = [];
    foreach ($siteLocaleCodes as $code) {
        $alternateUrls[$siteLocales[$code]['hreflang'] ?? $code] = route($routeName, ['locale' => $code]);
    }

    return $alternateUrls;
};

$renderLegalPage = function (string $page, string $locale) use ($siteLocales, $siteLocaleCodes, $siteXDefaultLocale, $buildLegalAlternates) {
    if (! in_array($locale, $siteLocaleCodes, true)) {
        abort(404);
    }

    App::setLocale($locale);

    $routeName = $page === 'privacy' ? 'legal.privacy.localized' : 'legal.terms.localized';
    $otherRouteName = $page === 'privacy' ? 'legal.terms.localized' : 'legal.privacy.localized';
    $dataFile = $page === 'privacy'
        ? resource_path('data/legal_privacy.php')
        : resource_path('data/legal_terms.php');
    $lang = $locale;
    $pageData = require $dataFile;
    $title = $pageData['titles'][$locale] ?? $pageData['titles']['en'];
    $copy = $pageData['content'][$locale] ?? $pageData['content']['en'];

    $languageSwitcherUrls = [];
    foreach ($siteLocaleCodes as $code) {
        $languageSwitcherUrls[$code] = route($routeName, ['locale' => $code]);
    }

    $headerNavLinks = [
        ['label' => __('landing.nav.features'), 'url' => route('marketing.localized', ['locale' => $locale]) . '#features'],
        ['label' => __('landing.nav.pricing'), 'url' => route('marketing.localized', ['locale' => $locale]) . '#pricing'],
        [
            'label' => $page === 'privacy'
                ? ($pageData['termsLabel'][$locale] ?? $pageData['termsLabel']['en'])
                : ($pageData['privacyLabel'][$locale] ?? $pageData['privacyLabel']['en']),
            'url' => route($otherRouteName, ['locale' => $locale]),
        ],
        ['label' => $title, 'url' => route($routeName, ['locale' => $locale])],
    ];

    return view("legal.{$page}", array_merge(
        PublicPageViewData::legal(
            locale: $locale,
            siteLocales: $siteLocales,
            canonicalUrl: route($routeName, ['locale' => $locale]),
            xDefaultUrl: route($routeName, ['locale' => $siteXDefaultLocale]),
            languageSwitcherUrls: $languageSwitcherUrls,
            alternateUrls: $buildLegalAlternates($routeName),
            seoTitle: $title,
            seoDescription: "{$title} - Esticly",
            headerNavLinks: $headerNavLinks,
        ),
        [
            'lang' => $locale,
            'title' => $title,
            'copy' => $copy,
            'updated' => $pageData['updated'],
            'email' => $pageData['email'],
            'allLangs' => $pageData['allLangs'],
            'languageSwitcherUrls' => $languageSwitcherUrls,
            'metaUpdatedLabel' => $pageData['metaUpdated'][$locale] ?? $pageData['metaUpdated']['en'],
        ]
    ));
};

Route::get('/', function (\Illuminate\Http\Request $request) use ($resolvePublicLocale) {
    return redirect()->route('marketing.localized', ['locale' => $resolvePublicLocale($request)], 301);
})->name('marketing.root');

Route::post('/waitlist', [PublicLaunchWaitlistController::class, 'store'])
    ->middleware('throttle:waitlist-subscribe')
    ->name('marketing.waitlist');

Route::get('/sitemap.xml', function () use ($siteLocaleCodes, $siteLocales, $siteXDefaultLocale) {
    $pageRoutes = [
        'landing' => [
            'route' => 'marketing.localized',
            'lastmod' => '2026-05-05',
        ],
        'privacy' => [
            'route' => 'legal.privacy.localized',
            'lastmod' => '2026-02-19',
        ],
        'terms' => [
            'route' => 'legal.terms.localized',
            'lastmod' => '2026-02-19',
        ],
        'account_deletion' => [
            'route' => 'legal.account-deletion.localized',
            'lastmod' => '2026-02-19',
        ],
    ];

    $pages = [];

    foreach ($pageRoutes as $page) {
        $alternates = [];

        foreach ($siteLocaleCodes as $code) {
            $alternates[] = [
                'hreflang' => $siteLocales[$code]['hreflang'] ?? $code,
                'href' => route($page['route'], ['locale' => $code]),
            ];
        }

        $alternates[] = [
            'hreflang' => 'x-default',
            'href' => route($page['route'], ['locale' => $siteXDefaultLocale]),
        ];

        foreach ($siteLocaleCodes as $code) {
            $pages[] = [
                'loc' => route($page['route'], ['locale' => $code]),
                'alternates' => $alternates,
                'lastmod' => $page['lastmod'],
            ];
        }
    }

    return response()
        ->view('sitemap.index', ['pages' => $pages])
        ->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('sitemap.xml');

Route::get('/{locale}', function (string $locale) use ($renderMarketing) {
    return $renderMarketing($locale);
})->where('locale', $siteLocalePattern)->name('marketing.localized');

Route::get('/privacy', function (\Illuminate\Http\Request $request) use ($resolvePublicLocale, $renderLegalPage) {
    return $renderLegalPage('privacy', $resolvePublicLocale($request));
})->name('legal.privacy');

Route::get('/{locale}/privacy', function (string $locale) use ($renderLegalPage) {
    return $renderLegalPage('privacy', $locale);
})->where('locale', $siteLocalePattern)->name('legal.privacy.localized');

Route::get('/terms', function (\Illuminate\Http\Request $request) use ($resolvePublicLocale, $renderLegalPage) {
    return $renderLegalPage('terms', $resolvePublicLocale($request));
})->name('legal.terms');

Route::get('/{locale}/terms', function (string $locale) use ($renderLegalPage) {
    return $renderLegalPage('terms', $locale);
})->where('locale', $siteLocalePattern)->name('legal.terms.localized');

Route::get('/delete-account', function (\Illuminate\Http\Request $request) use ($resolvePublicLocale) {
    $lang = $resolvePublicLocale($request);

    return view('legal.account-deletion', [
        'lang' => $lang,
        'requested_lang' => $lang,
    ]);
})->name('legal.account-deletion');

Route::get('/{locale}/delete-account', function (string $locale) use ($siteLocaleCodes) {
    if (! in_array($locale, $siteLocaleCodes, true)) {
        abort(404);
    }
    App::setLocale($locale);
    return view('legal.account-deletion', ['lang' => $locale, 'requested_lang' => $locale]);
})->where('locale', $siteLocalePattern)->name('legal.account-deletion.localized');

// Public booking (path-based)
Route::get('/@{slug}', [PublicBookingController::class, 'book'])->middleware('throttle:public-booking-view')->name('booking.landing');
Route::get('/@{slug}/book', function (string $slug, \Illuminate\Http\Request $request) {
    $params = ['slug' => $slug];

    if ($request->has('lang')) {
        $params['lang'] = $request->query('lang');
    }

    if ($request->has('service_id')) {
        $params['service_id'] = $request->query('service_id');
    }

    return redirect()->route('booking.landing', $params, 301);
})->middleware('throttle:public-booking-view')->name('booking.book');
Route::get('/@{slug}/staff', [PublicBookingController::class, 'staff'])->middleware('throttle:public-booking-view')->name('booking.staff');
Route::get('/@{slug}/availability', [PublicBookingController::class, 'availability'])->middleware('throttle:public-booking-view')->name('booking.availability');
Route::get('/@{slug}/slots', [PublicBookingController::class, 'slots'])->middleware('throttle:public-booking-view')->name('booking.slots');
Route::get('/@{slug}/promo/validate', [PublicBookingController::class, 'promoValidate'])->middleware('throttle:public-booking-view')->name('booking.promo.validate');
Route::post('/@{slug}/book', [PublicBookingController::class, 'submit'])->middleware('throttle:public-booking-submit')->name('booking.submit');

Route::get('/b/{slug}', [PublicBookingController::class, 'landing'])->middleware('throttle:public-booking-view');
Route::get('/b/{slug}/book', [PublicBookingController::class, 'book'])->middleware('throttle:public-booking-view');
Route::get('/b/{slug}/staff', [PublicBookingController::class, 'staff'])->middleware('throttle:public-booking-view');
Route::get('/b/{slug}/availability', [PublicBookingController::class, 'availability'])->middleware('throttle:public-booking-view');
Route::get('/b/{slug}/slots', [PublicBookingController::class, 'slots'])->middleware('throttle:public-booking-view');
Route::get('/b/{slug}/promo/validate', [PublicBookingController::class, 'promoValidate'])->middleware('throttle:public-booking-view');
Route::post('/b/{slug}/book', [PublicBookingController::class, 'submit'])->middleware('throttle:public-booking-submit');

// Short links
Route::get('/s/{code}', [PublicShortLinkController::class, 'go'])->name('short.go');

// Public reviews (by booking_slug)
Route::get('/r/{slug}', [PublicReviewController::class, 'show'])->middleware('throttle:public-review-view')->name('review.form');
Route::post('/r/{slug}', [PublicReviewController::class, 'submit'])->middleware('throttle:public-review-submit')->name('review.submit');

// Optional subdomain-based booking: https://{slug}.YOUR_DOMAIN
$baseDomain = env('BOOKING_BASE_DOMAIN');
if ($baseDomain) {
    Route::domain('{slug}.' . $baseDomain)->group(function () {
        Route::get('/', [PublicBookingController::class, 'landing'])->middleware('throttle:public-booking-view')->name('booking.domain.landing');
        Route::get('/book', [PublicBookingController::class, 'book'])->middleware('throttle:public-booking-view')->name('booking.domain.book');
        Route::get('/staff', [PublicBookingController::class, 'staff'])->middleware('throttle:public-booking-view')->name('booking.domain.staff');
        Route::get('/availability', [PublicBookingController::class, 'availability'])->middleware('throttle:public-booking-view')->name('booking.domain.availability');
        Route::get('/slots', [PublicBookingController::class, 'slots'])->middleware('throttle:public-booking-view')->name('booking.domain.slots');
        Route::get('/promo/validate', [PublicBookingController::class, 'promoValidate'])->middleware('throttle:public-booking-view')->name('booking.domain.promo.validate');
        Route::post('/book', [PublicBookingController::class, 'submit'])->middleware('throttle:public-booking-submit')->name('booking.domain.submit');
        Route::get('/r', [PublicReviewController::class, 'show'])->middleware('throttle:public-review-view')->name('review.domain.form');
        Route::post('/r', [PublicReviewController::class, 'submit'])->middleware('throttle:public-review-submit')->name('review.domain.submit');
    });
}

Route::middleware('auth')->prefix('admin/dsar')->group(function () {
    Route::get('/export/client', [DsarExportController::class, 'client'])->name('admin.dsar.export.client');
    Route::get('/export/clients', [DsarExportController::class, 'allClients'])->name('admin.dsar.export.clients');
    Route::get('/export/org', [DsarExportController::class, 'organization'])->name('admin.dsar.export.org');
});
