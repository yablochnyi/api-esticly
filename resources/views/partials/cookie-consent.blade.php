<section class="cookie-banner" id="cookieBanner" aria-labelledby="cookieTitle" hidden>
    <div class="cookie-banner__copy">
        <h2 id="cookieTitle">{{ __('cookies.title') }}</h2>
        <p>{{ __('cookies.description') }} <a href="{{ route('legal.privacy.localized', ['locale' => $currentLocale]) }}#cookies">{{ __('cookies.details') }}</a></p>
    </div>
    <div class="cookie-actions">
        <button type="button" class="cookie-button" data-cookie-choice="reject">{{ __('cookies.reject') }}</button>
        <button type="button" class="cookie-button" data-cookie-choice="accept">{{ __('cookies.accept') }}</button>
        <button type="button" class="cookie-button cookie-button--quiet" data-cookie-settings>{{ __('cookies.settings') }}</button>
    </div>
</section>

<dialog class="cookie-dialog" id="cookieDialog" aria-labelledby="cookieDialogTitle" aria-describedby="cookieDialogDescription">
    <div class="cookie-dialog__header">
        <h2 id="cookieDialogTitle">{{ __('cookies.settings') }}</h2>
        <button type="button" class="cookie-close" id="cookieClose" aria-label="{{ __('cookies.close') }}" title="{{ __('cookies.close') }}">&#215;</button>
    </div>
    <p id="cookieDialogDescription">{{ __('cookies.description') }}</p>
    <div class="cookie-category">
        <div class="cookie-category__title"><h3>{{ __('cookies.necessary') }}</h3><span>{{ __('cookies.always_on') }}</span></div>
        <p>{{ __('cookies.necessary_description') }}</p>
    </div>
    <div class="cookie-category">
        <label class="cookie-category__title" for="cookieAnalytics"><span>{{ __('cookies.analytics') }}</span><input id="cookieAnalytics" type="checkbox" aria-describedby="cookieAnalyticsDescription"></label>
        <p id="cookieAnalyticsDescription">{{ __('cookies.analytics_description') }}</p>
    </div>
    <p>{{ __('cookies.retention') }}</p>
    <p><a href="{{ route('legal.privacy.localized', ['locale' => $currentLocale]) }}#cookies">{{ __('cookies.details') }}</a> · <a href="https://policies.google.com/privacy" rel="noopener" target="_blank">{{ __('cookies.google_privacy') }}</a></p>
    <div class="cookie-actions">
        <button type="button" class="cookie-button" data-cookie-choice="reject">{{ __('cookies.reject') }}</button>
        <button type="button" class="cookie-button" data-cookie-choice="accept">{{ __('cookies.accept') }}</button>
        <button type="button" class="cookie-button cookie-button--quiet" id="cookieSave">{{ __('cookies.save') }}</button>
    </div>
</dialog>
