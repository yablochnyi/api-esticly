@php
    $faviconIco = file_exists(public_path('favicon.ico')) ? asset('favicon.ico') : null;
    $iconPng = file_exists(public_path('icon.png')) ? asset('icon.png') : null;
    $logoPng = file_exists(public_path('logo.png')) ? asset('logo.png') : null;
    $appleTouchIcon = $iconPng ?? $logoPng;
    $socialImage = $logoPng ?? $iconPng;
    $languages = $siteLocales ?? config('site_locales.supported', []);
    $currentLocale = $currentLocale ?? app()->getLocale();
    $localizedLandingUrls = $localizedLandingUrls ?? [];
    $seoAlternateUrls = $seoAlternateUrls ?? [];
    $seoCanonicalUrl = $seoCanonicalUrl ?? url()->current();
    $seoXDefaultUrl = $seoXDefaultUrl ?? $seoCanonicalUrl;
    $heroImagePath = "assets/public/hero-{$currentLocale}.png";
    $heroImage = file_exists(public_path($heroImagePath))
        ? asset($heroImagePath)
        : asset('assets/public/hero-uk.png');
    $reviews = __('landing.reviews.items');
    $faqItems = __('landing.faq.items');
    $featureItems = __('landing.features.items');
    $proItems = __('landing.audience.pros_items');
    $salonItems = __('landing.audience.salons_items');
    $basicItems = __('landing.pricing.basic_items');
    $proPlanItems = __('landing.pricing.pro_items');
@endphp
<!doctype html>
<html lang="{{ $currentLocale }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ __('landing.seo.title') }}</title>
    <meta name="description" content="{{ __('landing.seo.description') }}" />
    <link rel="canonical" href="{{ $seoCanonicalUrl }}" />
    @foreach($seoAlternateUrls as $hreflang => $href)
        <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}" />
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ $seoXDefaultUrl }}" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="{{ __('landing.seo.title') }}" />
    <meta property="og:description" content="{{ __('landing.seo.description') }}" />
    <meta property="og:url" content="{{ $seoCanonicalUrl }}" />
    @if($socialImage)
        <meta property="og:image" content="{{ $socialImage }}" />
    @endif
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ __('landing.seo.title') }}" />
    <meta name="twitter:description" content="{{ __('landing.seo.description') }}" />
    @if($socialImage)
        <meta name="twitter:image" content="{{ $socialImage }}" />
    @endif
    @if($faviconIco)
        <link rel="icon" href="{{ $faviconIco }}" sizes="any" />
    @endif
    @if($iconPng)
        <link rel="icon" type="image/png" href="{{ $iconPng }}" />
    @endif
    @if($appleTouchIcon)
        <link rel="apple-touch-icon" href="{{ $appleTouchIcon }}" />
    @endif
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/src/style.css') }}">
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-N67PPKX0S8"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-N67PPKX0S8');
    </script>
</head>
<body>

<header class="header">
    <div class="header__container">
        <button class="header__menu-btn" id="menuBtn">
            <img src="{{ asset('assets/public/burger-mobile.svg') }}" alt="">
        </button>

        <div class="header__logo">
            <img class="header__logo__inner" src="{{ asset('assets/public/logo.svg') }}" alt="Logo" >
        </div>

        <nav class="header__nav" id="mobileMenu">
            <button class="header__close-btn" id="closeBtn"></button>
            <a href="#features" class="header__link">{{ __('landing.nav.features') }}</a>
            <a href="#for-professionals" class="header__link">{{ __('landing.nav.professionals') }}</a>
            <a href="#for-salons" class="header__link">{{ __('landing.nav.salons') }}</a>
            <a href="#pricing" class="header__link">{{ __('landing.nav.pricing') }}</a>
            <a href="#faq" class="header__link">{{ __('landing.nav.faq') }}</a>
            <button class="header__btn header__btn--mobile">{{ __('landing.nav.download') }}</button>
        </nav>

        <div class="header__actions">
            <div class="header__lang-wrapper">
                <select class="header__lang-select" onchange="if (this.value) window.location.href = this.value;">
                    @foreach($languages as $code => $meta)
                        <option value="{{ $localizedLandingUrls[$code] ?? route('marketing.localized', ['locale' => $code]) }}" @selected($code === $currentLocale)>
                            {{ strtoupper($code) }}
                        </option>
                    @endforeach
                </select>
                <img src="{{ asset('assets/public/vector-down.svg') }}" alt="" class="header__lang-icon" width="8px">
            </div>
            <button class="header__btn header__btn--desktop">{{ __('landing.nav.download') }}</button>
        </div>
    </div>
</header>

<section class="hero">
    <div class="hero__container">
        <div class="hero__content">
            <h1 class="hero__title">{{ __('landing.hero.title') }}</h1>

            <p class="hero__description">
                {{ __('landing.hero.lead_1') }}
            </p>

            <div class="hero__features">
                <div class="hero__feature">
                    <img src="{{ asset('assets/public/purplestar.svg') }}" alt="">
                    <p>{{ __('landing.hero.points.0') }}</p>
                </div>

                <div class="hero__feature">
                    <img src="{{ asset('assets/public/purplestar.svg') }}" alt="">
                    <p>{{ __('landing.hero.points.1') }}</p>
                </div>

                <div class="hero__feature">
                    <img src="{{ asset('assets/public/purplestar.svg') }}" alt="">
                    <p>{{ __('landing.hero.points.2') }}</p>
                </div>
            </div>

            <div class="hero__buttons">
                <button class="hero__btn hero__btn--primary">{{ __('landing.hero.cta_download') }}</button>
                <button class="hero__btn hero__btn--secondary">{{ __('landing.hero.cta_trial') }}</button>
            </div>
        </div>

        <div class="hero__image">
            <img src="{{ $heroImage }}" alt="App mockup" class="hero__phones">
        </div>
    </div>

    <div class="hero__bottom">
        <div class="hero__bottom-container">
            <p class="hero__bottom-text">{{ __('landing.hero.bottom') }}</p>
            <div class="hero__apps">
                <img src="{{ asset('assets/public/appstore.svg') }}" alt="App Store" class="hero__app-badge">
                <img src="{{ asset('assets/public/playstore.svg') }}" alt="Google Play" class="hero__app-badge">
            </div>
        </div>
    </div>
</section>

<section class="problems" id="features">
    <div class="problems__container">
        <h2 class="problems__title">{{ __('landing.problem.title') }}</h2>

        <div class="problems__content">
            <div class="problems__card problems__card--white">
                <h3 class="problems__card-title">
                    <span class="problems__icon"><img src="{{ asset('assets/public/message-icon.svg') }}" alt=""></span>
                    {{ __('landing.problem.card_title') }}
                </h3>

                <p class="problems__card-text">
                    {{ __('landing.problem.intro') }}
                </p>

                <ul class="problems__list">
                    @foreach(__('landing.problem.channels') as $channel)
                        <li class="problems__list-item">
                            <span class="problems__list-icon"><img src="{{ asset('assets/public/x.svg') }}" alt=""></span>
                            {{ $channel }}
                        </li>
                    @endforeach
                </ul>

                <p class="problems__card-text problems__card-text--bottom">
                    {{ __('landing.problem.outro') }}
                </p>

                <p class="problems__result">
                    <strong class="strong">{{ __('landing.problem.result_label') }}</strong>
                    {{ __('landing.problem.result_text') }}
                </p>
            </div>

            <div class="problems__card problems__card--blue">
                <div class="problems__card-header">
                    <span class="problems__brand-icon"><img src="{{ asset('assets/public/mini_logo.svg') }}" alt=""></span>
                    <h3 class="problems__card-title problems__card-title--blue">{{ __('landing.solution.title') }}</h3>
                </div>

                <p class="problems__card-text problems__card-text--blue">
                    {{ __('landing.solution.intro') }}
                </p>

                <ul class="problems__features-list">
                    @foreach(__('landing.solution.items') as $item)
                        <li class="problems__feature-item">
                            <span class="problems__feature-icon"><img src="{{ asset('assets/public/tick_green.svg') }}" alt=""></span>
                            {{ $item }}
                        </li>
                    @endforeach
                </ul>

                <p class="problems__card-text problems__card-text--blue">
                    {{ __('landing.solution.outro') }}
                </p>

                <p class="problems__highlight">
                    <strong class="second_strong">{{ __('landing.solution.highlight_label') }}</strong>
                    {{ __('landing.solution.highlight_text') }}
                </p>
            </div>
        </div>
    </div>
</section>

<section class="features">
    <div class="features__container">
        <h2 class="features__title">{{ __('landing.features.title') }}</h2>

        <div class="features__grid">
            <div class="features__card">
                <div class="features__image">
                    <img src="{{ asset('assets/public/features1.png') }}" alt="" class="features__img">
                </div>
                <h3 class="features__card-title">
                    <span class="features__icon"><img class="features_icon__inner" src="{{ asset('assets/public/phone_icon.png') }}" alt=""></span>
                    {{ $featureItems[0]['title'] }}
                </h3>
                <p class="features__card-text">{{ $featureItems[0]['description'] }}</p>
            </div>

            <div class="features__card">
                <div class="features__image">
                    <img src="{{ asset('assets/public/features2.png') }}" alt="" class="features__img">
                </div>
                <h3 class="features__card-title">
                    <span class="features__icon"><img src="{{ asset('assets/public/icon2.png') }}" alt=""></span>
                    {{ $featureItems[1]['title'] }}
                </h3>
                <p class="features__card-text">{{ $featureItems[1]['description'] }}</p>
            </div>

            <div class="features__card">
                <div class="features__image">
                    <img src="{{ asset('assets/public/feature3.png') }}" alt="" class="features__img">
                </div>
                <h3 class="features__card-title">
                    <span class="features__icon"><img src="{{ asset('assets/public/icon3.png') }}" alt=""></span>
                    {{ $featureItems[2]['title'] }}
                </h3>
                <p class="features__card-text">{{ $featureItems[2]['description'] }}</p>
            </div>

            <div class="features__card">
                <div class="features__image">
                    <img src="{{ asset('assets/public/feature4.png') }}" alt="" class="features__img">
                </div>
                <h3 class="features__card-title">
                    <span class="features__icon">📊</span>
                    {{ $featureItems[3]['title'] }}
                </h3>
                <p class="features__card-text">{{ $featureItems[3]['description'] }}</p>
            </div>
        </div>
    </div>
</section>

<section class="hero-cta">
    <div class="hero-cta__card">
        <div class="hero-cta__phone-bg">
            <div class="hero-cta__badge">
                <img src="{{ asset('assets/public/esticly-mini.png') }}" alt="Esticly Logo">
            </div>
            <img src="{{ asset('assets/public/secondphone.png') }}" alt="" class="hero-cta__phone" aria-hidden="true">
            <div class="hero-cta__ellipse">
                <img src="{{ asset('assets/public/ellipse_top_right.svg') }}" alt="" class="hero-cta__ellipse-img">
            </div>
            <div class="center-cta__ellipse">
                <img src="{{ asset('assets/public/center-ellipse.svg') }}" alt="" class="hero-cta__ellipse-img">
            </div>
        </div>

        <div class="hero-cta__body">
            <h2 class="hero-cta__title">{{ __('landing.usp.title') }}</h2>
            <p class="hero-cta__description">{{ __('landing.usp.text_1') }}</p>
        </div>

        <div class="hero-cta__bottom">
            <p class="hero-cta__bottom-text">{{ __('landing.usp.text_2') }}</p>
            <div class="hero-cta__buttons">
                <img src="{{ asset('assets/public/appstore.png') }}" alt="App Store" class="hero-cta__btn-img">
                <img src="{{ asset('assets/public/playstore.png') }}" alt="Google Play" class="hero-cta__btn-img">
            </div>
        </div>
    </div>
</section>

<div class="hero-cta__bottom">
    <p class="hero-cta__bottom-text">{{ __('landing.usp.text_2') }}</p>
</div>

<section class="for-whom">
    <div class="for-whom__container">
        <h2 class="for-whom__title">{{ __('landing.audience.title') }}</h2>

        <div class="for-whom__content">
            <div class="for-whom__card for-whom__card--white" id="for-professionals">
                <h3 class="for-whom__card-title">
                    <span class="for-whom__icon"><img src="{{ asset('assets/public/icon5.png') }}" alt=""></span>
                    {{ __('landing.audience.pros_title') }}
                </h3>
                <hr class="hr">

                <p class="for-whom__card-text">{{ __('landing.audience.pros_intro') }}</p>

                <ul class="for-whom__list">
                    @foreach($proItems as $item)
                        <li class="for-whom__list-item">
                            <span class="for-whom__list-icon"><img src="{{ asset('assets/public/tick_green.svg') }}" alt=""></span>
                            {{ $item }}
                        </li>
                    @endforeach
                </ul>
                <hr>

                <p class="for-whom__card-text">{{ __('landing.audience.pros_outro') }}</p>
            </div>

            <div class="for-whom__card for-whom__card--blue" id="for-salons">
                <h3 class="for-whom__card-title for-whom__card-title--blue">
                    <span class="for-whom__icon"><img src="{{ asset('assets/public/icon6.png') }}" alt=""></span>
                    {{ __('landing.audience.salons_title') }}
                </h3>

                <hr class="hr hr-blue">

                <p class="for-whom__card-text for-whom__card-text--blue">{{ __('landing.audience.salons_intro') }}</p>

                <ul class="for-whom__list">
                    @foreach($salonItems as $item)
                        <li class="for-whom__list-item for-whom__list-item--blue">
                            <span class="for-whom__list-icon for-whom__list-icon--blue"><img src="{{ asset('assets/public/tick2.png') }}" alt=""></span>
                            {{ $item }}
                        </li>
                    @endforeach
                </ul>

                <hr class="hr-blue hr">

                <p class="for-whom__card-text for-whom__card-text--blue">{{ __('landing.audience.salons_outro') }}</p>
            </div>
        </div>
    </div>
</section>

<section class="pricing" id="pricing">
    <div class="pricing__container">
        <h2 class="pricing__title">{{ __('landing.pricing.title') }}</h2>

        <p class="pricing__description">{{ __('landing.pricing.description') }}</p>

        <div class="pricing__grid">
            <div class="pricing__card">
                <h3 class="pricing__card-title">{{ __('landing.pricing.basic_title') }}</h3>
                <hr>
                <div class="pricing__price">
                    <span class="pricing__amount">{{ __('landing.pricing.basic_price') }}</span>
                    <span class="pricing__currency"></span>
                    <span class="pricing__period">{{ __('landing.pricing.period') }}</span>
                </div>

                <p class="pricing__card-description">{{ __('landing.pricing.basic_intro') }}</p>

                <ul class="pricing__features">
                    @foreach($basicItems as $item)
                        <li class="pricing__feature">
                            <span class="pricing__check"><img src="{{ asset('assets/public/tick3.png') }}" alt=""></span>
                            {{ $item }}
                        </li>
                    @endforeach
                </ul>

                <hr>

                <button class="pricing__btn">{{ __('landing.pricing.basic_cta') }}</button>
            </div>

            <div class="pricing__card pricing__card--featured pricing__card--blue">
                <div class="pricing__badge">{{ __('landing.pricing.pro_badge') }}</div>

                <h3 class="pricing__card-title">{{ __('landing.pricing.pro_title') }}</h3>
                <hr>

                <div class="pricing__price">
                    <span class="pricing__amount">{{ __('landing.pricing.pro_price') }}</span>
                    <span class="pricing__currency"></span>
                    <span class="pricing__period">{{ __('landing.pricing.period') }}</span>
                    <span class="pricing__free-trial">{{ __('landing.pricing.pro_trial') }}</span>
                </div>

                <p class="pricing__card-description">{{ __('landing.pricing.pro_intro') }}</p>

                <ul class="pricing__features">
                    @foreach($proPlanItems as $item)
                        <li class="pricing__feature">
                            <span class="pricing__check"><img src="{{ asset('assets/public/tick3.png') }}" alt=""></span>
                            {{ $item }}
                        </li>
                    @endforeach
                </ul>
                <hr>

                <button class="pricing__btn pricing__btn--primary">{{ __('landing.pricing.pro_cta') }}</button>
            </div>
        </div>
    </div>
</section>

<section class="hero-cta-download">
    <div class="hero-cta-download__container">
        <div class="hero-cta-download__image">
            <img src="{{ asset('assets/public/phone-upscayl.png') }}" alt="Esticly App" class="hero-cta-download__phone">
            <img class="hero-cta-download__logo" src="{{ asset('assets/public/logotbn.png') }}" alt="">
        </div>

        <div class="hero-cta-download__content">
            <h2 class="hero-cta-download__title">{{ __('landing.app.title') }}</h2>
            <p class="hero-cta-download__text">{{ __('landing.app.text') }}</p>
        </div>
    </div>

    <div class="hero-cta-download__bottom">
        <div class="hero-cta-download__bottom-container">
            <p class="hero-cta-download__bottom-text change_to_black">{{ __('landing.app.bottom') }}</p>
            <div class="hero-cta-download__apps">
                <img src="{{ asset('assets/public/appstore.png') }}" alt="App Store" class="hero-cta-download__app-badge">
                <img src="{{ asset('assets/public/playstore.png') }}" alt="Google Play" class="hero-cta-download__app-badge">
            </div>
        </div>
    </div>
</section>

<section class="reviews">
    <div class="reviews__container">
        <h2 class="reviews__title">{{ __('landing.reviews.title') }}</h2>

        <div class="reviews__grid">
            @foreach($reviews as $review)
                <div class="reviews__card">
                    <div class="reviews__quote"><img class="vector" src="{{ asset('assets/public/vector.png') }}" alt=""></div>
                    <p class="reviews__text">{{ $review['headline'] }}</p>
                    <p class="reviews__description">{{ $review['body_1'] }}</p>
                    <p class="reviews__description">{{ $review['body_2'] }}</p>
                    <a href="#" class="reviews__link">{{ __('landing.reviews.read_more') }}</a>

                    <div class="reviews__author">
                        <img src="{{ asset('assets/public/' . ($loop->index === 0 ? 'anastasia.png' : ($loop->index === 1 ? 'svetlana.png' : 'anastasia2.png'))) }}" alt="{{ $review['name'] }}" class="reviews__avatar">
                        <div class="reviews__author-info">
                            <p class="reviews__author-name">{{ $review['name'] }}</p>
                            <p class="reviews__author-role">{{ $review['role'] }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="reviews__pagination">
            <button class="reviews__pagination-btn reviews__pagination-btn--prev" aria-label="Previous">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>

            <div class="reviews__pagination-dots">
                <button class="reviews__dot reviews__dot--active" data-index="0"></button>
                <button class="reviews__dot" data-index="1"></button>
                <button class="reviews__dot" data-index="2"></button>
                <button class="reviews__dot" data-index="3"></button>
                <button class="reviews__dot" data-index="4"></button>
            </div>

            <button class="reviews__pagination-btn reviews__pagination-btn--next" aria-label="Next">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
        </div>
    </div>
</section>

<section class="info-section">
    <div class="info-section__container">
        <h2 class="info-section__title">{{ __('landing.seo_block.title') }}</h2>
        <p class="info-section__text">
            {{ __('landing.seo_block.paragraph_1') }} <br><br>
            {{ __('landing.seo_block.paragraph_2') }}
        </p>
    </div>
</section>

<section class="faq" id="faq">
    <div class="faq__container">
        <h2 class="faq__title">{{ __('landing.faq.title') }}</h2>

        <div class="faq__list">
            @foreach($faqItems as $item)
                <div class="faq__item">
                    <button class="faq__question">
                        <span>{{ $item['q'] }}</span>
                        @if(!$loop->index || $loop->index !== 2)
                            <hr class="hr-blue hr">
                        @endif
                        <span class="faq__icon">
                            <img src="{{ asset('assets/public/vector_button.svg') }}" alt="" width="30px" object-fit="contain">
                        </span>
                    </button>
                    @if($loop->index === 2)
                        <hr class="hr-blue hr">
                    @endif
                    <div class="faq__answer">
                        <p>{{ $item['a'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="final-cta">
    <div class="final-cta__container">
        <div class="final-cta__content">
            <h2 class="final-cta__title">{{ __('landing.final_cta.title') }}</h2>
            <p class="final-cta__text">{{ __('landing.final_cta.text') }}</p>
            <div class="final-cta__buttons">
                <a href="#" class="final-cta__btn">
                    <img src="{{ asset('assets/public/appstore.svg') }}" alt="">
                </a>
                <a href="#" class="final-cta__btn">
                    <img src="{{ asset('assets/public/playstore.svg') }}" alt="">
                </a>
            </div>
        </div>

        <div class="final-cta__image">
            <img src="{{ $heroImage }}" alt="Esticly App" class="final-cta__phone">
            <img class="top-right" src="{{ asset('assets/public/final-cta-top-right.svg') }}" alt="">
            <img class="bottom-left" src="{{ asset('assets/public/final-cta-bottom.svg') }}" alt="">
        </div>
    </div>
</section>

<section class="footer-cta">
    <div class="footer-cta__container">
        <div class="footer-cta__content">
            <p class="footer-cta__text">{{ __('landing.hero.bottom') }}</p>
            <div class="footer-cta__buttons">
                <a href="#" class="footer-cta__app-link">
                    <img src="{{ asset('assets/public/appstore.png') }}" alt="App Store">
                </a>
                <a href="#" class="footer-cta__app-link">
                    <img src="{{ asset('assets/public/playstore.png') }}" alt="Google Play">
                </a>
            </div>
        </div>
    </div>
</section>

<footer>
    <div class="footer-top">
        <div class="footer-brand">
            <div class="footer-logo">
                <img src="{{ asset('assets/public/footer-logo.svg') }}" alt="">
            </div>
            <p class="footer-desc">{{ __('landing.footer.description') }}</p>
{{--            <div class="lang-buttons lang-desktop">--}}
{{--                @foreach($languages as $code => $meta)--}}
{{--                    <a href="{{ $localizedLandingUrls[$code] ?? route('marketing.localized', ['locale' => $code]) }}" class="lang-btn{{ $code === $currentLocale ? ' active' : '' }}">{{ strtoupper($code) }}</a>--}}
{{--                @endforeach--}}
{{--            </div>--}}
        </div>

        <div class="footer-nav">
            <p class="footer-nav-title">{{ __('landing.footer.menu_title') }}</p>
            <ul>
                <li><a href="#features">{{ __('landing.nav.features') }}</a></li>
                <li><a href="#for-professionals">{{ __('landing.nav.professionals') }}</a></li>
                <li><a href="#for-salons">{{ __('landing.nav.salons') }}</a></li>
                <li><a href="#pricing">{{ __('landing.nav.pricing') }}</a></li>
                <li><a href="#faq">{{ __('landing.nav.faq') }}</a></li>
            </ul>
        </div>

        <div class="footer-contacts">
            <p class="footer-contacts-title">{{ __('landing.footer.contacts_title') }}</p>

{{--            <div class="contact-item">--}}
{{--                <img src="{{ asset('assets/public/footer-phone.svg') }}" alt="" class="contact-icon">--}}
{{--                <div class="contact-details">--}}
{{--                    <span class="contact-main">{{ __('landing.footer.phone_value') }}</span>--}}
{{--                    <p class="contact-sub">{{ __('landing.footer.hours') }}</p>--}}
{{--                </div>--}}
{{--            </div>--}}

            <div class="contact-item">
                <img src="{{ asset('assets/public/footer-email.svg') }}" alt="" class="contact-icon">
                <div class="contact-details">
                    <a href="mailto:{{ __('landing.footer.email_value') }}" class="contact-email-link">{{ __('landing.footer.email_value') }}</a>
                    <a href="mailto:{{ __('landing.footer.email_value') }}" class="contact-write">{{ __('landing.footer.email_cta') }}</a>
                </div>
            </div>

{{--            <div class="contact-item">--}}
{{--                <img src="{{ asset('assets/public/footer-location.svg') }}" alt="" class="contact-icon">--}}
{{--                <div class="contact-details">--}}
{{--                    <span class="contact-city">{{ __('landing.footer.location_value') }}</span>--}}
{{--                    <p class="contact-address">{{ __('landing.footer.location_address') }}</p>--}}
{{--                </div>--}}
{{--            </div>--}}
        </div>
    </div>

{{--    <div class="lang-buttons lang-mobile">--}}
{{--        @foreach($languages as $code => $meta)--}}
{{--            <a href="{{ $localizedLandingUrls[$code] ?? route('marketing.localized', ['locale' => $code]) }}" class="lang-btn{{ $code === $currentLocale ? ' active' : '' }}">{{ strtoupper($code) }}</a>--}}
{{--        @endforeach--}}
{{--    </div>--}}

    <div class="footer-bottom">
        <span class="footer-copyright">{{ __('landing.footer.copyright') }}</span>
        <div class="footer-links">
            <a href="{{ route('legal.privacy.localized', ['locale' => $currentLocale]) }}">{{ __('landing.footer.privacy') }}</a>
            <a href="{{ route('legal.terms.localized', ['locale' => $currentLocale]) }}">{{ __('landing.footer.terms') }}</a>
        </div>
    </div>
</footer>

<div id="app"></div>
<script type="module" src="{{ asset('assets/src/main.js') }}"></script>
</body>
</html>
