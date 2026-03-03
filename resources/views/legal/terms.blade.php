@php
/** @var string $lang */
$updated = '2026-02-19';
$email = 'artem.yablochnyi@gmail.com';

$allLangs = ['uk', 'pl', 'en', 'it', 'fr', 'pt', 'de', 'es', 'cs'];

$titles = [
  'uk' => 'Умови користування',
  'pl' => 'Regulamin (Warunki korzystania)',
  'en' => 'Terms of Service',
  'it' => 'Termini di servizio',
  'fr' => 'Conditions d\'utilisation',
  'pt' => 'Termos de Serviço',
  'de' => 'Nutzungsbedingungen',
  'es' => 'Términos del servicio',
  'cs' => 'Podmínky používání',
];

$privacyLabel = [
  'uk' => 'Конфіденційність',
  'pl' => 'Prywatność',
  'en' => 'Privacy',
  'it' => 'Privacy',
  'fr' => 'Confidentialité',
  'pt' => 'Privacidade',
  'de' => 'Datenschutz',
  'es' => 'Privacidad',
  'cs' => 'Soukromí',
];

$metaUpdated = [
  'uk' => 'Оновлено',
  'pl' => 'Aktualizacja',
  'en' => 'Updated',
  'it' => 'Aggiornato',
  'fr' => 'Mise à jour',
  'pt' => 'Atualizado',
  'de' => 'Aktualisiert',
  'es' => 'Actualizado',
  'cs' => 'Aktualizováno',
];

$content = [
  'uk' => [
    'h1' => '1. Статус сервісу',
    'p1' => 'Сервіс перебуває в активній розробці та наразі надається безкоштовно оператором-фізичною особою (станом на дату оновлення без зареєстрованої компанії).',
    'h2' => '2. Акаунт і доступ',
    'l2' => [
      'Автентифікація виконується через OTP-код на номер телефону.',
      'Користувач відповідає за безпеку доступу до телефону та акаунта.',
      'Користувач відповідає за законність і коректність даних, які вносить у систему.',
    ],
    'h3' => '3. Допустиме використання',
    'l3' => [
      'Заборонені спроби обходу захисту, зловживання, спам і незаконне використання.',
      'Доступ може бути обмежений або заблокований у разі порушень, зловживань чи ризиків безпеки.',
    ],
    'h4' => '4. Дані та контент користувача',
    'p4' => 'Дані і контент, які ви завантажуєте (включно з даними клієнтів), залишаються під контролем вашої організації. Оператор обробляє їх лише для забезпечення функціоналу застосунку.',
    'h5' => '5. Доступність і відповідальність',
    'p5' => 'Застосунок надається за принципом «як є». Можливі помилки, технічні перерви та зміни функціоналу. Безперервна доступність і відсутність дефектів не гарантуються.',
    'h6' => '6. Оновлення умов і контакт',
    'p6' => 'Ці Умови можуть оновлюватися в процесі розвитку продукту. Актуальна версія публікується на цій сторінці. Контакт: :email.',
  ],
  'pl' => [
    'h1' => '1. Status usługi',
    'p1' => 'Usługa jest rozwijana i udostępniana bezpłatnie przez operatora będącego osobą fizyczną (bez zarejestrowanej spółki na dzień aktualizacji).',
    'h2' => '2. Konto i dostęp',
    'l2' => [
      'Logowanie odbywa się przez OTP na numer telefonu.',
      'Użytkownik odpowiada za bezpieczeństwo dostępu do telefonu i danych konta.',
      'Użytkownik odpowiada za zgodność i legalność danych wprowadzanych do systemu.',
    ],
    'h3' => '3. Dozwolone korzystanie',
    'l3' => [
      'Zabronione są próby obejścia zabezpieczeń, nadużycia, spam i działania naruszające prawo.',
      'Operator może ograniczyć lub zablokować dostęp w przypadku naruszeń, nadużyć lub zagrożeń bezpieczeństwa.',
    ],
    'h4' => '4. Dane i treści użytkownika',
    'p4' => 'Dane i treści dodawane przez użytkownika (w tym dane klientów) pozostają pod kontrolą użytkownika/organizacji. Operator przetwarza je wyłącznie w celu działania funkcji aplikacji.',
    'h5' => '5. Dostępność i odpowiedzialność',
    'p5' => 'Aplikacja jest dostarczana „as is”. Mogą występować błędy, przerwy techniczne i zmiany funkcji. Operator nie gwarantuje nieprzerwanej dostępności ani braku błędów.',
    'h6' => '6. Zmiany regulaminu i kontakt',
    'p6' => 'Regulamin może być aktualizowany wraz z rozwojem produktu. Aktualna wersja jest publikowana na tej stronie. Kontakt: :email.',
  ],
  'en' => [
    'h1' => '1. Service status',
    'p1' => 'The service is under active development and currently provided free of charge by an individual operator (no registered company as of the update date).',
    'h2' => '2. Account and access',
    'l2' => [
      'Authentication is performed via OTP sent to your phone number.',
      'You are responsible for securing your phone access and account credentials.',
      'You are responsible for the legality and correctness of data entered into the system.',
    ],
    'h3' => '3. Acceptable use',
    'l3' => [
      'No abuse, no attempts to bypass security controls, no spam, and no unlawful use.',
      'Access may be limited or blocked in case of violations, abuse, or security threats.',
    ],
    'h4' => '4. User data and content',
    'p4' => 'Data and content you upload (including client data) remain under your organization’s control. The operator processes such data only to provide application functionality.',
    'h5' => '5. Availability and liability',
    'p5' => 'The app is provided on an "as is" basis. Bugs, temporary outages, and feature changes may occur. Continuous availability and zero defects are not guaranteed.',
    'h6' => '6. Terms updates and contact',
    'p6' => 'These Terms may be updated as the product evolves. The current version is published on this page. Contact: :email.',
  ],
  'it' => [
    'h1' => '1. Stato del servizio',
    'p1' => 'Il servizio è in sviluppo attivo ed è attualmente fornito gratuitamente da un operatore persona fisica (senza società registrata alla data di aggiornamento).',
    'h2' => '2. Account e accesso',
    'l2' => [
      'L\'autenticazione avviene tramite OTP inviato al numero di telefono.',
      'L\'utente è responsabile della sicurezza dell\'accesso al telefono e all\'account.',
      'L\'utente è responsabile della liceità e correttezza dei dati inseriti nel sistema.',
    ],
    'h3' => '3. Uso consentito',
    'l3' => [
      'Sono vietati abuso del servizio, tentativi di aggirare la sicurezza, spam o uso illecito.',
      'L\'accesso può essere limitato o bloccato in caso di violazioni, abusi o rischi di sicurezza.',
    ],
    'h4' => '4. Dati e contenuti dell\'utente',
    'p4' => 'I dati e i contenuti caricati dall\'utente (inclusi i dati clienti) restano sotto il controllo dell\'organizzazione. L\'operatore li tratta solo per fornire le funzionalità dell\'applicazione.',
    'h5' => '5. Disponibilità e responsabilità',
    'p5' => 'L\'app è fornita "così com\'è". Possono verificarsi bug, interruzioni temporanee e modifiche funzionali. Non è garantita disponibilità continua né assenza di difetti.',
    'h6' => '6. Aggiornamenti dei Termini e contatto',
    'p6' => 'I presenti Termini possono essere aggiornati durante l\'evoluzione del prodotto. La versione corrente è pubblicata in questa pagina. Contatto: :email.',
  ],
  'fr' => [
    'h1' => '1. Statut du service',
    'p1' => 'Le service est en développement actif et est actuellement fourni gratuitement par un opérateur personne physique (sans société enregistrée à la date de mise à jour).',
    'h2' => '2. Compte et accès',
    'l2' => [
      'L\'authentification se fait via un code OTP envoyé au numéro de téléphone.',
      'L\'utilisateur est responsable de la sécurité d\'accès à son téléphone et à son compte.',
      'L\'utilisateur est responsable de la légalité et de l\'exactitude des données saisies.',
    ],
    'h3' => '3. Utilisation autorisée',
    'l3' => [
      'Toute tentative d\'abus, de contournement de sécurité, de spam ou d\'usage illégal est interdite.',
      'L\'accès peut être limité ou bloqué en cas de violation, d\'abus ou de risque de sécurité.',
    ],
    'h4' => '4. Données et contenu utilisateur',
    'p4' => 'Les données et contenus chargés par l\'utilisateur (y compris les données clients) restent sous le contrôle de l\'organisation. L\'opérateur les traite uniquement pour fournir les fonctionnalités de l\'application.',
    'h5' => '5. Disponibilité et responsabilité',
    'p5' => 'L\'application est fournie "en l\'état". Des bugs, interruptions temporaires et évolutions fonctionnelles peuvent survenir. La disponibilité continue et l\'absence totale de défauts ne sont pas garanties.',
    'h6' => '6. Mise à jour des Conditions et contact',
    'p6' => 'Ces Conditions peuvent être mises à jour avec l\'évolution du produit. La version en vigueur est publiée sur cette page. Contact : :email.',
  ],
  'pt' => [
    'h1' => '1. Estado do serviço',
    'p1' => 'O serviço está em desenvolvimento ativo e atualmente é oferecido gratuitamente por um operador pessoa física (sem empresa registrada na data desta atualização).',
    'h2' => '2. Conta e acesso',
    'l2' => [
      'A autenticação é realizada por OTP enviado ao número de telefone.',
      'O usuário é responsável pela segurança do acesso ao telefone e à conta.',
      'O usuário é responsável pela legalidade e correção dos dados inseridos no sistema.',
    ],
    'h3' => '3. Uso permitido',
    'l3' => [
      'São proibidos abuso do serviço, tentativa de burlar segurança, spam e uso ilegal.',
      'O acesso pode ser limitado ou bloqueado em caso de violação, abuso ou risco de segurança.',
    ],
    'h4' => '4. Dados e conteúdo do usuário',
    'p4' => 'Dados e conteúdo enviados pelo usuário (incluindo dados de clientes) permanecem sob controle da organização. O operador os processa apenas para fornecer as funcionalidades do aplicativo.',
    'h5' => '5. Disponibilidade e responsabilidade',
    'p5' => 'O aplicativo é fornecido no estado "como está". Podem ocorrer bugs, indisponibilidades temporárias e mudanças de funcionalidades. Não há garantia de disponibilidade contínua nem ausência total de defeitos.',
    'h6' => '6. Atualizações dos Termos e contato',
    'p6' => 'Estes Termos podem ser atualizados conforme o produto evolui. A versão atual é publicada nesta página. Contato: :email.',
  ],
  'de' => [
    'h1' => '1. Status des Dienstes',
    'p1' => 'Der Dienst befindet sich in aktiver Entwicklung und wird derzeit kostenlos von einem Betreiber als natürliche Person bereitgestellt (ohne eingetragenes Unternehmen zum Aktualisierungsdatum).',
    'h2' => '2. Konto und Zugriff',
    'l2' => [
      'Die Anmeldung erfolgt per OTP-Code an die Telefonnummer.',
      'Der Nutzer ist für die Sicherheit des Zugriffs auf Telefon und Konto verantwortlich.',
      'Der Nutzer ist für Rechtmäßigkeit und Richtigkeit der eingegebenen Daten verantwortlich.',
    ],
    'h3' => '3. Zulässige Nutzung',
    'l3' => [
      'Missbrauch, Umgehung von Sicherheitsmechanismen, Spam und rechtswidrige Nutzung sind untersagt.',
      'Der Zugriff kann bei Verstößen, Missbrauch oder Sicherheitsrisiken eingeschränkt oder gesperrt werden.',
    ],
    'h4' => '4. Nutzerdaten und Inhalte',
    'p4' => 'Vom Nutzer hochgeladene Daten und Inhalte (einschließlich Kundendaten) bleiben unter Kontrolle der Organisation. Der Betreiber verarbeitet diese nur zur Bereitstellung der App-Funktionen.',
    'h5' => '5. Verfügbarkeit und Haftung',
    'p5' => 'Die App wird "wie besehen" bereitgestellt. Fehler, temporäre Ausfälle und Funktionsänderungen können auftreten. Eine unterbrechungsfreie Verfügbarkeit und vollständige Fehlerfreiheit werden nicht garantiert.',
    'h6' => '6. Aktualisierung der Bedingungen und Kontakt',
    'p6' => 'Diese Bedingungen können mit der Weiterentwicklung des Produkts aktualisiert werden. Die aktuelle Version wird auf dieser Seite veröffentlicht. Kontakt: :email.',
  ],
  'es' => [
    'h1' => '1. Estado del servicio',
    'p1' => 'El servicio está en desarrollo activo y actualmente se ofrece de forma gratuita por un operador persona física (sin empresa registrada en la fecha de actualización).',
    'h2' => '2. Cuenta y acceso',
    'l2' => [
      'La autenticación se realiza mediante OTP enviado al número de teléfono.',
      'El usuario es responsable de la seguridad del acceso al teléfono y a la cuenta.',
      'El usuario es responsable de la legalidad y exactitud de los datos introducidos en el sistema.',
    ],
    'h3' => '3. Uso permitido',
    'l3' => [
      'Se prohíbe el abuso del servicio, los intentos de eludir la seguridad, el spam y el uso ilegal.',
      'El acceso puede limitarse o bloquearse en caso de infracciones, abuso o riesgos de seguridad.',
    ],
    'h4' => '4. Datos y contenido del usuario',
    'p4' => 'Los datos y contenidos subidos por el usuario (incluidos los datos de clientes) permanecen bajo control de la organización. El operador los procesa solo para proporcionar funcionalidades de la aplicación.',
    'h5' => '5. Disponibilidad y responsabilidad',
    'p5' => 'La aplicación se ofrece "tal cual". Pueden producirse errores, interrupciones temporales y cambios funcionales. No se garantiza disponibilidad continua ni ausencia total de defectos.',
    'h6' => '6. Actualizaciones de los Términos y contacto',
    'p6' => 'Estos Términos pueden actualizarse a medida que evoluciona el producto. La versión vigente se publica en esta página. Contacto: :email.',
  ],
  'cs' => [
    'h1' => '1. Stav služby',
    'p1' => 'Služba je v aktivním vývoji a je nyní poskytována zdarma provozovatelem jako fyzickou osobou (k datu aktualizace bez registrované společnosti).',
    'h2' => '2. Účet a přístup',
    'l2' => [
      'Ověření probíhá pomocí OTP kódu zaslaného na telefonní číslo.',
      'Uživatel odpovídá za zabezpečení přístupu k telefonu a účtu.',
      'Uživatel odpovídá za zákonnost a správnost údajů zadaných do systému.',
    ],
    'h3' => '3. Přípustné používání',
    'l3' => [
      'Je zakázáno zneužití služby, obcházení zabezpečení, spam a nezákonné použití.',
      'Přístup může být omezen nebo zablokován při porušení pravidel, zneužití nebo bezpečnostním riziku.',
    ],
    'h4' => '4. Uživatelská data a obsah',
    'p4' => 'Data a obsah nahraný uživatelem (včetně klientských dat) zůstává pod kontrolou organizace. Provozovatel je zpracovává pouze pro zajištění funkcí aplikace.',
    'h5' => '5. Dostupnost a odpovědnost',
    'p5' => 'Aplikace je poskytována "tak jak je". Mohou se vyskytnout chyby, dočasné výpadky a změny funkcí. Nepřetržitá dostupnost ani úplná bezchybnost nejsou zaručeny.',
    'h6' => '6. Aktualizace podmínek a kontakt',
    'p6' => 'Tyto Podmínky mohou být aktualizovány s vývojem produktu. Aktuální verze je zveřejněna na této stránce. Kontakt: :email.',
  ],
];

$copy = $content[$lang] ?? $content['en'];
$title = $titles[$lang] ?? $titles['en'];
$siteLocales = config('site_locales.supported', []);
$xDefaultLocale = config('site_locales.x_default', config('site_locales.default', 'pl'));
$canonicalUrl = route('legal.terms.localized', ['locale' => $lang]);
@endphp
<!doctype html>
<html lang="{{ $lang }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $title }}</title>
  <meta name="description" content="{{ $title }} - Esticly">
  <link rel="canonical" href="{{ $canonicalUrl }}">
  @foreach ($siteLocales as $code => $meta)
    <link rel="alternate" hreflang="{{ $meta['hreflang'] ?? $code }}" href="{{ route('legal.terms.localized', ['locale' => $code]) }}">
  @endforeach
  <link rel="alternate" hreflang="x-default" href="{{ route('legal.terms.localized', ['locale' => $xDefaultLocale]) }}">
  <style>
    :root { --bg:#0b1220; --card:#ffffff; --border:#e5e7eb; }
    body{ margin:0; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial; background:linear-gradient(180deg,#0b1220 0%,#0f172a 40%,#0b1220 100%); color:#111827; }
    .wrap{ max-width:920px; margin:0 auto; padding:22px 14px 40px; }
    .card{ background:rgba(255,255,255,.94); border:1px solid rgba(255,255,255,.18); border-radius:18px; padding:18px; }
    h1{ margin:0 0 6px; font-size:22px; }
    .meta{ color:rgba(17,24,39,.65); font-weight:800; font-size:13px; }
    .langs{ margin:12px 0 0; display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
    .langs a{ display:inline-block; padding:8px 10px; border-radius:999px; border:1px solid var(--border); background:#fff; color:#111827; text-decoration:none; font-weight:900; font-size:13px; }
    .langs a.active{ border-color:#111827; }
    h2{ margin:18px 0 8px; font-size:16px; }
    p,li{ color:#111827; line-height:1.45; font-weight:650; }
    ul{ margin:8px 0 0 18px; padding:0; }
    .muted{ color:rgba(17,24,39,.60); font-weight:800; }
    a{ color:#111827; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>{{ $title }}</h1>
      <div class="meta">{{ $metaUpdated[$lang] ?? 'Updated' }}: {{ $updated }}</div>

      <div class="langs">
        @foreach($allLangs as $code)
          <a class="{{ $lang===$code?'active':'' }}" href="/terms?lang={{ $code }}">{{ strtoupper($code) }}</a>
        @endforeach
        <a class="muted" href="/privacy?lang={{ $lang }}">{{ $privacyLabel[$lang] ?? 'Privacy' }}</a>
      </div>

      <h2>{{ $copy['h1'] }}</h2>
      <p>{{ $copy['p1'] }}</p>

      <h2>{{ $copy['h2'] }}</h2>
      <ul>
        @foreach($copy['l2'] as $row)
          <li>{{ $row }}</li>
        @endforeach
      </ul>

      <h2>{{ $copy['h3'] }}</h2>
      <ul>
        @foreach($copy['l3'] as $row)
          <li>{{ $row }}</li>
        @endforeach
      </ul>

      <h2>{{ $copy['h4'] }}</h2>
      <p>{{ $copy['p4'] }}</p>

      <h2>{{ $copy['h5'] }}</h2>
      <p>{{ $copy['p5'] }}</p>

      <h2>{{ $copy['h6'] }}</h2>
      <p>{!! str_replace(':email', '<a href="mailto:'.$email.'">'.$email.'</a>', e($copy['p6'])) !!}</p>
    </div>
  </div>
</body>
</html>
