@extends('booking.layout')

@php
    $title = $org->company_name ?: __('booking.salon');
    $currentLocale = $lang ?? app()->getLocale();
    $currency = $org->currency_code ?: '';
    $publicBioText = trim((string) ($publicBio ?? ''));
    $publicPhoneValue = trim((string) ($publicPhone ?? ''));
    $publicAddressValue = trim((string) ($org->address ?? ''));
    $specialtyTags = collect(is_array($org->booking_specialties) ? $org->booking_specialties : [])
        ->map(fn ($specialty) => trim((string) ($specialty['name'] ?? '')))
        ->filter()
        ->take(4)
        ->values();
    $galleryItems = collect($portfolioPhotos ?? [])->take(6)->values();
    $languages = ['uk', 'en', 'pl', 'cs', 'de', 'fr', 'it', 'es', 'pt'];
    $weekdaysShort = [
        __('booking.days_short.mon'),
        __('booking.days_short.tue'),
        __('booking.days_short.wed'),
        __('booking.days_short.thu'),
        __('booking.days_short.fri'),
        __('booking.days_short.sat'),
        __('booking.days_short.sun'),
    ];
@endphp
@section('body')
<header class="header">
    <div class="header_wrapper container">
        <div class="header__lang-wrapper">
            <a href="{{ route('booking.landing', ['slug' => $org->booking_slug, 'lang' => $currentLocale]) }}">
                <img src="{{ asset('assets/booking/public/logo_1.svg') }}" alt="Esticly">
            </a>
            <select class="header__lang-select" id="bookingLangSelect">
                @foreach($languages as $localeCode)
                    <option value="{{ $localeCode }}" @selected($localeCode === $currentLocale)>{{ strtoupper($localeCode) }}</option>
                @endforeach
            </select>
            <img src="{{ asset('assets/booking/public/vector-down.svg') }}" alt="" class="header__lang-icon" width="8" height="8">
        </div>
    </div>
</header>

<section class="specialist-section" id="bookingServiceStep">
    <div class="specialist-card container">
        <div class="specialist-row-top">
            <div class="specialist-photo" style="width:160px;height:160px;border-radius:16px;background:#f4efff;display:flex;align-items:center;justify-content:center;overflow:hidden;">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $title }}">
                @else
                    <div style="font-size:44px;font-weight:700;color:#4f46e5;">{{ mb_substr($title, 0, 1) }}</div>
                @endif
            </div>

            <div class="specialist-center">
                <div class="specialist-name-row">
                    <span class="specialist-name">{{ $title }}</span>
                    <div class="rating-wrapper">
                        <img src="{{ asset('assets/booking/public/rating.svg') }}" alt="Rating">
                        <span class="specialist-rating">{{ number_format((float) ($ratingAvg ?? 0), 1, '.', '') }}</span>
                    </div>
                </div>

                <hr class="specialist-hr">

                @if($specialtyTags->isNotEmpty())
                    <div class="specialist-specialty-row">
                        <span class="specialty-label">{{ __('booking.mockup.service_page.specialty') }}:</span>
                        <div class="specialty-tags">
                            @foreach($specialtyTags as $tag)
                                <div class="specialty-tag">{{ $tag }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            @if(!empty($socialLinks))
                <div class="social-icons">
                    @foreach($socialLinks as $socialLink)
                        <a class="social-btn" href="{{ $socialLink['href'] }}" aria-label="{{ ucfirst($socialLink['key']) }}" target="_blank" rel="noopener">
                            <img src="{{ asset('assets/booking/public/' . $socialLink['icon']) }}" alt="{{ ucfirst($socialLink['key']) }}">
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>

<section class="about container" id="bookingServiceAbout">
    <div class="about_inner">
        @if($publicBioText !== '')
            <div class="about_text">
                <span>{{ __('booking.about_title') }}:</span>
                <p>{{ $publicBioText }}</p>
            </div>
        @endif
        @if($publicAddressValue !== '')
            <div class="about_location">
                <span>
                    <div class="about_flex_location">
                        <img src="{{ asset('assets/booking/public/location.svg') }}" alt="">
                        {{ __('booking.mockup.service_page.footer_contacts') }}
                    </div>
                </span>
                <div class="about_location_wrapper" style="margin-top:12px;">
                    <p class="location_p">{{ $publicAddressValue }}</p>
                </div>
            </div>
        @endif
    </div>

    @if($galleryItems->isNotEmpty())
        <hr class="hr">

        <div class="master-works">
            <img src="{{ asset('assets/booking/public/solar_gallery-linear.svg') }}" alt="">
            <p>{{ __('booking.mockup.service_page.works_title') }}:</p>
        </div>

        <div class="gallery">
            @foreach($galleryItems as $photoUrl)
                <img src="{{ $photoUrl }}" alt="Portfolio">
            @endforeach
        </div>
    @endif
</section>

<section class="services-section" id="bookingServiceServices">
    <div class="services-layout container">
        <div class="services-list">
            <div class="services-title">
                <img src="{{ asset('assets/booking/public/solar_checklist-linear_.svg') }}" alt="">
                <span>{{ __('booking.services_title') }}:</span>
            </div>

                @foreach($services as $service)
                        @php
                            $duration = (int) ($service->duration_from_min ?? $service->duration_to_min ?? 0);
                            $priceType = $service->price_type ?? 'fixed';
                            $priceText = $priceType === 'range'
                                ? trim((string) ($service->price_from ?? '—') . '–' . (string) ($service->price_to ?? '—') . ' ' . $currency)
                                : trim((string) ($service->price_fixed ?? '—') . ' ' . $currency);
                            $priceValue = $priceType === 'range'
                                ? (float) ($service->price_from ?? 0)
                                : (float) ($service->price_fixed ?? 0);
                        @endphp
                        <div
                            class="service-item @if(($selectedServiceId ?? null) === $service->id) selected @endif"
                            data-service-id="{{ $service->id }}"
                            data-service-name="{{ e($service->name) }}"
                            data-service-duration="{{ $duration }}"
                            data-service-duration-label="{{ $duration }} {{ __('booking.minutes_short') }}"
                            data-service-price="{{ $priceValue }}"
                            data-service-price-label="{{ e($priceText) }}"
                            role="button"
                            tabindex="0"
                        >
                            <div class="service-checkbox-wrap">
                                <div class="service-checkbox @if(($selectedServiceId ?? null) === $service->id) checked @endif"></div>
                                <div class="service-info">
                                    <span class="service-name">{{ $service->name }}</span>
                                    <span class="service-meta"><img src="{{ asset('assets/booking/public/service_meta.svg') }}" alt=""> {{ $duration }} {{ __('booking.minutes_short') }}</span>
                                </div>
                            </div>
                            <span class="service-price">{{ $priceText }}</span>
                        </div>
                @endforeach
        </div>

        <div class="services-summary">
            <div class="summary-title">{{ __('booking.mockup.service_page.summary_title') }}:</div>
            <div class="summary-items" id="summaryItems"></div>
            <div class="summary-divider"></div>
            <div class="summary-row">
                <span class="summary-label">{{ __('booking.mockup.summary.duration') }}</span>
                <span class="summary-value" id="summaryDuration">0 {{ __('booking.minutes_short') }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">{{ __('booking.mockup.summary.price') }}</span>
                <span class="summary-value summary-cost" id="summaryCost">0 {{ $currency }}</span>
            </div>
            <button class="summary-btn" id="serviceContinueBtn" type="button" disabled>{{ __('booking.continue') }}</button>
        </div>
    </div>
</section>

<div class="page-wrapper" id="bookingDateStep" style="display:none;">
    <div class="page-header">
        <button class="back-btn" id="bookingBackBtn" type="button">
            <img src="{{ asset('assets/booking/public/left_vector.svg') }}" alt="">
        </button>
        <span class="page-title">{{ __('booking.mockup.stage_two.title') }}</span>
    </div>

    <div class="booking-layout">
        <div class="calendar-card">
            <div id="staffPicker" style="display:none;margin-bottom:24px;">
                <div class="time-group-label" style="margin-bottom:12px;">{{ __('booking.steps.staff') }}</div>
                <div class="time-slots" id="staffSlots" style="display:flex;flex-wrap:wrap;gap:10px;"></div>
                <div id="staffEmptyState" class="service-meta" style="display:none;margin-top:12px;">{{ __('booking.staff_empty') }}</div>
            </div>

            <div class="calendar-nav">
                <button class="nav-btn" id="prevMonth" type="button">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </button>
                <span class="calendar-month" id="calendarMonth">—</span>
                <button class="nav-btn" id="nextMonth" type="button">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </button>
            </div>

            <div class="calendar-grid">
                <div class="calendar-weekdays" id="calendarWeekdays"></div>
                <div class="calendar-days" id="calendarDays"></div>
            </div>

            <div class="time-section">
                <div class="time-slots" id="timeSlots"></div>
                <div id="timesEmptyState" class="service-meta" style="display:none;margin-top:16px;">{{ __('booking.no_free_time') }}</div>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-master" id="dateSummaryMaster">{{ $title }}</div>

            <div class="summary-datetime">
                <span class="summary-date" id="summaryDate">—</span>
                <span class="summary-time" id="summaryTime">—</span>
            </div>

            <div class="summary-services" id="summaryServices"></div>

            <div class="summary-divider"></div>

            <div class="summary-totals">
                <div class="summary-total-row">
                    <span class="summary-total-label">{{ __('booking.mockup.summary.duration') }}</span>
                    <span class="summary-total-value" id="totalDuration">0 {{ __('booking.minutes_short') }}</span>
                </div>
                <div class="summary-total-row">
                    <span class="summary-total-label">{{ __('booking.mockup.summary.price') }}</span>
                    <span class="summary-total-value" id="totalCost">0 {{ $currency }}</span>
                </div>
            </div>

            <button class="continue-btn" id="dateContinueBtn" type="button" disabled>{{ __('booking.continue') }}</button>
        </div>
    </div>
</div>

<section class="footer-cta">
    <div class="footer-cta__container">
        <div class="footer-cta__content">
            <p class="footer-cta__text">{{ __('booking.mockup.service_page.footer_cta') }}</p>
            <div class="footer-cta__buttons">
                <a href="#" class="footer-cta__app-link">
                    <img src="{{ asset('assets/booking/public/appstore.svg') }}" alt="App Store">
                </a>
                <a href="#" class="footer-cta__app-link">
                    <img src="{{ asset('assets/booking/public/playstore.svg') }}" alt="Google Play">
                </a>
            </div>
        </div>
    </div>
</section>

<footer>
    <div class="footer-top">
        <div class="footer-brand">
            <div class="footer-logo">
                <img src="{{ asset('assets/booking/public/esticly_logo.svg') }}" alt="Esticly">
            </div>
            <p class="footer-desc">{{ __('booking.mockup.service_page.footer_text') }}</p>
{{--            <div class="lang-buttons lang-desktop">--}}
{{--                @foreach($languages as $localeCode)--}}
{{--                    <button class="lang-btn js-lang-btn" type="button" data-lang="{{ $localeCode }}">{{ strtoupper($localeCode) }}</button>--}}
{{--                @endforeach--}}
{{--            </div>--}}
        </div>

{{--        <div class="footer-nav">--}}
{{--            <p class="footer-nav-title">{{ __('booking.mockup.service_page.footer_clients') }}</p>--}}
{{--            <ul>--}}
{{--                <li><a href="{{ route('booking.landing', ['slug' => $org->booking_slug, 'lang' => $currentLocale]) }}">{{ __('booking.book_now') }}</a></li>--}}
{{--                <li><a href="{{ route('legal.privacy.localized', ['locale' => $currentLocale]) }}">{{ __('landing.footer.privacy') }}</a></li>--}}
{{--                <li><a href="{{ route('legal.terms.localized', ['locale' => $currentLocale]) }}">{{ __('landing.footer.terms') }}</a></li>--}}
{{--            </ul>--}}
{{--        </div>--}}

{{--        <div class="footer-contacts">--}}
{{--            <p class="footer-contacts-title">{{ __('booking.mockup.service_page.footer_contacts') }}</p>--}}
{{--            @if($publicPhoneValue !== '')--}}
{{--                <div class="contact-item">--}}
{{--                    <img src="{{ asset('assets/booking/public/footer-phone.svg') }}" alt="" class="contact-icon">--}}
{{--                    <div class="contact-details">--}}
{{--                        <span class="contact-main">{{ $publicPhoneValue }}</span>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--            @endif--}}

{{--            @if($publicAddressValue !== '')--}}
{{--                <div class="contact-item">--}}
{{--                    <img src="{{ asset('assets/booking/public/footer-location.svg') }}" alt="" class="contact-icon">--}}
{{--                    <div class="contact-details">--}}
{{--                        <span class="contact-city">{{ $org->company_name ?: __('booking.salon') }}</span>--}}
{{--                        <p class="contact-address">{{ $publicAddressValue }}</p>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--            @endif--}}
{{--        </div>--}}
    </div>

    <div class="lang-buttons lang-mobile">
        @foreach($languages as $localeCode)
            <button class="lang-btn js-lang-btn" type="button" data-lang="{{ $localeCode }}">{{ strtoupper($localeCode) }}</button>
        @endforeach
    </div>

    <div class="footer-bottom">
        <span class="footer-copyright">2026 Esticly</span>
        <div class="footer-links">
            <a href="{{ route('legal.privacy.localized', ['locale' => $currentLocale]) }}">{{ __('landing.footer.privacy') }}</a>
            <a href="{{ route('legal.terms.localized', ['locale' => $currentLocale]) }}">{{ __('landing.footer.terms') }}</a>
        </div>
    </div>
</footer>

<div class="booking-overlay" id="bookingOverlay">
    <div class="booking-modal">
        <button class="booking-modal__close" id="closeModal" type="button">&#x2715;</button>
        <div class="booking-modal__left">
            <h2 class="booking-modal__title">{{ __('booking.mockup.stage_three.title') }}</h2>
            <form method="POST" action="{{ route('booking.submit', ['slug' => $org->booking_slug, 'lang' => $currentLocale]) }}" id="bookingSubmitForm">
                @csrf
                <input type="hidden" name="lang" value="{{ $currentLocale }}">
                <input type="hidden" name="service_id" id="formServiceId">
                <input type="hidden" name="staff_id" id="formStaffId">
                <input type="hidden" name="date" id="formDate">
                <input type="hidden" name="time" id="formTime">
                <input type="hidden" name="name" id="formName">

                <div class="bm-row">
                    <div class="bm-group">
                        <label class="bm-label">{{ __('booking.mockup.form.first_name') }}</label>
                        <input class="bm-input" type="text" placeholder="{{ __('booking.mockup.form.first_name_placeholder') }}" id="bookingFirstName" required>
                    </div>
                    <div class="bm-group">
                        <label class="bm-label">{{ __('booking.mockup.form.last_name') }}</label>
                        <input class="bm-input" type="text" placeholder="{{ __('booking.mockup.form.last_name_placeholder') }}" id="bookingLastName" required>
                    </div>
                </div>
                <div class="bm-row">
                    <div class="bm-group">
                        <label class="bm-label">{{ __('booking.mockup.form.phone') }}</label>
                        <input class="bm-input" type="tel" placeholder="+380" id="bookingPhoneVisible" name="phone" required>
                    </div>
                    <div class="bm-group">
                        <label class="bm-label">{{ __('booking.mockup.form.instagram') }}</label>
                        <input class="bm-input" type="text" placeholder="{{ __('booking.mockup.form.instagram_placeholder') }}">
                    </div>
                </div>
                <div class="bm-group" style="margin-bottom:0">
                    <label class="bm-label">{{ __('booking.comment') }}</label>
                    <textarea class="bm-input bm-textarea" placeholder="{{ __('booking.mockup.form.comment_placeholder') }}" name="comment"></textarea>
                </div>
                <button class="bm-submit" id="bookingSubmitBtn" type="submit">{{ __('booking.submit_booking') }}</button>
            </form>
        </div>
        <div class="booking-modal__right">
            <div class="bm-right-card">
                <div class="bm-summary-header">
                    <span class="bm-summary-date" id="modalDate">—</span>
                    <span class="bm-summary-time" id="modalTime">—</span>
                </div>
                <div class="bm-summary-list" id="modalServices"></div>
                <div class="bm-summary-divider"></div>
                <div class="bm-summary-total">
                    <span class="bm-summary-total__label">{{ __('booking.mockup.summary.price') }}</span>
                    <span class="bm-summary-total__value" id="modalTotalCost">0 {{ $currency }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

@php
    $servicesForJs = $services->map(function ($service) use ($currency) {
        $duration = (int) ($service->duration_from_min ?? $service->duration_to_min ?? 0);
        $priceType = $service->price_type ?? 'fixed';
        $priceValue = $priceType === 'range'
            ? (float) ($service->price_from ?? 0)
            : (float) ($service->price_fixed ?? 0);
        $priceText = $priceType === 'range'
            ? trim((string) ($service->price_from ?? '—') . '–' . (string) ($service->price_to ?? '—') . ' ' . $currency)
            : trim((string) ($service->price_fixed ?? '—') . ' ' . $currency);
        return [
            'id' => (int) $service->id,
            'name' => (string) $service->name,
            'duration' => $duration,
            'duration_label' => $duration . ' ' . __('booking.minutes_short'),
            'price' => $priceValue,
            'price_label' => $priceText,
        ];
    })->values();
@endphp
<script>
    const bookingConfig = {
        lang: @json($currentLocale),
        currency: @json($currency),
        orgName: @json($title),
        selectedServiceId: @json($selectedServiceId),
        routes: {
            landing: @json(route('booking.landing', ['slug' => $org->booking_slug, 'lang' => $currentLocale])),
            staff: @json(route('booking.staff', ['slug' => $org->booking_slug])),
            availability: @json(route('booking.availability', ['slug' => $org->booking_slug])),
            slots: @json(route('booking.slots', ['slug' => $org->booking_slug])),
        },
        i18n: {
        staffLabel: @json(__('booking.steps.staff')),
            orgLabel: @json($title),
            noFreeTime: @json(__('booking.no_free_time')),
            staffEmpty: @json(__('booking.staff_empty')),
            duration: @json(__('booking.mockup.summary.duration')),
            price: @json(__('booking.mockup.summary.price')),
            continueLabel: @json(__('booking.continue')),
            emptyDurationLabel: @json('0 ' . __('booking.minutes_short')),
            weekdays: @json($weekdaysShort),
        },
        services: @json($servicesForJs),
    };

    const state = {
        serviceId: bookingConfig.selectedServiceId,
        staffId: null,
        staffChoice: 'org',
        staffName: bookingConfig.orgName,
        requiresStaffChoice: false,
        date: null,
        time: null,
        selectedMonth: null,
        availableDates: [],
    };

    const serviceStep = document.getElementById('bookingServiceStep');
    const serviceAbout = document.getElementById('bookingServiceAbout');
    const serviceServices = document.getElementById('bookingServiceServices');
    const dateStep = document.getElementById('bookingDateStep');
    const serviceContinueBtn = document.getElementById('serviceContinueBtn');
    const dateContinueBtn = document.getElementById('dateContinueBtn');
    const bookingBackBtn = document.getElementById('bookingBackBtn');
    const serviceItems = Array.from(document.querySelectorAll('.service-item'));
    const summaryItems = document.getElementById('summaryItems');
    const summaryDuration = document.getElementById('summaryDuration');
    const summaryCost = document.getElementById('summaryCost');
    const summaryServices = document.getElementById('summaryServices');
    const totalDuration = document.getElementById('totalDuration');
    const totalCost = document.getElementById('totalCost');
    const dateSummaryMaster = document.getElementById('dateSummaryMaster');
    const calendarMonth = document.getElementById('calendarMonth');
    const calendarWeekdays = document.getElementById('calendarWeekdays');
    const calendarDays = document.getElementById('calendarDays');
    const timeSlots = document.getElementById('timeSlots');
    const timesEmptyState = document.getElementById('timesEmptyState');
    const summaryDate = document.getElementById('summaryDate');
    const summaryTime = document.getElementById('summaryTime');
    const staffPicker = document.getElementById('staffPicker');
    const staffSlots = document.getElementById('staffSlots');
    const staffEmptyState = document.getElementById('staffEmptyState');
    const overlay = document.getElementById('bookingOverlay');
    const closeModal = document.getElementById('closeModal');
    const bookingSubmitForm = document.getElementById('bookingSubmitForm');
    const formServiceId = document.getElementById('formServiceId');
    const formStaffId = document.getElementById('formStaffId');
    const formDate = document.getElementById('formDate');
    const formTime = document.getElementById('formTime');
    const formName = document.getElementById('formName');
    const modalDate = document.getElementById('modalDate');
    const modalTime = document.getElementById('modalTime');
    const modalServices = document.getElementById('modalServices');
    const modalTotalCost = document.getElementById('modalTotalCost');
    const bookingFirstName = document.getElementById('bookingFirstName');
    const bookingLastName = document.getElementById('bookingLastName');
    const bookingLangSelect = document.getElementById('bookingLangSelect');

    function getSelectedService() {
        return bookingConfig.services.find((service) => service.id === Number(state.serviceId)) || null;
    }

    function formatCurrencyValue(value) {
        const normalized = Number(value || 0);
        return `${normalized.toFixed(normalized % 1 === 0 ? 0 : 2)} ${bookingConfig.currency}`.trim();
    }

    function setSelectedService(serviceId) {
        state.serviceId = Number(serviceId);
        serviceItems.forEach((item) => {
            const checked = Number(item.dataset.serviceId) === state.serviceId;
            item.classList.toggle('selected', checked);
            item.querySelector('.service-checkbox')?.classList.toggle('checked', checked);
        });
        renderSelectedService();
        serviceContinueBtn.disabled = !state.serviceId;
    }

    function renderSelectedService() {
        const service = getSelectedService();
        summaryItems.innerHTML = '';
        summaryServices.innerHTML = '';

        if (!service) {
            summaryDuration.textContent = bookingConfig.i18n.emptyDurationLabel;
            summaryCost.textContent = `0 ${bookingConfig.currency}`.trim();
            totalDuration.textContent = bookingConfig.i18n.emptyDurationLabel;
            totalCost.textContent = `0 ${bookingConfig.currency}`.trim();
            return;
        }

        const serviceMarkup = `
            <div class="summary-item">
                <div class="summary-item-info">
                    <span class="summary-item-name">${service.name}</span>
                    <span class="summary-item-meta">${service.duration_label} · ${service.price_label}</span>
                </div>
            </div>`;
        summaryItems.innerHTML = serviceMarkup;

        summaryServices.innerHTML = `
            <div class="summary-service-item">
                <div class="summary-service-info">
                    <div class="summary-service-name">${service.name}</div>
                    <div class="summary-service-meta">${service.duration_label} · ${service.price_label}</div>
                </div>
            </div>`;

        summaryDuration.textContent = service.duration_label;
        summaryCost.textContent = service.price_label;
        totalDuration.textContent = service.duration_label;
        totalCost.textContent = service.price_label;
    }

    function toMonthKey(date) {
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
    }

    function formatDayForSummary(dateString) {
        const date = new Date(`${dateString}T12:00:00`);
        return new Intl.DateTimeFormat(bookingConfig.lang, { day: '2-digit', month: '2-digit', year: '2-digit' }).format(date);
    }

    async function fetchJson(url) {
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            throw new Error(`Request failed with status ${response.status}`);
        }
        return response.json();
    }

    async function openDateStep() {
        if (!state.serviceId) {
            return;
        }

        const params = new URLSearchParams({ service_id: String(state.serviceId) });
        const staffResponse = await fetchJson(`${bookingConfig.routes.staff}?${params.toString()}`);
        const staffList = Array.isArray(staffResponse.data) ? staffResponse.data : [];

        state.requiresStaffChoice = staffList.length > 0;
        state.staffId = null;
        state.staffChoice = staffList.length > 0 ? null : 'org';
        state.staffName = bookingConfig.orgName;
        renderStaffChoices(staffList);

        serviceStep.style.display = 'none';
        serviceAbout.style.display = 'none';
        serviceServices.style.display = 'none';
        dateStep.style.display = '';
        window.scrollTo({ top: 0, behavior: 'auto' });

        const now = new Date();
        state.selectedMonth = new Date(now.getFullYear(), now.getMonth(), 1);
        state.date = null;
        state.time = null;
        summaryDate.textContent = '—';
        summaryTime.textContent = '—';
        dateContinueBtn.disabled = true;
        renderSelectedService();
        await loadAvailability();
    }

    function renderStaffChoices(staffList) {
        if (!state.requiresStaffChoice) {
            staffPicker.style.display = 'none';
            staffSlots.innerHTML = '';
            staffEmptyState.style.display = 'none';
            return;
        }

        staffPicker.style.display = '';
        staffSlots.innerHTML = '';
        staffEmptyState.style.display = staffList.length === 0 ? '' : 'none';

        const orgButton = document.createElement('button');
        orgButton.type = 'button';
        orgButton.className = 'time-slot';
        orgButton.textContent = bookingConfig.i18n.orgLabel;
        orgButton.addEventListener('click', async () => {
            state.staffChoice = 'org';
            state.staffId = null;
            state.staffName = bookingConfig.orgName;
            dateSummaryMaster.textContent = bookingConfig.orgName;
            Array.from(staffSlots.children).forEach((node) => node.classList.remove('time-slot--selected'));
            orgButton.classList.add('time-slot--selected');
            state.date = null;
            state.time = null;
            summaryDate.textContent = '—';
            summaryTime.textContent = '—';
            clearTimes();
            dateContinueBtn.disabled = true;
            await loadAvailability();
        });
        staffSlots.appendChild(orgButton);

        staffList.forEach((staff) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'time-slot';
            button.textContent = staff.name;
            button.addEventListener('click', async () => {
                state.staffChoice = `staff:${staff.id}`;
                state.staffId = staff.id;
                state.staffName = staff.name;
                dateSummaryMaster.textContent = staff.name;
                Array.from(staffSlots.children).forEach((node) => node.classList.remove('time-slot--selected'));
                button.classList.add('time-slot--selected');
                state.date = null;
                state.time = null;
                summaryDate.textContent = '—';
                summaryTime.textContent = '—';
                clearTimes();
                dateContinueBtn.disabled = true;
                await loadAvailability();
            });
            staffSlots.appendChild(button);
        });
    }

    function renderCalendar() {
        calendarWeekdays.innerHTML = '';
        bookingConfig.i18n.weekdays.forEach((weekday) => {
            const weekdayEl = document.createElement('div');
            weekdayEl.className = 'weekday';
            weekdayEl.textContent = weekday;
            calendarWeekdays.appendChild(weekdayEl);
        });

        calendarMonth.textContent = new Intl.DateTimeFormat(bookingConfig.lang, { month: 'long', year: 'numeric' }).format(state.selectedMonth);
        calendarDays.innerHTML = '';

        const firstDay = new Date(state.selectedMonth.getFullYear(), state.selectedMonth.getMonth(), 1);
        const startOffset = (firstDay.getDay() + 6) % 7;
        const daysInMonth = new Date(state.selectedMonth.getFullYear(), state.selectedMonth.getMonth() + 1, 0).getDate();
        const daysInPrev = new Date(state.selectedMonth.getFullYear(), state.selectedMonth.getMonth(), 0).getDate();

        for (let i = startOffset - 1; i >= 0; i -= 1) {
            const day = document.createElement('div');
            day.className = 'day day--other';
            day.textContent = String(daysInPrev - i).padStart(2, '0');
            calendarDays.appendChild(day);
        }

        for (let dayNumber = 1; dayNumber <= daysInMonth; dayNumber += 1) {
            const dateValue = `${state.selectedMonth.getFullYear()}-${String(state.selectedMonth.getMonth() + 1).padStart(2, '0')}-${String(dayNumber).padStart(2, '0')}`;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'day';
            button.textContent = String(dayNumber).padStart(2, '0');

            const isAvailable = state.availableDates.includes(dateValue);
            if (!isAvailable) {
                button.classList.add('day--other');
                button.disabled = true;
            }
            if (state.date === dateValue) {
                button.classList.add('day--selected');
            }
            button.addEventListener('click', async () => {
                state.date = dateValue;
                state.time = null;
                summaryDate.textContent = formatDayForSummary(dateValue);
                summaryTime.textContent = '—';
                dateContinueBtn.disabled = true;
                renderCalendar();
                await loadSlots();
            });
            calendarDays.appendChild(button);
        }

        const totalCells = calendarDays.children.length;
        const trailing = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
        for (let dayNumber = 1; dayNumber <= trailing; dayNumber += 1) {
            const day = document.createElement('div');
            day.className = 'day day--other';
            day.textContent = String(dayNumber).padStart(2, '0');
            calendarDays.appendChild(day);
        }
    }

    async function loadAvailability() {
        if (!state.serviceId) {
            return;
        }
        if (state.requiresStaffChoice && state.staffChoice === null) {
            state.availableDates = [];
            renderCalendar();
            return;
        }
        clearTimes();
        const params = new URLSearchParams({
            service_id: String(state.serviceId),
            month: toMonthKey(state.selectedMonth),
        });
        if (state.staffId) {
            params.set('staff_id', String(state.staffId));
        }
        const response = await fetchJson(`${bookingConfig.routes.availability}?${params.toString()}`);
        state.availableDates = Array.isArray(response.available_dates) ? response.available_dates : [];
        renderCalendar();
    }

    function clearTimes() {
        timeSlots.innerHTML = '';
        timesEmptyState.style.display = 'none';
    }

    async function loadSlots() {
        if (!state.date || !state.serviceId) {
            return;
        }
        if (state.requiresStaffChoice && state.staffChoice === null) {
            return;
        }

        clearTimes();
        const params = new URLSearchParams({
            service_id: String(state.serviceId),
            date: state.date,
        });
        if (state.staffId) {
            params.set('staff_id', String(state.staffId));
        }
        const response = await fetchJson(`${bookingConfig.routes.slots}?${params.toString()}`);
        const times = Array.isArray(response.times) ? response.times : [];
        if (times.length === 0) {
            timesEmptyState.style.display = '';
            return;
        }

        times.forEach((timeValue) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'time-slot';
            button.textContent = timeValue;
            button.addEventListener('click', () => {
                state.time = timeValue;
                summaryTime.textContent = timeValue;
                Array.from(timeSlots.children).forEach((node) => node.classList.remove('time-slot--selected'));
                button.classList.add('time-slot--selected');
                dateContinueBtn.disabled = false;
            });
            timeSlots.appendChild(button);
        });
    }

    function openModal() {
        if (!state.serviceId || !state.date || !state.time) {
            return;
        }
        const service = getSelectedService();
        modalDate.textContent = summaryDate.textContent;
        modalTime.textContent = state.time;
        modalServices.innerHTML = service ? `
            <div class="bm-service-item">
                <div class="bm-service-name">${service.name}</div>
                <div class="bm-service-meta">${service.duration_label} · ${service.price_label}</div>
            </div>` : '';
        modalTotalCost.textContent = service ? service.price_label : `0 ${bookingConfig.currency}`;
        formServiceId.value = String(state.serviceId);
        formStaffId.value = state.staffId ? String(state.staffId) : '';
        formDate.value = state.date;
        formTime.value = state.time;
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeBookingModal() {
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    bookingSubmitForm.addEventListener('submit', (event) => {
        const firstName = bookingFirstName.value.trim();
        const lastName = bookingLastName.value.trim();
        formName.value = [firstName, lastName].filter(Boolean).join(' ').trim();
        if (!formName.value) {
            event.preventDefault();
            bookingFirstName.focus();
        }
    });

    serviceItems.forEach((item) => {
        const activate = () => setSelectedService(item.dataset.serviceId);
        item.addEventListener('click', activate);
        item.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                activate();
            }
        });
    });

    serviceContinueBtn.addEventListener('click', openDateStep);
    dateContinueBtn.addEventListener('click', openModal);
    bookingBackBtn.addEventListener('click', () => {
        dateStep.style.display = 'none';
        serviceStep.style.display = '';
        serviceAbout.style.display = '';
        serviceServices.style.display = '';
        window.scrollTo({ top: 0, behavior: 'auto' });
    });
    closeModal.addEventListener('click', closeBookingModal);
    overlay.addEventListener('click', (event) => {
        if (event.target === overlay) {
            closeBookingModal();
        }
    });
    document.getElementById('prevMonth').addEventListener('click', async () => {
        state.selectedMonth = new Date(state.selectedMonth.getFullYear(), state.selectedMonth.getMonth() - 1, 1);
        state.date = null;
        state.time = null;
        summaryDate.textContent = '—';
        summaryTime.textContent = '—';
        dateContinueBtn.disabled = true;
        await loadAvailability();
    });
    document.getElementById('nextMonth').addEventListener('click', async () => {
        state.selectedMonth = new Date(state.selectedMonth.getFullYear(), state.selectedMonth.getMonth() + 1, 1);
        state.date = null;
        state.time = null;
        summaryDate.textContent = '—';
        summaryTime.textContent = '—';
        dateContinueBtn.disabled = true;
        await loadAvailability();
    });

    bookingLangSelect.addEventListener('change', (event) => {
        const url = new URL(window.location.href);
        url.searchParams.set('lang', event.target.value);
        window.location.href = url.toString();
    });
    document.querySelectorAll('.js-lang-btn').forEach((button) => {
        button.addEventListener('click', () => {
            const url = new URL(window.location.href);
            url.searchParams.set('lang', button.dataset.lang);
            window.location.href = url.toString();
        });
    });

    if (state.serviceId) {
        setSelectedService(state.serviceId);
    } else {
        renderSelectedService();
        serviceContinueBtn.disabled = true;
    }
</script>
@endsection
