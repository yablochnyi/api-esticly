<?php

return [
    'seo' => [
        'title' => 'Esticly - CRM and online booking for beauty professionals and salons',
        'description' => 'Esticly helps beauty professionals and salons manage bookings, clients, reminders, marketing, analytics and team workflows in one place.',
    ],
    'locale' => [
        'switcher' => 'Language',
    ],
    'nav' => [
        'product' => 'Product',
        'mobile' => 'Mobile',
        'services' => 'Features',
        'ops' => 'How it works',
        'pricing' => 'Pricing',
        'login' => 'Log in',
        'try' => 'Try now',
    ],
    'hero' => [
        'eyebrow' => 'CRM + Online Booking + Mobile App',
        'title' => 'Esticly - operational hub for solo professionals and salons',
        'lead' => 'One product for daily operations: calendar, client CRM, online booking, reminders, marketing, analytics, team workflows and control without chaos in messengers and spreadsheets.',
        'cta_primary' => 'Start demo',
        'cta_secondary' => 'View mobile UX',
        'store_note' => 'The app is not in stores yet. Leave contacts and we will notify you first.',
        'store_aria' => 'App downloads',
        'app_store_aria' => 'App Store is coming soon',
        'play_store_aria' => 'Google Play is coming soon',
        'app_store_small' => 'Coming soon on',
        'app_store_label' => 'App Store',
        'play_store_small' => 'Coming soon on',
        'play_store_label' => 'Google Play',
    ],
    'hero_points' => [
        ['value' => '24/7', 'label' => 'online booking for clients'],
        ['value' => '1 place', 'label' => 'calendar, clients, reminders'],
        ['value' => 'Mobile', 'label' => 'key actions from phone'],
    ],
    'hero_metrics' => [
        ['title' => 'Calendar', 'label' => 'day / week / list'],
        ['title' => 'CRM', 'label' => 'history, notes, photos'],
        ['title' => 'Marketing', 'label' => 'automations and promos'],
        ['title' => 'Analytics', 'label' => 'revenue, visits, avg check'],
    ],
    'hero_chips' => ['Calendar', 'Client card', 'Reminders', 'Promo codes', 'Analytics', 'DSAR / GDPR'],

    'launch' => [
        'badge' => 'COMING SOON',
        'title' => 'Esticly mobile app will be available in stores soon',
        'subtitle' => 'Leave your phone and email. We will notify you when App Store and Google Play pages go live.',
    ],
    'waitlist' => [
        'email_label' => 'Work email',
        'email_placeholder' => 'name@company.com',
        'phone_label' => 'Phone number',
        'phone_placeholder' => '+48 577 000 000',
        'submit' => 'Notify me on launch',
        'success' => 'Done. We saved your contacts and will notify you once the apps are live in stores.',
    ],

    'features' => [
        'title' => 'Features that actually cover daily salon operations',
        'subtitle' => 'Esticly is not only about bookings. It is one workflow: booking -> visit -> reminders -> repeat sales -> analytics -> access control.',
        'items' => [
            ['icon' => '📅', 'title' => 'Calendar and bookings', 'description' => 'Day/week view, manual appointments, statuses, service buffers, combo services and conflict checks.'],
            ['icon' => '👤', 'title' => 'Client CRM', 'description' => 'Client card, history, notes, blacklist/waitlist, photos and quick actions.'],
            ['icon' => '🌐', 'title' => 'Online booking', 'description' => 'Public master/salon page, available slots, promo codes, social links, QR and share links.'],
            ['icon' => '🔔', 'title' => 'Reminders', 'description' => 'Push/Telegram/Email flows to reduce no-shows and notify staff about booking changes.'],
            ['icon' => '📣', 'title' => 'Marketing', 'description' => 'Post-visit automations, promo codes, reactivation campaigns and short links.'],
            ['icon' => '📊', 'title' => 'Analytics', 'description' => 'Revenue, visits, average check, cancellations, utilization by day and hour.'],
            ['icon' => '👥', 'title' => 'Team / Staff', 'description' => 'Staff schedules, permissions, visibility restrictions and role-based operations.'],
            ['icon' => '🔐', 'title' => 'Security and compliance', 'description' => 'Audit log, DSAR operations, anonymization, encrypted PII and controlled access.'],
            ['icon' => '☁️', 'title' => 'Backup and restore', 'description' => 'Automated DB/project backups, S3 storage, retention and restore checks with separate logs.'],
        ],
    ],

    'segments' => [
        'title' => 'Who gets the most value',
        'subtitle' => 'One product, different scenarios: solo specialist, team salon and growing operation-focused business.',
        'items' => [
            ['title' => 'Solo specialist', 'description' => 'Fast calendar, clients, reminders, public booking page and basic analytics.'],
            ['title' => 'Salon with team', 'description' => 'Staff, schedules, services, permissions and booking control in one place.'],
            ['title' => 'Operationally mature business', 'description' => 'GDPR/DSAR, audit log, backups, restore checks and deeper analytics.'],
        ],
        'preview_title' => 'Calendar / Day',
        'preview_description' => 'Main mobile screen for daily operations: quick slots, visit statuses and full day control.',
        'gallery_items' => [
            ['image' => 'img/client.png', 'title' => 'Client card', 'description' => 'History, revenue, notes, export/delete actions and quick links.'],
            ['image' => 'img/analytic.png', 'title' => 'Analytics / Dashboard', 'description' => 'Revenue, visits, average check, cancellations and period trends.'],
        ],
    ],

    'ops' => [
        'title' => 'What salon owners get in day-to-day operations',
        'subtitle' => 'Esticly helps not only to manage bookings, but also to control client flow, team operations and repeat sales.',
        'owner_tagline' => 'Owner value',
        'owner_items' => [
            'Fewer missed visits with reminders and team notifications.',
            'Faster admin workflow: create, move and update bookings in one place.',
            'Visibility into team actions: who changed what and when.',
            'Data request handling: client export and anonymization when needed.',
            'Safer operations with backups and restore checks.',
            'A base for scaling with roles, analytics and automations.',
        ],
        'cycle_tagline' => 'Typical salon flow in Esticly',
        'cycle_items' => [
            ['step' => 'Step 1', 'title' => 'Client books a slot', 'description' => 'Via landing page / share link / QR, or manager creates appointment manually.'],
            ['step' => 'Step 2', 'title' => 'System sends reminders', 'description' => 'Client reminders and internal notifications about new or changed visits.'],
            ['step' => 'Step 3', 'title' => 'Post-visit follow-up', 'description' => 'Automation with promo or review request after visit.'],
            ['step' => 'Step 4', 'title' => 'Owner sees metrics', 'description' => 'Revenue, utilization, average check and cancellations in analytics.'],
        ],
    ],

    'future' => [
        'title' => 'Real salon results with Esticly',
        'subtitle' => 'Show future clients the impact your workflow gets in the first weeks after switching.',
        'items' => [
            ['title' => 'Salon case studies', 'description' => 'Before/after metrics on no-show reduction, repeat visits and admin speed.'],
            ['title' => 'One-day onboarding', 'description' => 'Import clients, configure services and schedule, launch booking page and run test booking.'],
            ['title' => 'Role model clarity', 'description' => 'Explain owner/staff rights and data visibility boundaries.'],
            ['title' => 'EU-ready security', 'description' => 'Short block about encrypted PII, audit log, DSAR and retention controls.'],
        ],
    ],

    'pricing' => [
        'title' => 'Pricing that scales with your salon',
        'subtitle' => 'Start with core features and move to advanced plan as team and load grow.',
        'period' => '/ month',
        'basic' => [
            'tag' => 'Start / Basic',
            'price' => '49 PLN',
            'description' => 'For solo specialists or small studios: clean booking and client operations.',
            'items' => [
                'Calendar and appointments',
                'Client CRM and notes',
                'Online booking page',
                'Reminders and basic analytics',
                'Mobile access to key actions',
            ],
        ],
        'pro' => [
            'tag' => 'Pro / Salon',
            'price' => '79 PLN',
            'description' => 'For team salons that need marketing, permissions and deeper operational control.',
            'items' => [
                'Everything in Basic',
                'Staff roles and permissions',
                'Marketing automations and promo codes',
                'Advanced analytics',
                'Operational tools for growth',
            ],
        ],
        'note' => 'Pick the plan for your current stage and upgrade as your team grows, without migrating to another system.',
    ],

    'cta' => [
        'title' => 'Ready to test Esticly on real appointments?',
        'description' => 'Start with demo or connect your first salon. Replace messenger chaos with a controlled booking workflow.',
        'register' => 'Create account',
        'login' => 'Log in',
    ],

    'footer' => 'Esticly CRM for specialists and salons: bookings, clients, reminders, marketing, analytics and mobile operations in one place.',
];
