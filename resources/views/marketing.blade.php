<!DOCTYPE html>
@php
    $currentLocale = $currentLocale ?? app()->getLocale();
    $siteLocales = $siteLocales ?? config('site_locales.supported', []);
    $seoCanonicalUrl = $seoCanonicalUrl ?? url()->current();
    $seoAlternateUrls = $seoAlternateUrls ?? [];
    $seoXDefaultUrl = $seoXDefaultUrl ?? url()->current();
    $localizedLandingUrls = $localizedLandingUrls ?? [];

    $heroPoints = is_array(__('landing.hero_points')) ? __('landing.hero_points') : [];
    $heroMetrics = is_array(__('landing.hero_metrics')) ? __('landing.hero_metrics') : [];
    $heroChips = is_array(__('landing.hero_chips')) ? __('landing.hero_chips') : [];

    $featureItems = is_array(__('landing.features.items')) ? __('landing.features.items') : [];
    $segmentsItems = is_array(__('landing.segments.items')) ? __('landing.segments.items') : [];
    $galleryItems = is_array(__('landing.segments.gallery_items')) ? __('landing.segments.gallery_items') : [];
    $ownerItems = is_array(__('landing.ops.owner_items')) ? __('landing.ops.owner_items') : [];
    $cycleItems = is_array(__('landing.ops.cycle_items')) ? __('landing.ops.cycle_items') : [];
    $futureItems = is_array(__('landing.future.items')) ? __('landing.future.items') : [];
    $pricingBasicItems = is_array(__('landing.pricing.basic.items')) ? __('landing.pricing.basic.items') : [];
    $pricingProItems = is_array(__('landing.pricing.pro.items')) ? __('landing.pricing.pro.items') : [];

    $currentLocaleMeta = $siteLocales[$currentLocale] ?? ['native' => strtoupper($currentLocale), 'flag' => '🌐'];
@endphp
<html lang="{{ str_replace('_', '-', $currentLocale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('landing.seo.title') }}</title>
    <meta name="description" content="{{ __('landing.seo.description') }}">
    <link rel="canonical" href="{{ $seoCanonicalUrl }}">
    @foreach ($seoAlternateUrls as $hreflang => $href)
        <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ $seoXDefaultUrl }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ __('landing.seo.title') }}">
    <meta property="og:description" content="{{ __('landing.seo.description') }}">
    <meta property="og:url" content="{{ $seoCanonicalUrl }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ __('landing.seo.title') }}">
    <meta name="twitter:description" content="{{ __('landing.seo.description') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800" rel="stylesheet" />
    <link href="https://fonts.bunny.net/css?family=fraunces:500,600,700" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/esticly-landing.css') }}">
    @php
        $brandIconCandidates = ['brand/esticly-icon.png', 'icon.png'];
        $brandLogoCandidates = ['brand/esticly-logo.png', 'logo.png'];
        $faviconIcoCandidates = ['brand/favicon.ico'];
        $favicon32Candidates = ['brand/favicon-32x32.png', 'icon.png'];
        $favicon16Candidates = ['brand/favicon-16x16.png', 'icon.png'];
        $appleTouchCandidates = ['brand/apple-touch-icon.png', 'icon.png'];

        $pickAsset = function (array $candidates): ?string {
            foreach ($candidates as $rel) {
                if (file_exists(public_path($rel))) {
                    return $rel;
                }
            }
            return null;
        };

        $brandLogoRel = $pickAsset($brandLogoCandidates);
        $faviconIcoRel = $pickAsset($faviconIcoCandidates);
        $favicon32Rel = $pickAsset($favicon32Candidates);
        $favicon16Rel = $pickAsset($favicon16Candidates);
        $appleTouchRel = $pickAsset($appleTouchCandidates);
    @endphp
    @if ($faviconIcoRel)
        <link rel="icon" href="{{ asset($faviconIcoRel) }}" sizes="any">
    @endif
    @if ($favicon32Rel)
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset($favicon32Rel) }}">
    @endif
    @if ($favicon16Rel)
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset($favicon16Rel) }}">
    @endif
    @if ($appleTouchRel)
        <link rel="apple-touch-icon" href="{{ asset($appleTouchRel) }}">
    @endif
</head>
<body>
<nav class="nav">
    <div class="wrap nav-inner">
        <a href="{{ $localizedLandingUrls[$currentLocale] ?? route('marketing.root') }}" class="brand">
            @if ($brandLogoRel)
                <img class="brand-wordmark-img" src="{{ asset($brandLogoRel) }}" alt="{{ config('app.name', 'Esticly') }}">
            @else
                <span class="brand-name-text">{{ config('app.name', 'Esticly') }}</span>
            @endif
        </a>
        <div class="nav-links">
            <a class="nav-link" href="#product">{{ __('landing.nav.product') }}</a>
            <a class="nav-link" href="#mobile">{{ __('landing.nav.mobile') }}</a>
            <a class="nav-link" href="#services">{{ __('landing.nav.services') }}</a>
            <a class="nav-link" href="#ops">{{ __('landing.nav.ops') }}</a>
            <a class="nav-link" href="#pricing">{{ __('landing.nav.pricing') }}</a>
        </div>
        <div class="nav-actions">
            <details class="lang-switcher">
                <summary class="lang-switcher__summary" aria-label="{{ __('landing.locale.switcher') }}">
                    <span class="lang-switcher__flag">{{ $currentLocaleMeta['flag'] ?? '🌐' }}</span>
                    <span class="lang-switcher__summary-text">{{ $currentLocaleMeta['native'] ?? strtoupper($currentLocale) }}</span>
                    <span class="lang-switcher__chevron" aria-hidden="true">▾</span>
                </summary>
                <div class="lang-switcher__menu">
                    <div class="lang-switcher__menu-title">{{ __('landing.locale.switcher') }}</div>
                    @foreach ($siteLocales as $code => $localeMeta)
                        <a class="lang-switcher__item {{ $code === $currentLocale ? 'is-active' : '' }}"
                           href="{{ $localizedLandingUrls[$code] ?? route('marketing.localized', ['locale' => $code]) }}"
                           hreflang="{{ $localeMeta['hreflang'] ?? $code }}"
                           lang="{{ $code }}"
                           @if($code === $currentLocale) aria-current="true" @endif>
                            <span class="lang-switcher__item-flag">{{ $localeMeta['flag'] ?? '🌐' }}</span>
                            <span class="lang-switcher__item-label">{{ $localeMeta['native'] ?? strtoupper($code) }}</span>
                        </a>
                    @endforeach
                </div>
            </details>
            @if (Route::has('login'))<a class="btn btn-soft" href="{{ route('login') }}">{{ __('landing.nav.login') }}</a>@endif
            <a class="btn btn-primary" href="#cta">{{ __('landing.nav.try') }}</a>
        </div>
    </div>
</nav>

<main class="wrap">
    <section class="launch-banner card" id="store-waitlist">
        <div class="launch-banner__copy">
            <span class="launch-pill">{{ __('landing.launch.badge') }}</span>
            <h2>{{ __('landing.launch.title') }}</h2>
            <p>{{ __('landing.launch.subtitle') }}</p>
        </div>
        <form method="POST" action="{{ route('marketing.waitlist') }}" class="launch-form">
            @csrf
            <input type="hidden" name="locale" value="{{ $currentLocale }}">
            <label>
                <span>{{ __('landing.waitlist.email_label') }}</span>
                <input type="email" name="email" value="{{ old('email') }}" placeholder="{{ __('landing.waitlist.email_placeholder') }}" required>
            </label>
            <label>
                <span>{{ __('landing.waitlist.phone_label') }}</span>
                <input type="text" name="phone" value="{{ old('phone') }}" placeholder="{{ __('landing.waitlist.phone_placeholder') }}" required>
            </label>
            <button type="submit" class="btn btn-primary">{{ __('landing.waitlist.submit') }}</button>
        </form>
        @if (session('waitlist_success'))
            <div class="launch-form-msg is-success">{{ session('waitlist_success') }}</div>
        @endif
        @if ($errors->any())
            <div class="launch-form-msg is-error">{{ $errors->first() }}</div>
        @endif
    </section>

    <section class="hero" id="product">
        <div class="hero-grid">
            <div class="card hero-copy">
                <div class="eyebrow">{{ __('landing.hero.eyebrow') }}</div>
                <h1>{{ __('landing.hero.title') }}</h1>
                <p class="lead">{{ __('landing.hero.lead') }}</p>
                <div class="hero-actions">
                    <a class="btn btn-primary" href="#cta">{{ __('landing.hero.cta_primary') }}</a>
                    <a class="btn" href="#mobile">{{ __('landing.hero.cta_secondary') }}</a>
                </div>
                <div class="store-buttons" aria-label="{{ __('landing.hero.store_aria') }}">
                    <a class="store-btn" href="#store-waitlist" aria-label="{{ __('landing.hero.app_store_aria') }}">
                        <span class="store-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16.37 12.12c.02 2.35 2.06 3.14 2.08 3.15-.02.05-.32 1.11-1.05 2.19-.63.93-1.29 1.85-2.32 1.87-1.02.02-1.35-.61-2.52-.61-1.17 0-1.53.59-2.5.63-1 .04-1.77-1-2.4-1.92-1.29-1.86-2.27-5.25-.95-7.53.65-1.13 1.82-1.85 3.1-1.87.97-.02 1.88.66 2.52.66.64 0 1.83-.82 3.08-.7.52.02 1.98.21 2.92 1.59-.08.05-1.74 1.01-1.72 2.54Zm-2.33-5.95c.53-.64.89-1.52.79-2.4-.77.03-1.71.51-2.26 1.15-.49.57-.92 1.46-.81 2.32.86.07 1.75-.44 2.28-1.07Z"/></svg>
                        </span>
                        <span class="store-copy"><small>{{ __('landing.hero.app_store_small') }}</small><strong>{{ __('landing.hero.app_store_label') }}</strong></span>
                    </a>
                    <a class="store-btn" href="#store-waitlist" aria-label="{{ __('landing.hero.play_store_aria') }}">
                        <span class="store-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3.3 2.2c-.2.2-.3.6-.3 1.1v17.4c0 .5.1.9.3 1.1l.1.1 9.8-9.8v-.2L3.4 2.1l-.1.1Zm14.3 6.5-3.2 3.2 3.2 3.2.1-.1 3.8-2.1c1.1-.6 1.1-1.5 0-2.1l-3.8-2.1-.1.1Zm-.9.5-11 11 .1.1c.3.2.7.2 1.2-.1l12.5-7-2.8-2.8Zm-9.7-8c-.5-.3-.9-.3-1.2-.1l-.1.1 11 11 2.8-2.8-12.5-7Z"/></svg>
                        </span>
                        <span class="store-copy"><small>{{ __('landing.hero.play_store_small') }}</small><strong>{{ __('landing.hero.play_store_label') }}</strong></span>
                    </a>
                </div>
                <p class="store-note">{{ __('landing.hero.store_note') }}</p>
                <div class="hero-points">
                    @foreach ($heroPoints as $point)
                        <div class="mini"><strong>{{ $point['value'] ?? '' }}</strong><span>{{ $point['label'] ?? '' }}</span></div>
                    @endforeach
                </div>
                <div class="metric-row">
                    @foreach ($heroMetrics as $metric)
                        <div class="metric"><strong>{{ $metric['title'] ?? '' }}</strong><span>{{ $metric['label'] ?? '' }}</span></div>
                    @endforeach
                </div>
            </div>

            <div class="card hero-visual" id="mobile">
                <div class="chip-row">
                    @foreach ($heroChips as $chip)
                        <span class="chip">{{ $chip }}</span>
                    @endforeach
                </div>
                <div>
                    <div class="phone-stage">
                        <div class="phone">
                            <div class="screen screen-image">
                                <img src="{{ asset('img/calendar.png') }}" alt="Esticly mobile calendar screen" loading="lazy">
                            </div>
                        </div>
                        <div class="phone tall">
                            <div class="screen screen-image">
                                <img src="{{ asset('img/client.png') }}" alt="Esticly mobile client card screen" loading="lazy">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="services">
        <h2 class="section-title">{{ __('landing.features.title') }}</h2>
        <p class="section-sub">{{ __('landing.features.subtitle') }}</p>
        <div class="grid-3">
            @foreach ($featureItems as $feature)
                <article class="feature">
                    <div class="icon">{{ $feature['icon'] ?? '•' }}</div>
                    <h3>{{ $feature['title'] ?? '' }}</h3>
                    <p>{{ $feature['description'] ?? '' }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="section">
        <div class="split">
            <div class="card list-card">
                <h2 class="section-title" style="font-size:30px;margin-bottom:6px;">{{ __('landing.segments.title') }}</h2>
                <p class="section-sub">{{ __('landing.segments.subtitle') }}</p>
                <ul>
                    @foreach ($segmentsItems as $index => $item)
                        <li>
                            <span class="bullet">{{ $index + 1 }}</span>
                            <span>
                                <strong style="color:var(--text)">{{ $item['title'] ?? '' }}</strong><br>
                                {{ $item['description'] ?? '' }}
                            </span>
                        </li>
                    @endforeach
                </ul>
                <figure class="inline-phone-preview">
                    <div class="inline-phone-preview__media">
                        <img src="{{ asset('img/calendar.png') }}" alt="Esticly calendar" loading="lazy">
                    </div>
                    <figcaption>
                        <strong>{{ __('landing.segments.preview_title') }}</strong>
                        {{ __('landing.segments.preview_description') }}
                    </figcaption>
                </figure>
            </div>
            <div class="card gallery-card">
                @foreach ($galleryItems as $item)
                    <figure class="shot-card shot-card-compact">
                        <img src="{{ asset($item['image'] ?? 'img/client.png') }}" alt="{{ $item['title'] ?? 'Esticly screen' }}" loading="lazy">
                        <figcaption><strong>{{ $item['title'] ?? '' }}</strong>{{ $item['description'] ?? '' }}</figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section" id="ops">
        <h2 class="section-title">{{ __('landing.ops.title') }}</h2>
        <p class="section-sub">{{ __('landing.ops.subtitle') }}</p>
        <div class="ops-grid">
            <div class="card ops-card">
                <span class="tagline">{{ __('landing.ops.owner_tagline') }}</span>
                <ul class="checklist">
                    @foreach ($ownerItems as $item)
                        <li><span class="checkmark">✓</span><span>{{ $item }}</span></li>
                    @endforeach
                </ul>
            </div>
            <div class="card ops-card">
                <span class="tagline">{{ __('landing.ops.cycle_tagline') }}</span>
                <div class="timeline">
                    @foreach ($cycleItems as $item)
                        <div class="timeline-item">
                            <div class="time">{{ $item['step'] ?? '' }}</div>
                            <div class="box"><strong>{{ $item['title'] ?? '' }}</strong><span>{{ $item['description'] ?? '' }}</span></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <h2 class="section-title">{{ __('landing.future.title') }}</h2>
        <p class="section-sub">{{ __('landing.future.subtitle') }}</p>
        <div class="grid-2">
            @foreach ($futureItems as $item)
                <div class="usecase"><h3>{{ $item['title'] ?? '' }}</h3><p>{{ $item['description'] ?? '' }}</p></div>
            @endforeach
        </div>
    </section>

    <section class="section" id="pricing">
        <h2 class="section-title">{{ __('landing.pricing.title') }}</h2>
        <p class="section-sub">{{ __('landing.pricing.subtitle') }}</p>
        <div class="pricing">
            <div class="price-card">
                <div class="price-tag">{{ __('landing.pricing.basic.tag') }}</div>
                <div class="price-value">{{ __('landing.pricing.basic.price') }} <span>{{ __('landing.pricing.period') }}</span></div>
                <p class="section-sub" style="font-size:14px;">{{ __('landing.pricing.basic.description') }}</p>
                <ul>
                    @foreach ($pricingBasicItems as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="price-card featured">
                <div class="price-tag">{{ __('landing.pricing.pro.tag') }}</div>
                <div class="price-value">{{ __('landing.pricing.pro.price') }} <span>{{ __('landing.pricing.period') }}</span></div>
                <p class="section-sub" style="font-size:14px;">{{ __('landing.pricing.pro.description') }}</p>
                <ul>
                    @foreach ($pricingProItems as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
        <div class="note-card">{{ __('landing.pricing.note') }}</div>
    </section>

    <section class="section" id="cta">
        <div class="card cta">
            <div>
                <h2 class="section-title" style="margin:0;font-size:30px;">{{ __('landing.cta.title') }}</h2>
                <p>{{ __('landing.cta.description') }}</p>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;">
                @if (Route::has('register'))<a class="btn btn-primary" href="{{ route('register') }}">{{ __('landing.cta.register') }}</a>@endif
                @if (Route::has('login'))<a class="btn" href="{{ route('login') }}">{{ __('landing.cta.login') }}</a>@endif
            </div>
        </div>
    </section>

    <footer>
        {{ __('landing.footer') }}
    </footer>
</main>
</body>
</html>
