@php
/** @var string $lang */
$updated = '2026-02-19';
$email = 'artem.yablochnyi@gmail.com';

$allLangs = ['uk', 'pl', 'en', 'it', 'fr', 'pt', 'de', 'es', 'cs'];

$titles = [
  'uk' => 'Політика конфіденційності',
  'pl' => 'Polityka prywatności',
  'en' => 'Privacy Policy',
  'it' => 'Informativa sulla privacy',
  'fr' => 'Politique de confidentialité',
  'pt' => 'Política de Privacidade',
  'de' => 'Datenschutzerklärung',
  'es' => 'Política de privacidad',
  'cs' => 'Zásady ochrany osobních údajů',
];

$termsLabel = [
  'uk' => 'Умови',
  'pl' => 'Regulamin',
  'en' => 'Terms',
  'it' => 'Termini',
  'fr' => 'Conditions',
  'pt' => 'Termos',
  'de' => 'Bedingungen',
  'es' => 'Términos',
  'cs' => 'Podmínky',
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
    'h1' => '1. Контролер даних і статус проєкту',
    'p1' => 'Контролером даних є оператор застосунку — фізична особа (станом на дату оновлення без зареєстрованої компанії). Контакт: :email. На цьому етапі сервіс надається безкоштовно.',
    'h2' => '2. Категорії даних',
    'l2' => [
      'Дані акаунта: номер телефону (OTP-вхід), e-mail (якщо вказано).',
      'Дані організації/салону: назва, адреса, опис, графік, налаштування, файли та зображення.',
      'Дані клієнтів, які вносить салон: ім\'я, телефон, нотатки, історія візитів, згоди й підписи.',
      'Технічні дані: системні логи, токени пристроїв/push та дані для безпеки і діагностики.',
    ],
    'h3' => '3. Цілі обробки',
    'l3' => [
      'Надання функціоналу сервісу (календар, CRM, записи, нагадування).',
      'Безпека, запобігання зловживанням, стабільна робота сервісу.',
      'Обробка запитів користувача (наприклад, експорт або анонімізація даних).',
    ],
    'h4' => '4. Одержувачі даних',
    'p4' => 'Дані можуть оброблятися технічними та комунікаційними провайдерами (наприклад, хостинг, інфраструктура БД/черг, SMS/push/e-mail провайдери) лише в обсязі, необхідному для роботи функцій.',
    'h5' => '5. Зберігання і бекапи',
    'p5' => 'Продакшен-дані зберігаються стільки, скільки потрібно для роботи сервісу, безпеки та підтримки. Резервні копії та строки їх зберігання залежать від активної конфігурації середовища (deploy), тому точні строки можуть відрізнятися між середовищами.',
    'h6' => '6. Ваші права (GDPR/RODO)',
    'p6' => 'Ви можете подати запит на доступ, виправлення, обмеження, видалення/анонімізацію та переносимість даних. Запити щодо приватності: :email.',
  ],
  'pl' => [
    'h1' => '1. Administrator danych i status projektu',
    'p1' => 'Administratorem danych jest operator aplikacji — osoba fizyczna (bez zarejestrowanej spółki na dzień aktualizacji). Kontakt: :email. Usługa jest obecnie udostępniana bezpłatnie.',
    'h2' => '2. Zakres danych',
    'l2' => [
      'Dane konta: numer telefonu (logowanie OTP), e-mail (jeśli podany).',
      'Dane organizacji/salonu: nazwa, adres, opis, harmonogram, ustawienia, pliki i grafiki.',
      'Dane klientów wprowadzane przez salon: imię, telefon, notatki, historia wizyt, zgody i podpisy.',
      'Dane techniczne: logi systemowe, tokeny urządzeń/push, dane wymagane do bezpieczeństwa i diagnostyki.',
    ],
    'h3' => '3. Cele przetwarzania',
    'l3' => [
      'Działanie aplikacji (kalendarz, CRM, rezerwacje, powiadomienia).',
      'Bezpieczeństwo, wykrywanie nadużyć i utrzymanie stabilności.',
      'Obsługa żądań użytkownika (np. eksport lub anonimizacja danych).',
    ],
    'h4' => '4. Odbiorcy danych',
    'p4' => 'Dane mogą być przetwarzane przez dostawców infrastruktury technicznej i komunikacji wyłącznie w zakresie niezbędnym do działania funkcji.',
    'h5' => '5. Przechowywanie i backup',
    'p5' => 'Dane produkcyjne są przechowywane przez okres korzystania z usługi oraz przez czas wymagany do bezpieczeństwa i obsługi zgłoszeń. Kopie zapasowe i okres ich retencji zależą od aktualnej konfiguracji środowiska (deploy), dlatego konkretne czasy mogą się różnić między środowiskami.',
    'h6' => '6. Twoje prawa (GDPR/RODO)',
    'p6' => 'Masz prawo do dostępu, sprostowania, ograniczenia, usunięcia/anonimizacji oraz przeniesienia danych. Wnioski privacy/RODO: :email.',
  ],
  'en' => [
    'h1' => '1. Data controller and project status',
    'p1' => 'The data controller is the app operator — an individual person (no registered company as of the update date). Contact: :email. The service is currently provided free of charge.',
    'h2' => '2. Data categories',
    'l2' => [
      'Account data: phone number (OTP login), email (if provided).',
      'Organization/salon data: name, address, description, schedule, settings, files and images.',
      'Client data entered by salons: name, phone, notes, visit history, agreements and signatures.',
      'Technical data: system logs, device/push tokens, and data required for security and diagnostics.',
    ],
    'h3' => '3. Processing purposes',
    'l3' => [
      'Providing app functionality (calendar, CRM, bookings, notifications).',
      'Security, abuse prevention, and service reliability.',
      'Handling user requests (for example data export or anonymization).',
    ],
    'h4' => '4. Data recipients',
    'p4' => 'Data may be processed by infrastructure and communication providers only to the extent required to deliver features.',
    'h5' => '5. Retention and backups',
    'p5' => 'Production data is stored for as long as needed to provide the service and to maintain security/support operations. Backup copies and their retention periods depend on the active deployment configuration, so exact durations may differ between environments.',
    'h6' => '6. Your rights (GDPR)',
    'p6' => 'You can request access, rectification, restriction, deletion/anonymization, and data portability. Privacy requests: :email.',
  ],
  'it' => [
    'h1' => '1. Titolare del trattamento e stato del progetto',
    'p1' => 'Il titolare del trattamento è l\'operatore dell\'app, una persona fisica (senza società registrata alla data di aggiornamento). Contatto: :email. Il servizio è attualmente gratuito.',
    'h2' => '2. Categorie di dati',
    'l2' => [
      'Dati account: numero di telefono (accesso OTP), e-mail (se fornita).',
      'Dati organizzazione/salone: nome, indirizzo, descrizione, orario, impostazioni, file e immagini.',
      'Dati clienti inseriti dal salone: nome, telefono, note, cronologia visite, consensi e firme.',
      'Dati tecnici: log di sistema, token push/dispositivo e dati necessari per sicurezza e diagnostica.',
    ],
    'h3' => '3. Finalità del trattamento',
    'l3' => [
      'Fornitura delle funzionalità dell\'app (calendario, CRM, prenotazioni, notifiche).',
      'Sicurezza, prevenzione abusi e affidabilità del servizio.',
      'Gestione delle richieste utente (ad es. esportazione o anonimizzazione dati).',
    ],
    'h4' => '4. Destinatari dei dati',
    'p4' => 'I dati possono essere trattati da fornitori tecnici e di comunicazione solo nella misura necessaria per fornire le funzionalità.',
    'h5' => '5. Conservazione e backup',
    'p5' => 'I dati di produzione sono conservati per il tempo necessario al servizio, alla sicurezza e al supporto. Backup e tempi di conservazione dipendono dalla configurazione attiva dell\'ambiente di deploy.',
    'h6' => '6. Diritti dell\'interessato (GDPR)',
    'p6' => 'Puoi richiedere accesso, rettifica, limitazione, cancellazione/anonimizzazione e portabilità dei dati. Richieste privacy: :email.',
  ],
  'fr' => [
    'h1' => '1. Responsable du traitement et statut du projet',
    'p1' => 'Le responsable du traitement est l\'opérateur de l\'application, une personne physique (sans société enregistrée à la date de mise à jour). Contact : :email. Le service est actuellement gratuit.',
    'h2' => '2. Catégories de données',
    'l2' => [
      'Données de compte : numéro de téléphone (connexion OTP), e-mail (si fourni).',
      'Données de l\'organisation/salon : nom, adresse, description, planning, paramètres, fichiers et images.',
      'Données clients saisies par le salon : nom, téléphone, notes, historique des visites, consentements et signatures.',
      'Données techniques : logs système, tokens push/appareil et données nécessaires à la sécurité/diagnostic.',
    ],
    'h3' => '3. Finalités du traitement',
    'l3' => [
      'Fournir les fonctionnalités de l\'application (calendrier, CRM, réservations, notifications).',
      'Sécurité, prévention des abus et fiabilité du service.',
      'Traitement des demandes utilisateurs (ex. export ou anonymisation des données).',
    ],
    'h4' => '4. Destinataires des données',
    'p4' => 'Les données peuvent être traitées par des prestataires techniques et de communication uniquement dans la mesure nécessaire au fonctionnement des fonctionnalités.',
    'h5' => '5. Conservation et sauvegardes',
    'p5' => 'Les données de production sont conservées aussi longtemps que nécessaire pour le service, la sécurité et le support. Les sauvegardes et leurs durées de conservation dépendent de la configuration active de déploiement.',
    'h6' => '6. Vos droits (RGPD)',
    'p6' => 'Vous pouvez demander l\'accès, la rectification, la limitation, la suppression/anonymisation et la portabilité des données. Demandes privacy : :email.',
  ],
  'pt' => [
    'h1' => '1. Controlador de dados e estado do projeto',
    'p1' => 'O controlador de dados é o operador da aplicação, uma pessoa física (sem empresa registrada na data desta atualização). Contato: :email. O serviço é atualmente gratuito.',
    'h2' => '2. Categorias de dados',
    'l2' => [
      'Dados da conta: número de telefone (login OTP), e-mail (se informado).',
      'Dados da organização/salão: nome, endereço, descrição, agenda, configurações, arquivos e imagens.',
      'Dados de clientes inseridos pelo salão: nome, telefone, notas, histórico de visitas, consentimentos e assinaturas.',
      'Dados técnicos: logs do sistema, tokens de push/dispositivo e dados necessários para segurança e diagnóstico.',
    ],
    'h3' => '3. Finalidades do tratamento',
    'l3' => [
      'Fornecer as funcionalidades da aplicação (calendário, CRM, reservas, notificações).',
      'Segurança, prevenção de abuso e confiabilidade do serviço.',
      'Atender solicitações do usuário (por exemplo exportação ou anonimização de dados).',
    ],
    'h4' => '4. Destinatários dos dados',
    'p4' => 'Os dados podem ser tratados por provedores de infraestrutura e comunicação apenas na medida necessária para disponibilizar as funcionalidades.',
    'h5' => '5. Retenção e backups',
    'p5' => 'Os dados de produção são mantidos pelo tempo necessário para operação, segurança e suporte. Backups e prazos de retenção dependem da configuração ativa do ambiente de deploy.',
    'h6' => '6. Seus direitos (GDPR)',
    'p6' => 'Você pode solicitar acesso, retificação, limitação, exclusão/anonimização e portabilidade de dados. Solicitações de privacidade: :email.',
  ],
  'de' => [
    'h1' => '1. Verantwortlicher und Projektstatus',
    'p1' => 'Verantwortlicher ist der Betreiber der App als natürliche Person (ohne eingetragenes Unternehmen zum Aktualisierungsdatum). Kontakt: :email. Der Dienst ist derzeit kostenlos.',
    'h2' => '2. Datenkategorien',
    'l2' => [
      'Kontodaten: Telefonnummer (OTP-Login), E-Mail (falls angegeben).',
      'Organisations-/Salon-Daten: Name, Adresse, Beschreibung, Zeitplan, Einstellungen, Dateien und Bilder.',
      'Vom Salon erfasste Kundendaten: Name, Telefon, Notizen, Besuchshistorie, Einwilligungen und Unterschriften.',
      'Technische Daten: System-Logs, Push-/Geräte-Token und sicherheitsrelevante Diagnosedaten.',
    ],
    'h3' => '3. Verarbeitungszwecke',
    'l3' => [
      'Bereitstellung der App-Funktionen (Kalender, CRM, Buchungen, Benachrichtigungen).',
      'Sicherheit, Missbrauchsprävention und Zuverlässigkeit des Dienstes.',
      'Bearbeitung von Nutzeranfragen (z. B. Export oder Anonymisierung).',
    ],
    'h4' => '4. Empfänger der Daten',
    'p4' => 'Daten können durch Infrastruktur- und Kommunikationsanbieter nur im erforderlichen Umfang zur Bereitstellung der Funktionen verarbeitet werden.',
    'h5' => '5. Aufbewahrung und Backups',
    'p5' => 'Produktionsdaten werden so lange gespeichert, wie es für Betrieb, Sicherheit und Support erforderlich ist. Backups und Aufbewahrungsfristen hängen von der aktiven Deployment-Konfiguration ab.',
    'h6' => '6. Ihre Rechte (DSGVO)',
    'p6' => 'Sie können Auskunft, Berichtigung, Einschränkung, Löschung/Anonymisierung und Datenübertragbarkeit verlangen. Datenschutzanfragen: :email.',
  ],
  'es' => [
    'h1' => '1. Responsable del tratamiento y estado del proyecto',
    'p1' => 'El responsable del tratamiento es el operador de la aplicación, una persona física (sin empresa registrada en la fecha de actualización). Contacto: :email. El servicio es actualmente gratuito.',
    'h2' => '2. Categorías de datos',
    'l2' => [
      'Datos de cuenta: número de teléfono (inicio de sesión OTP), correo electrónico (si se proporciona).',
      'Datos de organización/salón: nombre, dirección, descripción, horario, ajustes, archivos e imágenes.',
      'Datos de clientes introducidos por el salón: nombre, teléfono, notas, historial de visitas, consentimientos y firmas.',
      'Datos técnicos: registros del sistema, tokens push/dispositivo y datos necesarios para seguridad y diagnóstico.',
    ],
    'h3' => '3. Finalidades del tratamiento',
    'l3' => [
      'Proporcionar funcionalidades de la app (calendario, CRM, reservas, notificaciones).',
      'Seguridad, prevención de abuso y fiabilidad del servicio.',
      'Atender solicitudes del usuario (por ejemplo exportación o anonimización).',
    ],
    'h4' => '4. Destinatarios de los datos',
    'p4' => 'Los datos pueden ser tratados por proveedores técnicos y de comunicación solo en la medida necesaria para ofrecer las funcionalidades.',
    'h5' => '5. Conservación y copias de seguridad',
    'p5' => 'Los datos de producción se conservan el tiempo necesario para operar el servicio, mantener la seguridad y brindar soporte. Las copias de seguridad y sus plazos dependen de la configuración activa de despliegue.',
    'h6' => '6. Tus derechos (GDPR)',
    'p6' => 'Puedes solicitar acceso, rectificación, limitación, eliminación/anonimización y portabilidad de datos. Solicitudes de privacidad: :email.',
  ],
  'cs' => [
    'h1' => '1. Správce údajů a stav projektu',
    'p1' => 'Správcem údajů je provozovatel aplikace jako fyzická osoba (k datu aktualizace bez registrované společnosti). Kontakt: :email. Služba je nyní poskytována zdarma.',
    'h2' => '2. Kategorie údajů',
    'l2' => [
      'Údaje účtu: telefonní číslo (OTP přihlášení), e-mail (pokud je uveden).',
      'Údaje organizace/salonu: název, adresa, popis, rozvrh, nastavení, soubory a obrázky.',
      'Klientská data zadaná salonem: jméno, telefon, poznámky, historie návštěv, souhlasy a podpisy.',
      'Technická data: systémové logy, push/tokeny zařízení a údaje potřebné pro bezpečnost a diagnostiku.',
    ],
    'h3' => '3. Účely zpracování',
    'l3' => [
      'Poskytování funkcí aplikace (kalendář, CRM, rezervace, notifikace).',
      'Bezpečnost, prevence zneužití a spolehlivost služby.',
      'Vyřízení požadavků uživatele (např. export nebo anonymizace dat).',
    ],
    'h4' => '4. Příjemci údajů',
    'p4' => 'Údaje mohou být zpracovávány technickými a komunikačními poskytovateli pouze v rozsahu nezbytném pro poskytování funkcí.',
    'h5' => '5. Uchovávání a zálohy',
    'p5' => 'Produkční data jsou uchovávána po dobu nezbytnou pro provoz služby, bezpečnost a podporu. Zálohy a doby uchování závisí na aktivní konfiguraci prostředí nasazení.',
    'h6' => '6. Vaše práva (GDPR)',
    'p6' => 'Můžete požádat o přístup, opravu, omezení, výmaz/anonymizaci a přenositelnost dat. Požadavky na soukromí: :email.',
  ],
];

$copy = $content[$lang] ?? $content['en'];
$title = $titles[$lang] ?? $titles['en'];
$siteLocales = config('site_locales.supported', []);
$xDefaultLocale = config('site_locales.x_default', config('site_locales.default', 'pl'));
$canonicalUrl = route('legal.privacy.localized', ['locale' => $lang]);
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
    <link rel="alternate" hreflang="{{ $meta['hreflang'] ?? $code }}" href="{{ route('legal.privacy.localized', ['locale' => $code]) }}">
  @endforeach
  <link rel="alternate" hreflang="x-default" href="{{ route('legal.privacy.localized', ['locale' => $xDefaultLocale]) }}">
  <style>
    :root { --bg:#0b1220; --card:#ffffff; --muted:#5b6475; --text:#111827; --border:#e5e7eb; }
    body{ margin:0; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial; background:linear-gradient(180deg,#0b1220 0%,#0f172a 40%,#0b1220 100%); color:var(--text); }
    .wrap{ max-width:920px; margin:0 auto; padding:22px 14px 40px; }
    .card{ background:rgba(255,255,255,.94); border:1px solid rgba(255,255,255,.18); border-radius:18px; padding:18px; }
    h1{ margin:0 0 6px; font-size:22px; }
    .meta{ color:rgba(17,24,39,.65); font-weight:700; font-size:13px; }
    .langs{ margin:12px 0 0; display:flex; gap:8px; flex-wrap:wrap; }
    .langs a{ display:inline-block; padding:8px 10px; border-radius:999px; border:1px solid var(--border); background:#fff; color:#111827; text-decoration:none; font-weight:800; font-size:13px; }
    .langs a.active{ border-color:#111827; }
    h2{ margin:18px 0 8px; font-size:16px; }
    p,li{ color:#111827; line-height:1.45; font-weight:600; }
    ul{ margin:8px 0 0 18px; padding:0; }
    .muted{ color:rgba(17,24,39,.60); font-weight:700; }
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
          <a class="{{ $lang===$code?'active':'' }}" href="/privacy?lang={{ $code }}">{{ strtoupper($code) }}</a>
        @endforeach
        <a class="muted" href="/terms?lang={{ $lang }}">{{ $termsLabel[$lang] ?? 'Terms' }}</a>
      </div>

      <h2>{{ $copy['h1'] }}</h2>
      <p>{!! str_replace(':email', '<a href="mailto:'.$email.'">'.$email.'</a>', e($copy['p1'])) !!}</p>

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
