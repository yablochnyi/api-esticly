@php
/** @var string $lang */
$updated = '2026-03-26';
$email = 'artem.yablochnyi@gmail.com';

$titles = [
  'uk' => 'Видалення акаунта',
  'pl' => 'Usunięcie konta',
  'en' => 'Account deletion',
  'it' => 'Eliminazione dell\'account',
  'fr' => 'Suppression du compte',
  'pt' => 'Exclusão da conta',
  'de' => 'Kontolöschung',
  'es' => 'Eliminación de la cuenta',
  'cs' => 'Odstranění účtu',
];

$privacyLabel = [
  'uk' => 'Політика конфіденційності',
  'pl' => 'Polityka prywatności',
  'en' => 'Privacy Policy',
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
    'lead' => 'На цій сторінці пояснено, як користувачі Esticly можуть запросити видалення акаунта та пов’язаних із ним даних.',
    'steps_title' => 'Як подати запит',
    'steps' => [
      'Надішліть лист на :email з темою "Видалення акаунта Esticly".',
      'Укажіть номер телефону акаунта, назву салону/організації та, за можливості, e-mail, пов’язаний із акаунтом.',
      'Ми можемо попросити коротке підтвердження, щоб переконатися, що запит подає власник акаунта.',
      'Після підтвердження ми запускаємо процедуру видалення/деактивації акаунта.',
    ],
    'deleted_title' => 'Що буде видалено або анонімізовано',
    'deleted' => [
      'Дані акаунта: номер телефону, e-mail (якщо був указаний), назва салону, адреса, опис, налаштування.',
      'Операційні дані, пов’язані з акаунтом: послуги, графіки, працівники, клієнтська база, нотатки, візити та інші робочі записи, якщо їх не потрібно зберігати за законом.',
      'Файли та медіа, завантажені в межах акаунта.',
    ],
    'retained_title' => 'Що може зберігатися обмежений час',
    'retained' => [
      'Мінімальні технічні або юридично необхідні записи можуть зберігатися протягом обмеженого часу для безпеки, запобігання зловживанням, бухгалтерії чи виконання законних обов’язків.',
      'Резервні копії можуть зберігатися до 14 днів відповідно до поточної продакшен-конфігурації backup retention.',
    ],
    'timing_title' => 'Строк виконання',
    'timing' => 'Ми намагаємося обробляти запити на видалення акаунта без невиправданої затримки, зазвичай протягом 30 днів після підтвердження запиту.',
  ],
  'pl' => [
    'lead' => 'Ta strona wyjaśnia, jak użytkownicy Esticly mogą poprosić o usunięcie konta i powiązanych z nim danych.',
    'steps_title' => 'Jak złożyć wniosek',
    'steps' => [
      'Wyślij e-mail na adres :email z tematem "Usunięcie konta Esticly".',
      'Podaj numer telefonu konta, nazwę salonu/organizacji oraz, jeśli to możliwe, adres e-mail powiązany z kontem.',
      'Możemy poprosić o krótkie potwierdzenie, aby upewnić się, że wniosek składa właściciel konta.',
      'Po potwierdzeniu uruchamiamy procedurę usunięcia/dezaktywacji konta.',
    ],
    'deleted_title' => 'Co zostanie usunięte lub zanonimizowane',
    'deleted' => [
      'Dane konta: numer telefonu, e-mail (jeśli podano), nazwa salonu, adres, opis i ustawienia.',
      'Dane operacyjne powiązane z kontem: usługi, harmonogramy, pracownicy, baza klientów, notatki, wizyty i inne rekordy robocze, o ile prawo nie wymaga ich przechowywania.',
      'Pliki i media przesłane w ramach konta.',
    ],
    'retained_title' => 'Co może być przechowywane przez ograniczony czas',
    'retained' => [
      'Minimalne techniczne lub prawnie wymagane zapisy mogą być przechowywane przez ograniczony czas ze względów bezpieczeństwa, zapobiegania nadużyciom, księgowości lub obowiązków prawnych.',
      'Kopie zapasowe mogą być przechowywane do 14 dni zgodnie z obecną produkcyjną konfiguracją retencji backupów.',
    ],
    'timing_title' => 'Termin realizacji',
    'timing' => 'Staramy się realizować wnioski o usunięcie konta bez zbędnej zwłoki, zazwyczaj w ciągu 30 dni od potwierdzenia zgłoszenia.',
  ],
  'en' => [
    'lead' => 'This page explains how Esticly users can request deletion of their account and related data.',
    'steps_title' => 'How to request account deletion',
    'steps' => [
      'Send an email to :email with the subject "Esticly account deletion".',
      'Include the account phone number, the salon/organization name, and, if available, the email address linked to the account.',
      'We may ask for a short confirmation to verify that the request is being made by the account owner.',
      'After confirmation, we start the account deletion/deactivation procedure.',
    ],
    'deleted_title' => 'What will be deleted or anonymized',
    'deleted' => [
      'Account data: phone number, email (if provided), salon name, address, description, and settings.',
      'Operational data linked to the account: services, schedules, staff, client database, notes, visits, and other work records, unless retention is required by law.',
      'Files and media uploaded within the account.',
    ],
    'retained_title' => 'What may be retained for a limited time',
    'retained' => [
      'Minimal technical or legally required records may be retained for a limited period for security, abuse prevention, accounting, or legal compliance.',
      'Backup copies may remain for up to 14 days according to the current production backup retention configuration.',
    ],
    'timing_title' => 'Processing time',
    'timing' => 'We aim to process account deletion requests without undue delay, usually within 30 days after confirmation of the request.',
  ],
  'it' => [
    'lead' => 'Questa pagina spiega come gli utenti di Esticly possono richiedere l’eliminazione dell’account e dei dati correlati.',
    'steps_title' => 'Come inviare la richiesta',
    'steps' => [
      'Invia un\'e-mail a :email con oggetto "Eliminazione account Esticly".',
      'Indica il numero di telefono dell’account, il nome del salone/organizzazione e, se disponibile, l’e-mail collegata all’account.',
      'Potremmo richiedere una breve conferma per verificare che la richiesta provenga dal titolare dell’account.',
      'Dopo la conferma avviamo la procedura di eliminazione/disattivazione dell’account.',
    ],
    'deleted_title' => 'Cosa verrà eliminato o anonimizzato',
    'deleted' => [
      'Dati dell’account: numero di telefono, e-mail (se fornita), nome del salone, indirizzo, descrizione e impostazioni.',
      'Dati operativi collegati all’account: servizi, orari, staff, database clienti, note, visite e altri record di lavoro, salvo obblighi legali di conservazione.',
      'File e media caricati nell’account.',
    ],
    'retained_title' => 'Cosa può essere conservato per un periodo limitato',
    'retained' => [
      'Dati tecnici minimi o legalmente necessari possono essere conservati per un periodo limitato per sicurezza, prevenzione abusi, contabilità o obblighi legali.',
      'Le copie di backup possono rimanere fino a 14 giorni secondo l’attuale configurazione di retention in produzione.',
    ],
    'timing_title' => 'Tempi di elaborazione',
    'timing' => 'Cerchiamo di elaborare le richieste di eliminazione senza ritardi ingiustificati, di norma entro 30 giorni dalla conferma.',
  ],
  'fr' => [
    'lead' => 'Cette page explique comment les utilisateurs d’Esticly peuvent demander la suppression de leur compte et des données associées.',
    'steps_title' => 'Comment faire la demande',
    'steps' => [
      'Envoyez un e-mail à :email avec l’objet "Suppression du compte Esticly".',
      'Indiquez le numéro de téléphone du compte, le nom du salon/de l’organisation et, si possible, l’e-mail lié au compte.',
      'Nous pouvons demander une courte confirmation pour vérifier que la demande provient bien du propriétaire du compte.',
      'Après confirmation, nous lançons la procédure de suppression/désactivation du compte.',
    ],
    'deleted_title' => 'Ce qui sera supprimé ou anonymisé',
    'deleted' => [
      'Données du compte : numéro de téléphone, e-mail (si fourni), nom du salon, adresse, description et paramètres.',
      'Données opérationnelles liées au compte : services, horaires, équipe, base clients, notes, visites et autres enregistrements de travail, sauf obligation légale de conservation.',
      'Fichiers et médias téléversés dans le compte.',
    ],
    'retained_title' => 'Ce qui peut être conservé pendant une durée limitée',
    'retained' => [
      'Des enregistrements techniques minimaux ou légalement requis peuvent être conservés pendant une durée limitée pour la sécurité, la prévention des abus, la comptabilité ou le respect des obligations légales.',
      'Les sauvegardes peuvent être conservées jusqu’à 14 jours selon la configuration actuelle de rétention en production.',
    ],
    'timing_title' => 'Délai de traitement',
    'timing' => 'Nous essayons de traiter les demandes de suppression sans retard injustifié, généralement dans les 30 jours après confirmation.',
  ],
  'pt' => [
    'lead' => 'Esta página explica como os usuários do Esticly podem solicitar a exclusão da conta e dos dados relacionados.',
    'steps_title' => 'Como solicitar a exclusão da conta',
    'steps' => [
      'Envie um e-mail para :email com o assunto "Exclusão da conta Esticly".',
      'Informe o número de telefone da conta, o nome do salão/organização e, se possível, o e-mail vinculado à conta.',
      'Podemos solicitar uma breve confirmação para verificar se o pedido está sendo feito pelo titular da conta.',
      'Após a confirmação, iniciamos o processo de exclusão/desativação da conta.',
    ],
    'deleted_title' => 'O que será excluído ou anonimizado',
    'deleted' => [
      'Dados da conta: número de telefone, e-mail (se informado), nome do salão, endereço, descrição e configurações.',
      'Dados operacionais vinculados à conta: serviços, agendas, equipe, base de clientes, notas, visitas e outros registros, salvo obrigação legal de retenção.',
      'Arquivos e mídias enviados dentro da conta.',
    ],
    'retained_title' => 'O que pode ser mantido por tempo limitado',
    'retained' => [
      'Registros técnicos mínimos ou exigidos por lei podem ser mantidos por período limitado por motivos de segurança, prevenção de abuso, contabilidade ou obrigações legais.',
      'Cópias de backup podem permanecer por até 14 dias conforme a configuração atual de retenção em produção.',
    ],
    'timing_title' => 'Prazo de processamento',
    'timing' => 'Buscamos processar pedidos de exclusão sem atraso indevido, normalmente em até 30 dias após a confirmação.',
  ],
  'de' => [
    'lead' => 'Diese Seite erklärt, wie Esticly-Nutzer die Löschung ihres Kontos und der damit verbundenen Daten beantragen können.',
    'steps_title' => 'So beantragen Sie die Kontolöschung',
    'steps' => [
      'Senden Sie eine E-Mail an :email mit dem Betreff "Esticly Kontolöschung".',
      'Geben Sie die Telefonnummer des Kontos, den Namen des Salons/der Organisation und nach Möglichkeit die mit dem Konto verknüpfte E-Mail-Adresse an.',
      'Wir können eine kurze Bestätigung anfordern, um sicherzustellen, dass die Anfrage vom Kontoinhaber stammt.',
      'Nach der Bestätigung starten wir den Lösch-/Deaktivierungsprozess.',
    ],
    'deleted_title' => 'Was gelöscht oder anonymisiert wird',
    'deleted' => [
      'Kontodaten: Telefonnummer, E-Mail (falls angegeben), Salonname, Adresse, Beschreibung und Einstellungen.',
      'Betriebsdaten zum Konto: Services, Zeitpläne, Mitarbeiter, Kundendatenbank, Notizen, Besuche und andere Arbeitsdaten, soweit keine gesetzliche Aufbewahrungspflicht besteht.',
      'Dateien und Medien, die innerhalb des Kontos hochgeladen wurden.',
    ],
    'retained_title' => 'Was für begrenzte Zeit aufbewahrt werden kann',
    'retained' => [
      'Minimale technische oder gesetzlich erforderliche Aufzeichnungen können für einen begrenzten Zeitraum aus Gründen der Sicherheit, Missbrauchsprävention, Buchhaltung oder gesetzlicher Pflichten aufbewahrt werden.',
      'Backup-Kopien können gemäß der aktuellen Produktions-Retention bis zu 14 Tage bestehen bleiben.',
    ],
    'timing_title' => 'Bearbeitungszeit',
    'timing' => 'Wir bemühen uns, Anfragen ohne unangemessene Verzögerung zu bearbeiten, in der Regel innerhalb von 30 Tagen nach Bestätigung.',
  ],
  'es' => [
    'lead' => 'Esta página explica cómo los usuarios de Esticly pueden solicitar la eliminación de su cuenta y de los datos relacionados.',
    'steps_title' => 'Cómo solicitar la eliminación de la cuenta',
    'steps' => [
      'Envía un correo a :email con el asunto "Eliminación de cuenta Esticly".',
      'Indica el número de teléfono de la cuenta, el nombre del salón/organización y, si es posible, el correo asociado a la cuenta.',
      'Podemos solicitar una breve confirmación para verificar que la solicitud la realiza el propietario de la cuenta.',
      'Tras la confirmación, iniciamos el procedimiento de eliminación/desactivación de la cuenta.',
    ],
    'deleted_title' => 'Qué se eliminará o anonimizará',
    'deleted' => [
      'Datos de la cuenta: número de teléfono, correo electrónico (si se proporcionó), nombre del salón, dirección, descripción y ajustes.',
      'Datos operativos vinculados a la cuenta: servicios, horarios, personal, base de clientes, notas, visitas y otros registros de trabajo, salvo obligación legal de conservación.',
      'Archivos y contenido multimedia cargados dentro de la cuenta.',
    ],
    'retained_title' => 'Qué puede conservarse por tiempo limitado',
    'retained' => [
      'Los registros técnicos mínimos o legalmente necesarios pueden conservarse por un tiempo limitado por motivos de seguridad, prevención de abuso, contabilidad o cumplimiento legal.',
      'Las copias de seguridad pueden permanecer hasta 14 días según la configuración actual de retención en producción.',
    ],
    'timing_title' => 'Plazo de tramitación',
    'timing' => 'Intentamos tramitar las solicitudes sin demora indebida, normalmente en un plazo de 30 días tras la confirmación.',
  ],
  'cs' => [
    'lead' => 'Tato stránka vysvětluje, jak mohou uživatelé Esticly požádat o odstranění účtu a souvisejících dat.',
    'steps_title' => 'Jak podat žádost',
    'steps' => [
      'Pošlete e-mail na :email s předmětem "Odstranění účtu Esticly".',
      'Uveďte telefonní číslo účtu, název salonu/organizace a pokud možno e-mail spojený s účtem.',
      'Můžeme požádat o krátké potvrzení, abychom ověřili, že žádost podává vlastník účtu.',
      'Po potvrzení zahájíme proces odstranění/deaktivace účtu.',
    ],
    'deleted_title' => 'Co bude odstraněno nebo anonymizováno',
    'deleted' => [
      'Údaje účtu: telefonní číslo, e-mail (pokud byl uveden), název salonu, adresa, popis a nastavení.',
      'Provozní data spojená s účtem: služby, rozvrhy, zaměstnanci, databáze klientů, poznámky, návštěvy a další pracovní záznamy, pokud zákon nevyžaduje jejich uchování.',
      'Soubory a média nahraná v rámci účtu.',
    ],
    'retained_title' => 'Co může být uchováno po omezenou dobu',
    'retained' => [
      'Minimální technické nebo zákonem vyžadované záznamy mohou být uchovány po omezenou dobu kvůli bezpečnosti, prevenci zneužití, účetnictví nebo právním povinnostem.',
      'Zálohy mohou zůstat až 14 dní podle aktuální produkční konfigurace retention.',
    ],
    'timing_title' => 'Doba zpracování',
    'timing' => 'Žádosti se snažíme zpracovat bez zbytečného odkladu, obvykle do 30 dnů od potvrzení.',
  ],
];

$copy = $content[$lang] ?? $content['en'];
$title = $titles[$lang] ?? $titles['en'];
$siteLocales = config('site_locales.supported', []);
$xDefaultLocale = config('site_locales.x_default', config('site_locales.default', 'pl'));
$canonicalUrl = route('legal.account-deletion.localized', ['locale' => $lang]);
@endphp
<!doctype html>
<html lang="{{ $lang }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $title }} · Esticly</title>
  <meta name="robots" content="index,follow">
  <link rel="canonical" href="{{ $canonicalUrl }}">
  @foreach($siteLocales as $code => $meta)
    <link rel="alternate" hreflang="{{ $meta['hreflang'] ?? $code }}" href="{{ route('legal.account-deletion.localized', ['locale' => $code]) }}">
  @endforeach
  <link rel="alternate" hreflang="x-default" href="{{ route('legal.account-deletion.localized', ['locale' => $xDefaultLocale]) }}">
  <style>
    :root { color-scheme: light; --bg:#f8f8fd; --card:#fff; --text:#1e2030; --muted:#6d7288; --line:#e7e8f1; --accent:#7c69e3; }
    * { box-sizing: border-box; }
    body { margin:0; font-family: ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background:var(--bg); color:var(--text); }
    .wrap { max-width: 860px; margin: 0 auto; padding: 32px 20px 56px; }
    .card { background:var(--card); border:1px solid var(--line); border-radius:24px; padding:28px; box-shadow:0 14px 40px rgba(52,58,88,.06); }
    .top { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap; margin-bottom:20px; }
    .brand { font-size:14px; color:var(--muted); margin-bottom:8px; }
    h1, h2 { margin:0 0 12px; line-height:1.1; }
    h1 { font-size: clamp(32px, 5vw, 44px); }
    h2 { font-size: 20px; margin-top: 28px; }
    p, li { font-size:16px; line-height:1.65; color:var(--text); }
    .muted { color:var(--muted); }
    ul, ol { padding-left: 20px; margin: 12px 0 0; }
    .chip { display:inline-flex; align-items:center; padding:8px 12px; border:1px solid var(--line); border-radius:999px; color:var(--muted); font-size:14px; }
    .footer { margin-top:24px; display:flex; gap:14px; flex-wrap:wrap; }
    a { color:var(--accent); text-decoration:none; }
    a:hover { text-decoration:underline; }
    .lang-switch { display:flex; gap:8px; flex-wrap:wrap; }
    .lang-switch a { padding:8px 10px; border:1px solid var(--line); border-radius:999px; color:var(--muted); }
    .lang-switch a.active { border-color: rgba(124,105,227,.35); background: rgba(124,105,227,.08); color: var(--accent); font-weight:600; }
  </style>
</head>
<body>
  <main class="wrap">
    <div class="card">
      <div class="top">
        <div>
          <div class="brand">Esticly</div>
          <h1>{{ $title }}</h1>
          <p class="muted">{{ $copy['lead'] }}</p>
        </div>
        <div class="chip">{{ $metaUpdated[$lang] ?? $metaUpdated['en'] }}: {{ $updated }}</div>
      </div>

      <div class="lang-switch">
        @foreach($siteLocales as $code => $meta)
          <a href="{{ route('legal.account-deletion.localized', ['locale' => $code]) }}" class="{{ $lang === $code ? 'active' : '' }}">{{ strtoupper($code) }}</a>
        @endforeach
      </div>

      <h2>{{ $copy['steps_title'] }}</h2>
      <ol>
        @foreach($copy['steps'] as $item)
          <li>{!! str_replace(':email', '<a href="mailto:' . e($email) . '">' . e($email) . '</a>', e($item)) !!}</li>
        @endforeach
      </ol>

      <h2>{{ $copy['deleted_title'] }}</h2>
      <ul>
        @foreach($copy['deleted'] as $item)
          <li>{{ $item }}</li>
        @endforeach
      </ul>

      <h2>{{ $copy['retained_title'] }}</h2>
      <ul>
        @foreach($copy['retained'] as $item)
          <li>{{ $item }}</li>
        @endforeach
      </ul>

      <h2>{{ $copy['timing_title'] }}</h2>
      <p>{{ $copy['timing'] }}</p>

      <div class="footer">
        <a href="mailto:{{ $email }}">{{ $email }}</a>
        <a href="{{ route('legal.privacy.localized', ['locale' => $lang]) }}">{{ $privacyLabel[$lang] ?? $privacyLabel['en'] }}</a>
      </div>
    </div>
  </main>
</body>
</html>
