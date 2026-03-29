<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>website-esticly</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/src/style.css') }}">

</head>
<body>

<!-- Header Navigation -->
<header class="header">
    <div class="header__container">
        <button class="header__menu-btn" id="menuBtn">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <div class="header__logo">
            <img src="{{asset('assets/public/logo.png')}}" alt="Logo" width="80" height="80">
        </div>

        <nav class="header__nav" id="mobileMenu">
            <button class="header__close-btn" id="closeBtn">
            </button>
            <a href="#" class="header__link">Возможности</a>
            <a href="#" class="header__link">Для мастеров</a>
            <a href="#" class="header__link">Для салонов</a>
            <a href="#" class="header__link">Цены</a>
            <a href="#" class="header__link">FAQ</a>
            <button class="header__btn header__btn--mobile">Скачать приложение</button>
        </nav>

        <div class="header__actions">
            <select class="header__lang-select">
                <option value="ru">RU</option>
                <option value="en">EN</option>
                <option value="ua">UA</option>
            </select>
            <button class="header__btn header__btn--desktop">Скачать приложение</button>
        </div>
    </div>
</header>





<!-- Hero Section -->
<section class="hero">
    <div class="hero__container">
        <div class="hero__content">
            <h1 class="hero__title">Управляйте записями и клиентами без хаоса</h1>

            <p class="hero__description">
                Esticly — это CRM для салонов красоты и частных мастеров, которая помогает автоматизировать запись клиентов, управлять клиентской базой, отправлять напоминания и контролировать доход в одном приложении.
            </p>

            <div class="hero__features">
                <div class="hero__feature">
                    <img src="{{asset('assets/public/purplestar.png')}}" alt="">
                    <p>Система онлайн-записи клиентов позволяет принимать заявки 24/7 без звонков, сообщений и длительных переписок.</p>
                </div>

                <div class="hero__feature">
                    <img src="{{asset('assets/public/purplestar.png')}}" alt="">
                    <p>Это снижает нагрузку на мастера или администратора, уменьшает количество ошибок и помогает увеличить число записей.</p>
                </div>

                <div class="hero__feature">
                    <img src="{{asset('assets/public/purplestar.png')}}" alt="">
                    <p >Esticly делает процесс взаимодействия с клиентами более удобным, а бизнес — более организованным и предсказуемым.</p>
                </div>
            </div>

            <div class="hero__buttons">
                <button class="hero__btn hero__btn--primary">Скачать приложение</button>
                <button class="hero__btn hero__btn--secondary">Попробовать бесплатно 7 дней</button>
            </div>
        </div>

        <div class="hero__image">
            <img src="{{asset('assets/public/hero-section-image.png')}}" alt="App mockup" class="hero__phones">
        </div>
    </div>

    <!-- Bottom section -->
    <div class="hero__bottom">
        <div class="hero__bottom-container">
            <p class="hero__bottom-text">Esticly делает процесс взаимодействия с клиентами более удобным, а бизнес — более организованным и предсказуемым.</p>
            <div class="hero__apps">
                <img src="{{asset('assets/public/appstore.png')}}" alt="App Store" class="hero__app-badge">
                <img src="{{asset('assets/public/playstore.png')}}" alt="Google Play" class="hero__app-badge">
            </div>
        </div>
    </div>
</section>






<!-- Problems and Solutions Section -->
<section class="problems">
    <div class="problems__container">
        <h2 class="problems__title">Проблемы и решения</h2>

        <div class="problems__content">
            <!-- Left Card -->
            <div class="problems__card problems__card--white">
                <h3 class="problems__card-title">
                    <span class="problems__icon"><img src="{{asset('assets/public/message-icon.png')}}" alt=""></span>
                    Записи вручную/мессенджеры/соц.сети
                </h3>

                <p class="problems__card-text">
                    Большинство мастеров и салонов красоты до сих пор ведут запись клиентов через:
                </p>

                <ul class="problems__list">
                    <li class="problems__list-item">
                        <span class="problems__list-icon"><img src="{{asset('assets/public/x.png')}}" alt=""></span>
                        Мессенджеры
                    </li>
                    <li class="problems__list-item">
                        <span class="problems__list-icon"><img src="{{asset('assets/public/x.png')}}" alt=""></span>
                        Социальные сети
                    </li>
                    <li class="problems__list-item">
                        <span class="problems__list-icon"><img src="{{asset('assets/public/x.png')}}" alt=""></span>
                        Блокноты
                    </li>
                    <li class="problems__list-item">
                        <span class="problems__list-icon"><img src="{{asset('assets/public/x.png')}}" alt=""></span>
                        Заметки в телефоне
                    </li>
                </ul>

                <p class="problems__card-text problems__card-text--bottom">
                    На первый взгляд это кажется удобным, но на практике такой подход создает хаос и мешает росту бизнеса.
                </p>

                <p class="problems__result">
                    <strong class="strong">Результат:</strong>  Сообщения теряются, клиенты забывают о визитах, часть записей не фиксируется, а реальная загрузка и доход остаются неочевидными. В результате мастер или салон ежедневно теряет деньги, время и контроль над процессами. Если нет единой CRM-системы для салона красоты, бизнес начинает работать реактивно, а не системно.
                </p>
            </div>

            <!-- Right Card - Blue -->
            <div class="problems__card problems__card--blue">
                <div class="problems__card-header">
                    <span class="problems__brand-icon"><img src="{{asset('assets/public/mini_logo.png')}}" alt=""></span>
                    <h3 class="problems__card-title problems__card-title--blue">Esticly</h3>
                </div>

                <p class="problems__card-text problems__card-text--blue">
                    Esticly объединяет все ключевые процессы работы beauty-бизнеса в одном месте:
                </p>

                <ul class="problems__features-list">
                    <li class="problems__feature-item">
                        <span class="problems__feature-icon"><img src="{{asset('assets/public/tick_green.png')}}" alt=""></span>
                        Запись клиентов
                    </li>
                    <li class="problems__feature-item">
                        <span class="problems__feature-icon"><img src="{{asset('assets/public/tick_green.png')}}" alt=""></span>
                        Клиентскую базу
                    </li>
                    <li class="problems__feature-item">
                        <span class="problems__feature-icon"><img src="{{asset('assets/public/tick_green.png')}}" alt=""></span>
                        Автоматические напоминания
                    </li>
                    <li class="problems__feature-item">
                        <span class="problems__feature-icon"><img src="{{asset('assets/public/tick_green.png')}}" alt=""></span>
                        Аналитику и управление ежедневными задачами
                    </li>
                </ul>

                <p class="problems__card-text problems__card-text--blue">
                    Вместо нескольких разрозненных инструментов вы получаете одну CRM для мастеров и салонов красоты, в которой все процессы связаны между собой.
                </p>

                <p class="problems__highlight">
                    <strong class="second_strong"> Это значит,</strong> что вы видите полную картину бизнеса: кто записан, кто не пришел, сколько вы зарабатываете, какие услуги приносят больше дохода и как распределяется загрузка. Такое решение помогает работать спокойнее, быстрее и эффективнее, а также увеличивает прибыль за счет автоматизации и порядка.
                </p>
            </div>
        </div>
    </div>
</section>


<!-- Features Section -->
<section class="features">
    <div class="features__container">
        <h2 class="features__title">Возможности</h2>

        <div class="features__grid">
            <div class="features__card">
                <div class="features__image">
                    <img src="{{asset('assets/public/features1.png')}}" alt="Онлайн-запись" class="features__img">
                </div>
                <h3 class="features__card-title">
                    <span class="features__icon"><img src="{{asset('assets/public/phone_icon.png')}}" alt=""></span>
                    Онлайн-запись клиентов.
                </h3>
                <p class="features__card-text">
                    Клиенты могут записываться самостоятельно в любое удобное время. Это упрощает коммуникацию, помогает не терять обращения и повышает количество записей. Для SEO этот блок усиливает релевантность по запросам «онлайн-запись клиентов», «программа для записи клиентов» и «онлайн-запись для салона красоты».
                </p>
            </div>

            <div class="features__card">
                <div class="features__image">
                    <img src="{{asset('assets/public/feature2.png')}}" alt="Автоматические напоминания" class="features__img">
                </div>
                <h3 class="features__card-title">
                    <span class="features__icon"><img src="{{asset('assets/public/icon2.png')}}" alt=""></span>
                    Автоматические напоминания.
                </h3>
                <p class="features__card-text">
                    Система отправляет напоминания о визите, что снижает количество пропущенных записей и помогает увеличить доход. Напоминания клиентам — одна из самых ценных функций для мастеров и салонов, потому что она напрямую влияет на загрузку и прибыль.

                </p>
            </div>

            <div class="features__card">
                <div class="features__image">
                    <img src="{{asset('assets/public/feature3.png')}}" alt="Клиентская база" class="features__img">
                </div>
                <h3 class="features__card-title">
                    <span class="features__icon"><img src="{{asset('assets/public/icon3.png')}}" alt=""></span>
                    Клиентская база.
                </h3>
                <p class="features__card-text">
                    Вся история клиентов хранится в одном месте: контакты, заметки, история визитов, предпочтения, комментарии. Это помогает улучшить сервис и выстраивать долгосрочные отношения с клиентами.
                </p>
            </div>

            <div class="features__card">
                <div class="features__image">
                    <img src="{{asset('assets/public/feature4.png')}}" alt="Аналитика дохода" class="features__img">
                </div>
                <h3 class="features__card-title">
                    <span class="features__icon">📊</span>
                    Аналитика дохода.
                </h3>
                <p class="features__card-text">
                    Вы можете видеть, сколько зарабатываете, какие услуги востребованы, где есть недозагрузка и как меняется результат со временем. Аналитика помогает принимать решения не на ощущениях, а на цифрах.
                </p>
            </div>
        </div>
    </div>
</section>



<!-- CTA Section -->
<section class="hero-cta">
    <div class="hero-cta__container">
        <div class="hero-cta__content">
            <h2 class="hero-cta__title">
                Управляйте записями Esticly развивается вместе с пользователями.
            </h2>

            <p class="hero-cta__text">
                Если вам не хватает какой-то функции, вы можете отправить запрос прямо из приложения, и команда рассмотрит возможность ее добавления. Это важное отличие от многих CRM-систем, где пользователю приходится подстраиваться под продукт.
            </p>


        </div>

        <div class="hero-cta__image">
            <img src="{{asset('assets/public/Frame 11.png')}}" alt="Esticly App">
        </div>
    </div>


    </div>
    <div class="hero-cta__bottom">
        <div class="hero-cta__bottom-container">
            <p class="hero-cta__bottom-text">Такой подход делает Esticly более гибкой CRM для салона красоты и мастеров. Вы получаете не просто программу для записи клиентов, а живой инструмент, который адаптируется под реальные задачи beauty-индустрии.</p>
            <div class="hero-cta__apps">
                <img src="{{asset('assets/public/appstore.png')}}" alt="App Store" class="hero-cta__app-badge">
                <img src="{{asset('assets/public/playstore.png')}}" alt="Google Play" class="hero-cta__app-badge">
            </div>
        </div>
</section>



<!-- For Whom Section -->
<section class="for-whom">
    <div class="for-whom__container">
        <h2 class="for-whom__title">Для кого Esticly</h2>

        <div class="for-whom__content">
            <div class="for-whom__card for-whom__card--white">
                <h3 class="for-whom__card-title">
                    <span class="for-whom__icon"><img src="{{asset('assets/public/icon5.png')}}" alt=""></span>
                    Для частных мастеров.
                </h3>

                <p class="for-whom__card-text">
                    Esticly помогает упростить ежедневную работу:
                </p>

                <ul class="for-whom__list">
                    <li class="for-whom__list-item">
                        <span class="for-whom__list-icon"><img src="{{asset('assets/public/tick2.png')}}" alt=""></span>
                        Быстро вести запись клиентов
                    </li>
                    <li class="for-whom__list-item">
                        <span class="for-whom__list-icon"><img src="{{asset('assets/public/tick2.png')}}" alt=""></span>
                        Не терять контакты
                    </li>
                    <li class="for-whom__list-item">
                        <span class="for-whom__list-icon"><img src="{{asset('assets/public/tick2.png')}}" alt=""></span>
                        Отправлять напоминания
                    </li>
                    <li class="for-whom__list-item">
                        <span class="for-whom__list-icon"><img src="{{asset('assets/public/tick2.png')}}" alt=""></span>
                        Контролировать доход без сложных настроек
                    </li>
                </ul>

                <p class="for-whom__card-text">
                    Это удобная CRM для мастера маникюра, косметолога, бровиста, парикмахера и других специалистов.
                </p>
            </div>

            <div class="for-whom__card for-whom__card--blue">
                <h3 class="for-whom__card-title for-whom__card-title--blue">
                    <span class="for-whom__icon"><img src="{{asset('assets/public/icon6.png')}}" alt=""></span>
                    Для салонов красоты.
                </h3>

                <p class="for-whom__card-text for-whom__card-text--blue">
                    Esticly поддержит салонам, которым важно:
                </p>

                <ul class="for-whom__list">
                    <li class="for-whom__list-item for-whom__list-item--blue">
                        <span class="for-whom__list-icon for-whom__list-icon--blue"><img src="{{asset('assets/public/tick2.png')}}" alt=""></span>
                        Управлять командой
                    </li>
                    <li class="for-whom__list-item for-whom__list-item--blue">
                        <span class="for-whom__list-icon for-whom__list-icon--blue"><img src="{{asset('assets/public/tick2.png')}}" alt=""></span>
                        Видеть общую загрузку
                    </li>
                    <li class="for-whom__list-item for-whom__list-item--blue">
                        <span class="for-whom__list-icon for-whom__list-icon--blue"><img src="{{asset('assets/public/tick2.png')}}" alt=""></span>
                        Контролировать процессы
                    </li>
                    <li class="for-whom__list-item for-whom__list-item--blue">
                        <span class="for-whom__list-icon for-whom__list-icon--blue"><img src="{{asset('assets/public/tick2.png')}}" alt=""></span>
                        Системно работать с клиентами
                    </li>
                </ul>

                <p class="for-whom__card-text for-whom__card-text--blue">
                    В будущем это станется основой для масштабирования бизнеса, роста повторных визитов и повышения качества сервиса.
                </p>
            </div>
        </div>
    </div>
</section>





<!-- Pricing Section -->
<section class="pricing">
    <div class="pricing__container">
        <h2 class="pricing__title">Тарифы</h2>

        <p class="pricing__description">
            Вы можете начать с бесплатного тестового периода на 7 дней, чтобы оценить возможности CRM и понять, как она помогает в ежедневной работе.
        </p>

        <div class="pricing__grid">
            <div class="pricing__card">
                <h3 class="pricing__card-title">Обычный тариф</h3>

                <div class="pricing__price">
                    <span class="pricing__amount">59 zł</span>
                    <span class="pricing__currency"></span>
                    <span class="pricing__period">/месяц</span>
                </div>

                <p class="pricing__card-description">
                    Он подходит частным мастерам, которым нужен надежный инструмент для:
                </p>

                <ul class="pricing__features">
                    <li class="pricing__feature">
                        <span class="pricing__check"><img src="{{asset('assets/public/tick3.png')}}" alt=""></span>
                        Управления клиентами
                    </li>
                    <li class="pricing__feature">
                        <span class="pricing__check"><img src="{{asset('assets/public/tick3.png')}}" alt=""></span>
                        Управления записями
                    </li>
                    <li class="pricing__feature">
                        <span class="pricing__check"><img src="{{asset('assets/public/tick3.png')}}" alt=""></span>
                        Управления напоминаниями
                    </li>
                    <li class="pricing__feature">
                        <span class="pricing__check"><img src="{{asset('assets/public/tick3.png')}}" alt=""></span>
                        Управления аналитикой
                    </li>
                </ul>

                <button class="pricing__btn">Выбрать</button>
            </div>

            <div class="pricing__card pricing__card--featured">
                <div class="pricing__badge">MOST POPULAR</div>

                <h3 class="pricing__card-title">PRO тариф</h3>

                <div class="pricing__price">
                    <span class="pricing__amount">89 zł</span>
                    <span class="pricing__currency"></span>
                    <span class="pricing__period">/месяц</span>
                    <span class="pricing__free-trial">7 дней бесплатно</span>
                </div>

                <p class="pricing__card-description">
                    Он подходит салонам красоты и командам. Этот тариф ориентирован на бизнес, которому нужны:
                </p>

                <ul class="pricing__features">
                    <li class="pricing__feature">
                        <span class="pricing__check"><img src="{{asset('assets/public/tick3.png')}}" alt=""></span>
                        Более широкие возможности управления
                    </li>
                    <li class="pricing__feature">
                        <span class="pricing__check"><img src="{{asset('assets/public/tick3.png')}}" alt=""></span>
                        Возможности дальнейшего роста
                    </li>
                    <li class="pricing__feature">
                        <span class="pricing__check"><img src="{{asset('assets/public/tick3.png')}}" alt=""></span>
                        Простая цена
                    </li>
                    <li class="pricing__feature">
                        <span class="pricing__check"><img src="{{asset('assets/public/tick3.png')}}" alt=""></span>
                        Прозрачная модель
                    </li>
                </ul>

                <button class="pricing__btn pricing__btn--primary">Выбрать</button>
            </div>
        </div>
    </div>
</section>





<!-- Download Section -->
<section class="hero-cta-download">
    <div class="hero-cta-download__container">
        <div class="hero-cta-download__image">
            <img  src="{{asset('assets/public/secondphone.jpg')}}" alt="Esticly App" class="hero-cta-download__phone">
            <img class="hero-cta-download__logo" src="{{asset('assets/public/logotbn.png')}}" alt="">
        </div>

        <div class="hero-cta-download__content">
            <h2 class="hero-cta-download__title">
                Приложение Esticly доступно на iOS и Android.
            </h2>

            <p class="hero-cta-download__text">
                Это значит, что управлять записями, клиентами и доходом можно из любого места и в любое время. Мобильная CRM особенно важна для beauty-сферы, где мастера и владельцы салонов часто работают в динамичном режиме и не всегда находятся за компьютером.
            </p>

        </div>
    </div>

    <div class="hero-cta-download__bottom">
        <div class="hero-cta-download__bottom-container">
            <p class="hero-cta-download__bottom-text  change_to_black">Esticly делает процесс взаимодействия с клиентами более удобным, а бизнес — более организованным и предсказуемым.</p>
            <div class="hero-cta-download__apps">
                <img src="{{asset('assets/public/appstore.png')}}" alt="App Store" class="hero-cta-download__app-badge">
                <img src="{{asset('assets/public/playstore.png')}}" alt="Google Play" class="hero-cta-download__app-badge">
            </div>
        </div>
    </div>

</section>


<!-- Reviews Section -->
<section class="reviews">
    <div class="reviews__container">
        <h2 class="reviews__title">Отзывы</h2>

        <div class="reviews__grid">
            <div class="reviews__card">
                <div class="reviews__quote"><img src="{{asset('assets/public/vector.png')}}" alt=""></div>
                <p class="reviews__text">
                    Действительно полезный инструмент для роста и удобства 💅✨
                </p>
                <p class="reviews__description">
                    Очень удобно, что клиенты сами выбирают свободное время, а я вижу весь свой график в одном месте. Есть напоминания, благодаря которым стало намного меньше “пропусков” записей. Интерфейс понятный, разобралась буквально за один день.

                </p>
                <p class="reviews__description">
                    Отдельный плюс — это экономия времени и более организованный работ...
                </p>
                <a href="#" class="reviews__link">Читать полностью</a>

                <div class="reviews__author">
                    <img src="{{asset('assets/public/anastasia.png')}}" alt="Анастасия" class="reviews__avatar">
                    <div class="reviews__author-info">
                        <p class="reviews__author-name">Анастасия</p>
                        <p class="reviews__author-role">Мастер маникюра</p>
                    </div>
                </div>
            </div>

            <div class="reviews__card">
                <div class="reviews__quote"><img src="{{asset('assets/public/vector.png')}}" alt=""></div>
                <p class="reviews__text">
                    Однозначно рекомендуем для салонов, работать более системно и удобно!
                </p>
                <p class="reviews__description">
                    Как салон красоты, мы искали удобное решение для записи клиентов и управления расписанием мастеров — и это приложение полностью справдало ожидания.
                </p>
                <p class="reviews__description">
                    Теперь вся запись ведётся в одном месте: администратору стало намного проще работать, а клиенты могут самостоятельно выбрать удобное врем...
                </p>
                <a href="#" class="reviews__link">Читать полностью</a>

                <div class="reviews__author">
                    <img src="{{asset('assets/public/svetlana.png')}}" alt="Светлана" class="reviews__avatar">
                    <div class="reviews__author-info">
                        <p class="reviews__author-name">Светлана</p>
                        <p class="reviews__author-role">Владелица салона красоты</p>
                    </div>
                </div>
            </div>

            <div class="reviews__card">
                <div class="reviews__quote"><img src="{{asset('assets/public/vector.png')}}" alt=""></div>
                <p class="reviews__text">
                    Действительно полезный инструмент для роста и удобства 🚀✨
                </p>
                <p class="reviews__description">
                    Очень удобно, что клиенты сами выбирают свободное время, а я вижу весь свой график в одном месте. Есть напоминания, благодаря которым стало намного меньше "пропусков" записей. Интерфейс понятный, разобралась буквально за один день.
                </p>
                <p class="reviews__description">
                    Отдельный плюс — это экономия времени и более организованный работ...
                </p>
                <a href="#" class="reviews__link">Читать полностью</a>

                <div class="reviews__author">
                    <img src="{{asset('assets/public/anastasia2.png')}}" alt="Анастасия" class="reviews__avatar">
                    <div class="reviews__author-info">
                        <p class="reviews__author-name">Анастасия</p>
                        <p class="reviews__author-role">Мастер маникюра</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
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

<!-- CTA Text Section -->
<section class="cta-text">
    <div class="cta-text__container">
        <h2 class="cta-text__title">
            CRM для салона красоты — это не просто программа для записи клиентов, а полноценный инструмент для автоматизации бизнеса.
        </h2>

        <p class="cta-text__description">
            Такая система помогает мастерам и салонам управлять клиентской базой, фиксировать визиты, отправлять напоминания, анализировать доход и систематично выстраивать процессы.
        </p>

        <p class="cta-text__description">
            Такая система помогает мастерам и салонам управлять клиентской базой, фиксировать визиты, отправлять напоминания, анализировать доход и системно выстраивать процессы.
            Использование CRM для beauty-бизнеса помогает уменьшить количество ошибок, улучшить клиентский опыт и увеличить прибыль. Когда все данные собраны в одном месте, владельцу проще контролировать работу, видеть слабые зоны и принимать решения на основе аналитики. Esticly создан именно для таких задач и помогает перевести хаотичное управление в понятную систему.
        </p>

        <a href="#" class="cta-text__btn">Читать полностью</a>
    </div>
</section>




<!-- FAQ Section -->
<section class="faq">
    <div class="faq__container">
        <h2 class="faq__title">FAQ</h2>

        <div class="faq__list">
            <div class="faq__item">
                <button class="faq__question">
                    <span>Что такое CRM для салона красоты?</span>
                    <span class="faq__icon">
             <img src="{{asset('assets/public/vector_button.png')}}" alt="" width="30px" object-fit="cover">
          </span>
                </button>
                <div class="faq__answer">
                    <p>Это система управления клиентами, записями, напоминаниями и доходом, которая помогает автоматизировать ежедневную работу и улучшать сервис.</p>
                </div>
            </div>

            <div class="faq__item">
                <button class="faq__question">
                    <span>Зачем CRM нужна частному мастеру?</span>
                    <span class="faq__icon">
             <img src="{{asset('assets/public/vector_button.png')}}" alt="" width="30px" object-fit="cover">
          </span>
                </button>
                <div class="faq__answer">
                    <p>CRM помогает мастеру организовать работу, не забывать о клиентах, получать напоминания о записях и видеть свой доход в одном месте. Это особенно полезно для тех, кто работает одной и управляет своим временем самостоятельно.</p>
                </div>
            </div>

            <div class="faq__item">
                <button class="faq__question">
                    <span>Сложно ли пользоваться Esticly?</span>
                    <span class="faq__icon">
            <img src="{{asset('assets/public/vector_button.png')}}" alt="" width="30px" object-fit="cover">
          </span>
                </button>
                <div class="faq__answer">
                    <p>Нет, Esticly имеет интуитивный интерфейс. Большинство пользователей разбираются в приложении за 1-2 дня. Есть также поддержка и документация, которые помогут вам начать.</p>
                </div>
            </div>

            <div class="faq__item">
                <button class="faq__question">
                    <span>Можно ли начать бесплатно?</span>
                    <span class="faq__icon">
             <img src="{{asset('assets/public/vector_button.png')}}" alt="" width="30px" object-fit="cover">
          </span>
                </button>
                <div class="faq__answer">
                    <p>Да, вы можете начать с бесплатного пробного периода на 7 дней. Это даст вам возможность протестировать все функции и решить, подходит ли вам Esticly, прежде чем оформлять платную подписку.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="hero-cta">
    <div class="hero-cta__container">
        <div class="hero-cta__content">
            <h2 class="hero-cta__title">
                Начните наводить порядок в бизнесе уже сегодня.
            </h2>

            <p class="hero-cta__text">
                Скачайте Esticly и попробуйте современную CRM для салонов красоты и частных мастеров, которая помогает автоматизировать запись клиентов, улучшить сервис и увеличить доход. Бесплатный старт позволяет быстро протестировать возможности приложения и перейти от хаоса к системе.
            </p>

            <div class="hero-cta__app-buttons">
                <a href="#" class="hero-cta__app-link">
                    <img src="{{asset('assets/public/appstore.png')}}" alt="App Store">
                </a>
                <a href="#" class="hero-cta__app-link">
                    <img src="{{asset('assets/public/playstore.png')}}" alt="Google Play">
                </a>
            </div>
        </div>

        <div class="hero-cta__image">
{{--            <img class="logo_btn" src="{{asset('assets/public/logotbn.png')}}" alt="">--}}
            <img src="{{asset('assets/public/Frame 11.png')}}" alt="Esticly App" class="hero-cta__phone">
        </div>
    </div>
</section>







<!-- Footer CTA Section -->
<section class="footer-cta">
    <div class="footer-cta__container">
        <div class="footer-cta__content">
            <p class="footer-cta__text">Esticly делает процесс взаимодействия с клиентами более удобным, а бизнес — более организованным и предсказуемым.</p>
            <div class="footer-cta__buttons">
                <a href="#" class="footer-cta__app-link">
                    <img src="{{asset('assets/public/appstore.png')}}" alt="App Store">
                </a>
                <a href="#" class="footer-cta__app-link">
                    <img src="{{asset('assets/public/playstore.png')}}" alt="Google Play">
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer">
    <div class="footer__container">
        <div class="footer__main">
            <div class="footer__column footer__column--left">
                <div class="footer__logo">
                    <img src="{{asset('assets/public/logo.png')}}" alt="Esticly" class="footer__logo-img">
                </div>
                <p class="footer__description">Попробуйте современную CRM для салонов красоты и частных мастеров, которая помогает автоматизировать запись клиентов, улучшить сервис и увеличить доход.</p>
                <div class="footer__languages">
                    <a href="#" class="footer__lang">RU</a>
                    <a href="#" class="footer__lang">EN</a>
                    <a href="#" class="footer__lang">PL</a>
                    <a href="#" class="footer__lang">IT</a>
                    <a href="#" class="footer__lang">FR</a>
                </div>
                <p class="footer__copyright">2026 EVA. Все права защищены</p>
            </div>

            <div class="footer__column">
                <h3 class="footer__title">Клиентам</h3>
                <ul class="footer__menu">
                    <li><a href="#" class="footer__link">Возможности</a></li>
                    <li><a href="#" class="footer__link">Для мастеров</a></li>
                    <li><a href="#" class="footer__link">Для салонов</a></li>
                    <li><a href="#" class="footer__link">Цены</a></li>
                    <li><a href="#" class="footer__link">FAQ</a></li>
                </ul>
            </div>

            <div class="footer__column footer__column--right">
                <h3 class="footer__title">Контакты</h3>
                <div class="footer__contact">
                    <p class="footer__contact-label"> <img src="{{asset('assets/public/phone.png')}}" alt="Location" class="footer__contact-icon">+48 577 000 000</p>
                    <p class="footer__contact-time">ПН-СБ: 09:00 - 20:00, ВС: выходной</p>
                </div>
                <div class="footer__contact">
                    <p class="footer__contact-label">
                        <img src="{{asset('assets/public/emial.png')}}" alt="Email" class="footer__contact-icon">
                        esticly@gmail.com
                    </p>
                    <a href="mailto:esticly@gmail.com" class="footer__write-btn">Написать</a>
                </div>
                <div class="footer__contact">
                    <p class="footer__contact-label">
                        <img src="{{asset('assets/public/location.png')}}" alt="Location" class="footer__contact-icon">
                        М.Киев
                    </p>
                    <p class="footer__contact-address">ул. Соборная 29, кабинет 32</p>
                </div>
            </div>
        </div>

        <div class="footer__bottom">
            <a href="#" class="footer__bottom-link">Политика конфиденциальности</a>
            <a href="#" class="footer__bottom-link">Условия использования</a>
        </div>
    </div>
</footer>

<div id="app"></div>
<script type="module" src="{{asset('assets/src/main.js')}}"></script>
</body>
</html>
