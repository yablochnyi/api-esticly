<!doctype html>
<html lang="{{ $currentLocale }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDescription }}" />
    <link rel="canonical" href="{{ $seoCanonicalUrl }}" />
    @foreach($seoAlternateUrls as $hreflang => $href)
        <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}" />
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ $seoXDefaultUrl }}" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="{{ $seoTitle }}" />
    <meta property="og:description" content="{{ $seoDescription }}" />
    <meta property="og:url" content="{{ $seoCanonicalUrl }}" />
    @if($socialImage)
        <meta property="og:image" content="{{ $socialImage }}" />
        <meta name="twitter:image" content="{{ $socialImage }}" />
    @endif
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $seoTitle }}" />
    <meta name="twitter:description" content="{{ $seoDescription }}" />
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
    @stack('head')
</head>
<body>
<header class="header">
    <div class="header__container">
        <button class="header__menu-btn" id="menuBtn">
            <img src="{{ asset('assets/public/burger-mobile.svg') }}" alt="">
        </button>

        <div class="header__logo">
            <a href="{{ $landingHomeUrl }}">
                <img class="header__logo__inner" src="{{ asset('assets/public/logo.svg') }}" alt="Esticly logo">
            </a>
        </div>

        <nav class="header__nav" id="mobileMenu">
            <button class="header__close-btn" id="closeBtn"></button>
            @foreach($headerNavLinks as $link)
                <a href="{{ $link['url'] }}" class="header__link">{{ $link['label'] }}</a>
            @endforeach
            <button class="header__btn header__btn--mobile">{{ $headerCtaLabel }}</button>
        </nav>

        <div class="header__actions">
            <div class="header__lang-wrapper">
                <select class="header__lang-select" onchange="if (this.value) window.location.href = this.value;">
                    @foreach($languageOptions as $option)
                        <option value="{{ $option['url'] }}" @selected($option['selected'])>{{ $option['label'] }}</option>
                    @endforeach
                </select>
                <img src="{{ asset('assets/public/vector-down.svg') }}" alt="" class="header__lang-icon" width="8px">
            </div>
            <button class="header__btn header__btn--desktop">{{ $headerCtaLabel }}</button>
        </div>
    </div>
</header>

@yield('content')

<footer>
    @if($showFooterTop)
        <div class="footer-top">
            <div class="footer-brand">
                <div class="footer-logo">
                    <img src="{{ asset('assets/public/footer-logo.svg') }}" alt="">
                </div>
                <p class="footer-desc">{{ __('landing.footer.description') }}</p>
            </div>

            <div class="footer-nav">
                <p class="footer-nav-title">{{ __('landing.footer.menu_title') }}</p>
                <ul>
                    @foreach($footerNavLinks as $link)
                        <li><a href="{{ $link['url'] }}">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>

            <div class="footer-contacts">
                <p class="footer-contacts-title">{{ __('landing.footer.contacts_title') }}</p>
                <div class="contact-item">
                    <img src="{{ asset('assets/public/footer-email.svg') }}" alt="" class="contact-icon">
                    <div class="contact-details">
                        <a href="mailto:{{ $footerEmail }}" class="contact-email-link">{{ $footerEmail }}</a>
                        <a href="mailto:{{ $footerEmail }}" class="contact-write">{{ __('landing.footer.email_cta') }}</a>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="footer-bottom" @if(!$showFooterTop) style="border-top:0;padding-top:0;" @endif>
        <span class="footer-copyright">{{ __('landing.footer.copyright') }}</span>
        <div class="footer-links">
            <a href="{{ route('legal.privacy.localized', ['locale' => $currentLocale]) }}">{{ __('landing.footer.privacy') }}</a>
            <a href="{{ route('legal.terms.localized', ['locale' => $currentLocale]) }}">{{ __('landing.footer.terms') }}</a>
        </div>
    </div>
</footer>

<div id="app"></div>
<script type="module" src="{{ asset('assets/src/main.js') }}"></script>
@stack('scripts')
</body>
</html>
