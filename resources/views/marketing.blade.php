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

        $brandIconRel = $pickAsset($brandIconCandidates);
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
                <div class="store-buttons" aria-label="App downloads">
                    <a class="store-btn" href="#" aria-label="Download on the App Store">
                        <span class="store-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16.37 12.12c.02 2.35 2.06 3.14 2.08 3.15-.02.05-.32 1.11-1.05 2.19-.63.93-1.29 1.85-2.32 1.87-1.02.02-1.35-.61-2.52-.61-1.17 0-1.53.59-2.5.63-1 .04-1.77-1-2.4-1.92-1.29-1.86-2.27-5.25-.95-7.53.65-1.13 1.82-1.85 3.1-1.87.97-.02 1.88.66 2.52.66.64 0 1.83-.82 3.08-.7.52.02 1.98.21 2.92 1.59-.08.05-1.74 1.01-1.72 2.54Zm-2.33-5.95c.53-.64.89-1.52.79-2.4-.77.03-1.71.51-2.26 1.15-.49.57-.92 1.46-.81 2.32.86.07 1.75-.44 2.28-1.07Z"/></svg>
                        </span>
                        <span class="store-copy"><small>Download on the</small><strong>App Store</strong></span>
                    </a>
                    <a class="store-btn" href="#" aria-label="Get it on Google Play">
                        <span class="store-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3.3 2.2c-.2.2-.3.6-.3 1.1v17.4c0 .5.1.9.3 1.1l.1.1 9.8-9.8v-.2L3.4 2.1l-.1.1Zm14.3 6.5-3.2 3.2 3.2 3.2.1-.1 3.8-2.1c1.1-.6 1.1-1.5 0-2.1l-3.8-2.1-.1.1Zm-.9.5-11 11 .1.1c.3.2.7.2 1.2-.1l12.5-7-2.8-2.8Zm-9.7-8c-.5-.3-.9-.3-1.2-.1l-.1.1 11 11 2.8-2.8-12.5-7Z"/></svg>
                        </span>
                        <span class="store-copy"><small>Get it on</small><strong>Google Play</strong></span>
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
        <h2 class="section-title">Функціонал, який реально закриває щоденну роботу салону</h2>
        <p class="section-sub">Esticly не лише про записи. Це повний робочий контур: запис -> візит -> нагадування -> повторний продаж -> аналітика -> контроль доступів.</p>
        <div class="grid-3">
            <article class="feature"><div class="icon">📅</div><h3>Календар і записи</h3><p>Панель день/тиждень, ручне створення візитів, статуси, буфери до/після послуги, комбо-послуги, перевірка конфліктів слотів.</p></article>
            <article class="feature"><div class="icon">👤</div><h3>Клієнтська база</h3><p>Картка клієнта, історія візитів, нотатки, blacklist / waitlist, фото та вкладення, швидкий доступ до повторних дій.</p></article>
            <article class="feature"><div class="icon">🌐</div><h3>Онлайн-запис</h3><p>Публічна сторінка майстра/салону, доступні слоти, послуги, промокоди, посилання на соцмережі, QR та share-посилання.</p></article>
            <article class="feature"><div class="icon">🔔</div><h3>Нагадування</h3><p>Push / Telegram / Email сценарії для зниження no-show, повідомлення майстру про нові записи та контроль доставки.</p></article>
            <article class="feature"><div class="icon">📣</div><h3>Маркетинг</h3><p>Автосценарії після візиту, промокоди, реактивації, короткі посилання, підготовка до сегментації та повторних продажів.</p></article>
            <article class="feature"><div class="icon">📊</div><h3>Аналітика</h3><p>Виручка, кількість візитів, середній чек, скасування, завантаження по днях/годинах і базові KPI для керування салоном.</p></article>
            <article class="feature"><div class="icon">👥</div><h3>Команда / Staff</h3><p>Співробітники, розклад, послуги, доступи/permissions, обмеження видимості клієнтів і робота у межах своєї ролі.</p></article>
            <article class="feature"><div class="icon">🔐</div><h3>Безпека та контроль</h3><p>Аудит-лог, DSAR-операції, анонімізація клієнтів, шифрування чутливих полів, контролі доступу в адмінці та мобільному API.</p></article>
            <article class="feature"><div class="icon">☁️</div><h3>Backup & Restore</h3><p>Автобекапи БД і проекту, завантаження в S3-compatible storage, retention, restore-check та окремий лог канал для перевірок.</p></article>
        </div>
    </section>

    <section class="section">
        <div class="split">
            <div class="card list-card">
                <h2 class="section-title" style="font-size:30px;margin-bottom:6px;">Для кого це працює найкраще</h2>
                <p class="section-sub">Один продукт, але різні сценарії: приватний майстер, салон з командою, бізнес який росте та хоче керованість.</p>
                <ul>
                    <li><span class="bullet">1</span><span><strong style="color:var(--text)">Приватний майстер</strong><br>Швидкий календар, клієнти, нагадування, публічна сторінка запису та базова аналітика без перевантаження.</span></li>
                    <li><span class="bullet">2</span><span><strong style="color:var(--text)">Салон з командою</strong><br>Staff, графіки, послуги, доступи, контроль записів, спільна база клієнтів і робочі процеси в одному контурі.</span></li>
                    <li><span class="bullet">3</span><span><strong style="color:var(--text)">Операційно зрілий бізнес</strong><br>GDPR/DSAR, аудит-лог, резервні копії, відновлення, аналітика та база для подальшої автоматизації продажів.</span></li>
                </ul>
                <figure class="inline-phone-preview">
                    <div class="inline-phone-preview__media">
                        <img src="{{ asset('img/calendar.png') }}" alt="Календар Esticly у мобільному застосунку" loading="lazy">
                    </div>
                    <figcaption>
                        <strong>Календар / День</strong>
                        Мобільний екран для щоденної роботи майстра: швидкі слоти, статуси візитів, керування днем без таблиць і чатів.
                    </figcaption>
                </figure>
            </div>
            <div class="card gallery-card">
                <figure class="shot-card shot-card-compact">
                    <img src="{{ asset('img/client.png') }}" alt="Картка клієнта в Esticly" loading="lazy">
                    <figcaption><strong>Картка клієнта</strong>Історія візитів, дохід, нотатки, експорт / видалення (анонімізація) та швидкі дії.</figcaption>
                </figure>
                <figure class="shot-card shot-card-compact">
                    <img src="{{ asset('img/analytic.png') }}" alt="Аналітика в Esticly" loading="lazy">
                    <figcaption><strong>Аналітика / Дашборд</strong>Виручка, кількість візитів, середній чек, скасування та динаміка за період.</figcaption>
                </figure>
            </div>
        </div>
    </section>

    <section class="section" id="ops">
        <h2 class="section-title">Що отримує салон у щоденній роботі, окрім красивого інтерфейсу</h2>
        <p class="section-sub">Esticly допомагає не тільки вести записи, а й тримати під контролем клієнтів, команду, повторні продажі та якість сервісу без ручного хаосу.</p>
        <div class="ops-grid">
            <div class="card ops-card">
                <span class="tagline">Що це дає власнику салону</span>
                <ul class="checklist">
                    <li><span class="checkmark">✓</span><span>Менше пропущених візитів завдяки нагадуванням клієнтам і повідомленням команді</span></li>
                    <li><span class="checkmark">✓</span><span>Швидша робота адміністратора: запис, перенесення, статуси та історія клієнта в одному місці</span></li>
                    <li><span class="checkmark">✓</span><span>Контроль дій у команді: хто що змінив у клієнтах, записах і налаштуваннях</span></li>
                    <li><span class="checkmark">✓</span><span>Безпечна робота з даними клієнтів: експорт або видалення/анонімізація на запит</span></li>
                    <li><span class="checkmark">✓</span><span>Збережені медіа та дані салону: резервні копії та базовий захист від втрат</span></li>
                    <li><span class="checkmark">✓</span><span>Підготовка до росту: команда, ролі, доступи, аналітика і повторні продажі в одному продукті</span></li>
                </ul>
            </div>
            <div class="card ops-card">
                <span class="tagline">Типовий робочий цикл салону в Esticly</span>
                <div class="timeline">
                    <div class="timeline-item"><div class="time">Крок 1</div><div class="box"><strong>Клієнт бронює слот</strong><span>Через лендінг / share-link / QR / менеджер створює запис вручну.</span></div></div>
                    <div class="timeline-item"><div class="time">Крок 2</div><div class="box"><strong>Система нагадує</strong><span>Надсилання нагадувань клієнту та повідомлення команді про нові/змінені записи.</span></div></div>
                    <div class="timeline-item"><div class="time">Крок 3</div><div class="box"><strong>Після візиту — повторний контакт</strong><span>Маркетинг-автоматизація, промокод або запит на відгук (через чергу та delay).</span></div></div>
                    <div class="timeline-item"><div class="time">Крок 4</div><div class="box"><strong>Керівник бачить цифри</strong><span>Аналітика по виручці, завантаженню, середньому чеку та скасуванням.</span></div></div>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <h2 class="section-title">Що ще можна показати на лендінгу (коли будуть скріни / кейси)</h2>
        <p class="section-sub">Нижче готові блоки для роста конверсии: можна швидко заповнити реальними цифрами, відгуками та сценаріями використання.</p>
        <div class="grid-2">
            <div class="usecase"><h3>Кейси салонів</h3><p>До/після: як змінилася кількість пропущених записів, повторних візитів і швидкість обробки клієнтів після переходу на Esticly.</p></div>
            <div class="usecase"><h3>Онбординг за 1 день</h3><p>Покажи простий сценарій старту: імпорт клієнтів, налаштування послуг, графіка, лендінгу та тестового запису.</p></div>
            <div class="usecase"><h3>Ролі в команді</h3><p>Пояснення різниці між owner і staff: хто бачить календар, хто може редагувати клієнтів, хто запускає експорт/видалення.</p></div>
            <div class="usecase"><h3>Безпека для ЄС</h3><p>Коротко про шифрування PII, аудит-лог, DSAR, retention і контроль доступів — це підвищує довіру салонів.</p></div>
        </div>
    </section>

    <section class="section" id="pricing">
        <h2 class="section-title">Тарифи, які масштабуються разом із салоном</h2>
        <p class="section-sub">Почни з базового плану, а коли росте команда та навантаження — переходь на розширений без міграції на інший продукт.</p>
        <div class="pricing">
            <div class="price-card">
                <div class="price-tag">Start / Basic</div>
                <div class="price-value">49 PLN <span>/ місяць</span></div>
                <p class="section-sub" style="font-size:14px;">Для майстра або невеликої студії: порядок у записах, клієнтах і нагадуваннях.</p>
                <ul>
                    <li>Календар і записи</li>
                    <li>Клієнтська база та нотатки</li>
                    <li>Онлайн-запис / лендінг</li>
                    <li>Нагадування та базова аналітика</li>
                    <li>Мобільний доступ до ключових функцій</li>
                </ul>
            </div>
            <div class="price-card featured">
                <div class="price-tag">Pro / Salon</div>
                <div class="price-value">79 PLN <span>/ місяць</span></div>
                <p class="section-sub" style="font-size:14px;">Для салонів з командою та потребою в маркетингу, доступах і більш глибокому контролі процесів.</p>
                <ul>
                    <li>Все з Basic</li>
                    <li>Staff / ролі / доступи</li>
                    <li>Маркетинг-автоматизації та промокоди</li>
                    <li>Розширена аналітика</li>
                    <li>Операційні інструменти та підготовка до масштабування</li>
                </ul>
            </div>
        </div>
        <div class="note-card">Остаточний склад тарифів можна гнучко змінювати: окремо продавати маркетинг-канали (SMS/Viber), розширену аналітику, кастомний домен, DSAR/backup-опції для салонів PRO+.</div>
    </section>

    <section class="section" id="cta">
        <div class="card cta">
            <div>
                <h2 class="section-title" style="margin:0;font-size:30px;">Готовий протестувати Esticly на реальних записах?</h2>
                <p>Почни з демо або підключи перший салон. Esticly допомагає прибрати хаос із месенджерів і перетворити процес запису на керовану систему.</p>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;">
                @if (Route::has('register'))<a class="btn btn-primary" href="{{ route('register') }}">Створити акаунт</a>@endif
                @if (Route::has('login'))<a class="btn" href="{{ route('login') }}">Увійти</a>@endif
            </div>
        </div>
    </section>

    <footer>
        © {{ date('Y') }} {{ config('app.name', 'Esticly') }}. CRM для майстрів і салонів: записи, клієнти, нагадування, маркетинг, аналітика та мобільна робота в одному місці.
    </footer>
</main>
</body>
</html>
