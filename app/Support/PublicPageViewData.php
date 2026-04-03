<?php

namespace App\Support;

class PublicPageViewData
{
    public static function marketing(string $locale, array $siteLocales, array $seo): array
    {
        $landingUrl = $seo['localized_urls'][$locale] ?? route('marketing.localized', ['locale' => $locale]);
        $heroImagePath = "assets/public/hero-{$locale}.png";

        return array_merge(
            self::sharedLayoutData(
                locale: $locale,
                siteLocales: $siteLocales,
                canonicalUrl: $seo['canonical_url'],
                alternateUrls: $seo['alternate_urls'],
                xDefaultUrl: $seo['x_default_url'],
                languageSwitcherUrls: $seo['localized_urls'],
                headerNavLinks: [
                    ['label' => __('landing.nav.features'), 'url' => '#features'],
                    ['label' => __('landing.nav.professionals'), 'url' => '#for-professionals'],
                    ['label' => __('landing.nav.salons'), 'url' => '#for-salons'],
                    ['label' => __('landing.nav.pricing'), 'url' => '#pricing'],
                    ['label' => __('landing.nav.faq'), 'url' => '#faq'],
                ],
                ctaUrl: $landingUrl,
                showFooterTop: true,
                footerNavLinks: [
                    ['label' => __('landing.nav.features'), 'url' => '#features'],
                    ['label' => __('landing.nav.professionals'), 'url' => '#for-professionals'],
                    ['label' => __('landing.nav.salons'), 'url' => '#for-salons'],
                    ['label' => __('landing.nav.pricing'), 'url' => '#pricing'],
                    ['label' => __('landing.nav.faq'), 'url' => '#faq'],
                ],
                seoTitle: __('landing.seo.title'),
                seoDescription: __('landing.seo.description'),
            ),
            [
                'heroImage' => self::assetIfExists($heroImagePath) ?? asset('assets/public/hero-uk.png'),
                'reviews' => __('landing.reviews.items'),
                'faqItems' => __('landing.faq.items'),
                'featureItems' => __('landing.features.items'),
                'proItems' => __('landing.audience.pros_items'),
                'salonItems' => __('landing.audience.salons_items'),
                'basicItems' => __('landing.pricing.basic_items'),
                'proPlanItems' => __('landing.pricing.pro_items'),
            ]
        );
    }

    public static function legal(
        string $locale,
        array $siteLocales,
        string $canonicalUrl,
        string $xDefaultUrl,
        array $languageSwitcherUrls,
        array $alternateUrls,
        string $seoTitle,
        string $seoDescription,
        array $headerNavLinks,
    ): array {
        $landingUrl = route('marketing.localized', ['locale' => $locale]);

        return self::sharedLayoutData(
            locale: $locale,
            siteLocales: $siteLocales,
            canonicalUrl: $canonicalUrl,
            alternateUrls: $alternateUrls,
            xDefaultUrl: $xDefaultUrl,
            languageSwitcherUrls: $languageSwitcherUrls,
            headerNavLinks: $headerNavLinks,
            ctaUrl: $landingUrl,
            showFooterTop: true,
            footerNavLinks: [
                ['label' => __('landing.nav.features'), 'url' => route('marketing.localized', ['locale' => $locale]) . '#features'],
                ['label' => __('landing.nav.professionals'), 'url' => route('marketing.localized', ['locale' => $locale]) . '#for-professionals'],
                ['label' => __('landing.nav.salons'), 'url' => route('marketing.localized', ['locale' => $locale]) . '#for-salons'],
                ['label' => __('landing.nav.pricing'), 'url' => route('marketing.localized', ['locale' => $locale]) . '#pricing'],
                ['label' => __('landing.nav.faq'), 'url' => route('marketing.localized', ['locale' => $locale]) . '#faq'],
            ],
            seoTitle: $seoTitle,
            seoDescription: $seoDescription,
        );
    }

    private static function sharedLayoutData(
        string $locale,
        array $siteLocales,
        string $canonicalUrl,
        array $alternateUrls,
        string $xDefaultUrl,
        array $languageSwitcherUrls,
        array $headerNavLinks,
        string $ctaUrl,
        bool $showFooterTop,
        array $footerNavLinks,
        string $seoTitle,
        string $seoDescription,
    ): array {
        $landingHomeUrl = route('marketing.localized', ['locale' => $locale]);
        $faviconIco = self::assetIfExists('favicon.ico');
        $iconPng = self::assetIfExists('icon.png');
        $logoPng = self::assetIfExists('logo.png');

        return [
            'currentLocale' => $locale,
            'siteLocales' => $siteLocales,
            'landingHomeUrl' => $landingHomeUrl,
            'seoTitle' => $seoTitle,
            'seoDescription' => $seoDescription,
            'seoCanonicalUrl' => $canonicalUrl,
            'seoAlternateUrls' => $alternateUrls,
            'seoXDefaultUrl' => $xDefaultUrl,
            'faviconIco' => $faviconIco,
            'iconPng' => $iconPng,
            'appleTouchIcon' => $iconPng ?? $logoPng,
            'socialImage' => $logoPng ?? $iconPng,
            'languageOptions' => self::buildLanguageOptions($siteLocales, $languageSwitcherUrls, $locale),
            'headerNavLinks' => $headerNavLinks,
            'headerCtaLabel' => __('landing.nav.download'),
            'headerCtaUrl' => $ctaUrl,
            'showFooterTop' => $showFooterTop,
            'footerNavLinks' => $footerNavLinks,
            'footerEmail' => __('landing.footer.email_value'),
        ];
    }

    private static function buildLanguageOptions(array $siteLocales, array $urls, string $currentLocale): array
    {
        $options = [];

        foreach ($siteLocales as $code => $meta) {
            $options[] = [
                'code' => $code,
                'label' => strtoupper($code),
                'url' => $urls[$code] ?? route('marketing.localized', ['locale' => $code]),
                'selected' => $code === $currentLocale,
            ];
        }

        return $options;
    }

    private static function assetIfExists(string $path): ?string
    {
        return file_exists(public_path($path)) ? asset($path) : null;
    }
}
