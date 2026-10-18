# Production source reconciliation: 2026-10-18

The running `beautycrm_api` container was used as the reference for application
behavior. Source files were compared by SHA-256 against the host checkout and
the local repository. Secrets, storage, dependencies and generated caches were
excluded. No database migrations, OTP policy changes or application restarts
are part of this reconciliation.

## Preserved production behavior

- Apple and Google subscription periods, deferred replacements and reconciliation.
- Turnstile challenges, App Check, OTP pause/registered-only switches and the
  existing daily, hourly and per-phone budgets.
- Structured Google Calendar failure diagnostics already running in production.

The local unfinished OTP network/global-hour limiter and unpublished website
attribution changes are preserved in the Git branch
`archive/local-before-production-sync-20261018`. They are not in the production
application source on `main`.

New regression tests, deployment notes and environment variable documentation
are retained on `main`; they are not production runtime configuration. Real
`.env` files, service-account credentials, Apple private keys and backups remain
outside Git. Local mobile IDE settings are also excluded.

The historical September deployment notes describe the deployment state on
their respective dates, not the current image or source checkout. This document
supersedes their source-synchronization warnings as of this reconciliation.

Source equality is not a security certification of the host. Credential rotation
and remediation of the previously discovered remote-access incident remain
separate operational work.

## Verification

- 577 runtime/source/configuration and public asset files match the running
  container by SHA-256. Tests, documentation, `.env.example` and `.gitignore`
  are intentionally maintained separately from the older copies in the image.
- Backend: 149 tests, 978 assertions passed using an isolated in-memory database.
- Mobile: 172 tests passed with the pinned Flutter 3.38.5 SDK; analysis of `lib`
  and `test` reported no issues.
- The old backend landing-page smoke test now asserts the existing HTTP 301
  locale redirect. The mobile launch smoke test now mocks secure storage and
  asserts the actual phone screen instead of obsolete branding.
- No store release, signed mobile archive or new production image was created.
