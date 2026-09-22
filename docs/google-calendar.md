# Google Calendar: setup and release checklist

## Behaviour

One-way sync: Esticly -> a separate Google calendar named Esticly. Google does not
write back into appointments. A user explicitly connects a Google account and
confirms sharing in the authenticated app before any visit is exported.

Owners export their organisation's visits. Staff export only their assigned
visits while base access remains enabled. The payload contains the client name,
service and start/end instants, not phones, notes, payments or photographs.
Events are private, have no attendees, and do not send invitations/reminders.
Pending, completed and cancelled visits (including history) are copied. Status
labels appear in titles and descriptions using the language saved when Google
was connected. Google event colors are grape (3), basil (10) and tomato (11).
Cancelled visits remain visible but do not block time. Their Google event status
stays `confirmed`: Google's `cancelled` means a deleted/hidden event, not a
visible business status. Only deleted or reassigned-out-of-scope visits are
removed from this connection's calendar.

Existing mapped events receive the new text/colors on the next reconciliation
pass without changing IDs. Cancelled visits removed by the previous implementation
are exported again. No migration, new OAuth scope or reconnection is needed.
Deploy the updated API and restart queue workers before testing status colors.

Visit creation, calendar-visible edits and deletion queue a targeted sync as soon
as the database transaction commits, including public bookings and staff changes.
The job reads the latest visit, bypasses the full-sync cursor and checks the same
owner/staff permissions. Lock contention and temporary provider failures retry
after five seconds; edits during an in-flight job are not dropped. No Google HTTP
request runs in the visit-save request. Native Google clients can still take time
to refresh after the server has updated an event.

The scheduler queues a reconciliation every minute as a recovery mechanism (also
covering bulk SQL changes that do not emit model events). Each worker processes a
bounded page, saving progress and event IDs before network calls. Large calendars
may take several cycles. Outages/rate limits retain pending work; reconnection is
required when permission is revoked. There is no instant/real-time guarantee.
Manual changes in Google are not imported; change appointments in Esticly.
An unchanged appointment manually deleted in Google is not automatically restored
until its Esticly payload changes. This is a schedule mirror, not an immutable backup.

Disconnecting stops export, clears stored Google tokens and invalidates outstanding
OAuth attempts. Already exported Google events remain. Account deletion cascades
stored credentials/mappings; it does not erase the user's independent Google copy.
Previously copied data cannot be recalled from screenshots, exports or other devices.

## Google Cloud Console

1. Open https://console.cloud.google.com/ and select the Esticly project. Do not
   change Firebase App Check, signing certificates or Google Play billing keys.
2. APIs & Services -> Library -> Google Calendar API -> Enable.
3. Google Auth Platform -> Branding: application name Esticly, your support email,
   homepage https://esticly.com, the real public privacy/terms URLs and verified
   authorised domain esticly.com. Update the privacy policy to explain calendar
   sharing, storage of OAuth credentials, withdrawal and independent Google copies.
4. Audience: External (for ordinary customer Google accounts). During testing add
   only your test Google accounts to Test users.
5. Data Access: request only openid, email and
   https://www.googleapis.com/auth/calendar.app.created.
   Do not request full calendar access. Complete any verification Google requests
   for your branding/scopes before broad production use.
6. Clients -> Create client -> Web application (not Android or iOS).
   Name: Esticly Calendar backend.
   Authorised redirect URI, exactly:
   https://esticly.com/integrations/google-calendar/callback
   No JavaScript origin is needed for this server-side code flow.
7. Store Client ID and Client secret only in server environment variables. Never
   commit the secret or put it in Dart, a mobile build, screenshots or chat.

Testing-mode refresh tokens for these scopes normally expire after seven days.
Do not leave the OAuth app in Testing for paying customers. Move to Production
and complete Google's required verification after the test checklist passes.
Use a separate OAuth client/project and HTTPS callback for staging.

## Server configuration

Add to the server's protected .env.docker, and provide the same values to the API,
scheduler and queue workers:

```dotenv
GOOGLE_CALENDAR_CLIENT_ID=YOUR_WEB_CLIENT_ID
GOOGLE_CALENDAR_CLIENT_SECRET=YOUR_WEB_CLIENT_SECRET
GOOGLE_CALENDAR_REDIRECT_URI=https://esticly.com/integrations/google-calendar/callback
GOOGLE_CALENDAR_CACHE_STORE=database
```

The existing APP_KEY encrypts Google tokens and account email in the database.
Back up APP_KEY securely; do not rotate it casually. The shared database cache
must support atomic locks; do not use array/file caches across production hosts.
No new Composer or Flutter dependencies are required.

Deploy the API migration before releasing the mobile screen:

```sh
docker compose exec -u www-data api php artisan migrate --force
docker compose exec -u www-data api php artisan config:clear
docker compose exec -u www-data api php artisan schedule:list
docker compose exec -u www-data api php artisan calendar:sync
```

Rebuild/restart the services using the project's existing deployment flow so
workers use the new code and environment. The existing scheduler must run every
minute, and a real asynchronous queue worker must process the default queue.
Do NOT use QUEUE_CONNECTION=sync in production: confirming in the app should not
wait for an entire page of Google requests. Do NOT flush caches/OTP replay locks.
Inspect only safe statuses/IDs and google_calendar_sync_failed reason codes;
do not dump encrypted credentials, OAuth callbacks or token responses into logs.

## Mobile and native calendars

Settings -> Calendar sync -> Connect Google Calendar. Sign in in the system
browser, return to Esticly, verify the displayed email, accept the sharing
checkbox and enable sync. The browser does not install tokens on the phone.
No device-calendar permission is requested and no direct iCloud API is used.
Both iOS and Android offer device sync and Google sync independently. Using both
may show duplicate appointments; consent in either flow warns about this.
The Google Calendar app on either platform can display the exported events when
signed into the same Google account; adding an iPhone system account is only
needed when viewing the Google copy in Apple's Calendar app.

On Android, add the same Google account in system accounts, enable Calendar sync
and select the Esticly calendar in Google Calendar or Samsung Calendar.
On iPhone, Settings -> Apps -> Calendar -> Calendar Accounts -> Add Account ->
Google, enable Calendars, then select Esticly in the Calendar app. If a secondary
calendar is missing, check Google's calendar selection at
https://calendar.google.com/calendar/syncselect . Native calendar refresh has its
own schedule, independent of the server; the app cannot enable a system account
or force Apple/Samsung to refresh automatically.

## Required real-account test before release

- Use an isolated test Esticly organisation and Google account, not customer data.
- Confirm consent is required after OAuth and no export happens before it.
- Close Esticly; create/change/cancel a visit through another authorised session.
  Verify the Google copy without waiting for the minute scheduler, then check
  native Android/iOS display after its own refresh.
- Check pending -> completed -> cancelled -> pending keeps one Google event,
  changes its localized title and color, and leaves cancelled visits visible.
  Actual deletion must still remove the exported event. Select only one export
  source while checking colors so a second local copy cannot obscure the result.
- Test organisation timezone versus device timezone and an appointment spanning
  a DST transition. API event timestamps use explicit UTC offsets.
- Repeat sync and simulate an interrupted response; verify no duplicate events.
- Test staff isolation, reassignment, revoked base access and another organisation.
- Revoke Google authorisation; verify reconnect status and recovery after linking.
- Disconnect; verify tokens are cleared, copying stops and existing copies remain.
- Delete the separate Google calendar; verify reconnect is requested.
- Verify your published privacy policy and Google OAuth audience before rollout.

Automated checks:

```sh
php vendor/bin/phpunit tests/Feature/GoogleCalendarTest.php
```

Run master-app/test/google_calendar_test.dart with Flutter for consent, status,
localisation and layout checks. All automated Google calls are mocked; they do
not prove that the real OAuth client/audience/scopes are correctly configured.

References:
- https://developers.google.com/workspace/calendar/api/auth
- https://developers.google.com/identity/protocols/oauth2/web-server
- https://developers.google.com/identity/protocols/oauth2
- https://support.google.com/calendar/answer/99358
