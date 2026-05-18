@extends('layouts.marketing')

@section('content')
@php($appStoreUrl = 'https://apps.apple.com/app/id6761251722')
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

{{--            <div class="hero__buttons">--}}
{{--                <button class="hero__btn hero__btn--primary">{{ __('landing.hero.cta_download') }}</button>--}}
{{--                <button class="hero__btn hero__btn--secondary">{{ __('landing.hero.cta_trial') }}</button>--}}
{{--            </div>--}}
        </div>

        <div class="hero__image">
            <img src="{{ $heroImage }}" alt="App mockup" class="hero__phones">
        </div>
    </div>

    <div class="hero__bottom">
        <div class="hero__bottom-container">
            <p class="hero__bottom-text">{{ __('landing.hero.bottom') }}</p>
            <div class="hero__apps">
                <a href="{{ $appStoreUrl }}" target="_blank" rel="noopener noreferrer" aria-label="Download on the App Store">
                    <img src="{{ asset('assets/public/appstore.svg') }}" alt="App Store" class="hero__app-badge">
                </a>
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
                <a href="{{ $appStoreUrl }}" target="_blank" rel="noopener noreferrer" aria-label="Download on the App Store">
                    <img src="{{ asset('assets/public/appstore.png') }}" alt="App Store" class="hero-cta__btn-img">
                </a>
                <img src="{{ asset('assets/public/playstore.png') }}" alt="Google Play" class="hero-cta__btn-img">
            </div>
        </div>
    </div>
</section>

{{--<div class="hero-cta__bottom">--}}
{{--    <p class="hero-cta__bottom-text">{{ __('landing.usp.text_2') }}</p>--}}
{{--</div>--}}

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

{{--                <button class="pricing__btn">{{ __('landing.pricing.basic_cta') }}</button>--}}
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

{{--                <button class="pricing__btn pricing__btn--primary">{{ __('landing.pricing.pro_cta') }}</button>--}}
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
                <a href="{{ $appStoreUrl }}" target="_blank" rel="noopener noreferrer" aria-label="Download on the App Store">
                    <img src="{{ asset('assets/public/appstore.png') }}" alt="App Store" class="hero-cta-download__app-badge">
                </a>
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
{{--                <button class="reviews__dot" data-index="3"></button>--}}
{{--                <button class="reviews__dot" data-index="4"></button>--}}
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
                <a href="{{ $appStoreUrl }}" class="final-cta__btn" target="_blank" rel="noopener noreferrer" aria-label="Download on the App Store">
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
                <a href="{{ $appStoreUrl }}" class="footer-cta__app-link" target="_blank" rel="noopener noreferrer" aria-label="Download on the App Store">
                    <img src="{{ asset('assets/public/appstore.png') }}" alt="App Store">
                </a>
                <a href="#" class="footer-cta__app-link">
                    <img src="{{ asset('assets/public/playstore.png') }}" alt="Google Play">
                </a>
            </div>
        </div>
    </div>
</section>
@endsection
