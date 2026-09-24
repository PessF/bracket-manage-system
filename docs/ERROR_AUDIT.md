# Application error audit — 2026-09-24

Baseline: `0729ab0` on `main`.

## Findings and fixes

- Array-valued search/status/view parameters reproduced HTTP 500 errors on Events, Competitions, and Bracket pages. Filters and pagination are now validated before string conversion or queries. Invalid HTML GET filters return a themed 422 page with a clean retry URL; API validation retains field details.
- Ranking forms competed with the shared navigation-form submission handler. Async forms now manage their own busy state, reject duplicate submissions, and restore the clicked button after a failed save. Implicit submit buttons are supported. HTML responses (including expired-session redirects) are rejected, and a saved result is distinguished from a failed display refresh.
- Broad controller catches returned raw exception messages and reported unexpected API failures as validation errors. Expected domain/input errors retain useful messages; unexpected failures are logged and return generic messages (HTTP 500 for JSON operations).
- Database failures on the competition index were silently shown as an empty list in local mode. They now produce a recoverable 503 response. Shared 409/419/429/500/503 error handling uses existing dark card styles and localized copy. The error document does not require database-backed authentication or sessions.
- Blade breadcrumbs, podiums, and ranking results now handle missing event or participant relations while preserving ranks and scores. All 85 compiled views passed PHP syntax checks.
- Invalid legacy bracket scheduling values now omit estimated times instead of throwing during rendering.
- Disabled browser storage no longer prevents live-page reloads.
- No-build pages previously omitted common UI handlers. Shared interaction code now loads in both Vite and Blade fallback paths, including bracket search.
- Docker's APP_ENV override caused two baseline test failures. Tests now force the isolated testing environment. A stale viewer assertion now includes the intended bracket page class.

## Verification

- Backend: **108 tests passed, 3,824 assertions**, using isolated in-memory SQLite. New regression coverage includes invalid filters, server/database failures, ranking save failures, all competition formats, empty competitions, and legacy schedules.
- Production Vite build passed. The existing root-owned build directory required generating assets in a temporary directory and copying them through the running app container. Existing font paths remain runtime-resolved.
- PHP formatting and `git diff --check` passed.
- Browser: public page crawl plus 23 isolated admin/page fixtures at 1366px and 390px, and an additional no-build ranking fixture. Across 48 fixture page/viewport checks: no JavaScript exceptions or document-level horizontal overflow; shared background remained `#0a0f15`.
- Browser failure injection confirmed duplicate ranking submissions issue one request, failed saves permit retry, buttons are restored, and unexpected HTML yields the localized error message in both bundled and fallback rendering.
- Rechecked malformed filters against the running local server: HTTP 422, replacing the reproduced HTTP 500s. Normal Events and Competitions pages returned HTTP 200.
- All existing database migrations are applied. Historical missing-column and database-connectivity log entries were distinguished from currently reproducible failures; no development database reset or schema change was needed.

## Scope

Browser admin fixtures use isolated test data and mocked failure responses; they do not establish coverage of every authenticated live-data interaction or an external production deployment. Existing theme CSS and tournament rules remain unchanged.
