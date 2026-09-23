# EasyKids Competition Tournament Management System

Laravel 12 + PHP 8.3 + MySQL 8 implementation of the EasyKids tournament engine. It supports Ranking, Round Robin, Single Elimination, and Double Elimination, including BYEs, winner/loser dependency propagation, configurable one-match or bracket-reset Grand Finals, ranking attempts, and Round Robin standings.

## Start on Linux with Docker

Run from this directory:

```bash
cp .env.example .env
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
sudo chown -R "$(id -u):$(id -g)" storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

export APP_UID="$(id -u)"
export APP_GID="$(id -g)"
docker compose build
docker compose run --rm --no-deps app composer install
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
```

Open <http://127.0.0.1:8080>. The seeder creates a LIVE eight-team Double Elimination bracket with 14 matches.

Thai is the default interface language and the application timezone defaults to `Asia/Bangkok`. Use the `EN / ไทย` switch in the header to change the language; the selection is stored in the browser session.

## Access control

The home page redirects to `/events`. Visitors and signed-in users see the same Event → Competition → Bracket hierarchy. `/events/{event}` lists only that event's competitions, including draft, ready, live, completed, and archived entries. Visitors and viewer accounts can browse without a login. Administrators also receive event and competition CRUD controls, participant management, imports, lifecycle actions, and score entry. Web writes require both `auth` and `admin` middleware; hiding buttons is not the security boundary.

Existing `/tournaments/{id}` URLs remain valid. The optional `/view/{token}` link offers a compact, read-only display from READY onward, including completed results. It is a convenience link, not an access-control secret: the event and competition pages are public. Administrators can replace it with a unique 4–36 character short name; the old share URL then stops working.

## Event structure and migration

`Event` (`events`) owns many `Tournament` records (`external_tournaments`). “Competition” is the user-facing name for the existing Tournament model. Its `competition` text field remains descriptive metadata; it is not an event identifier. Stages, groups, participants, matches, ranking attempts, and standings retain their existing tournament foreign keys.

The new `2026_09_22_000000_add_events_to_competitions.php` migration creates the event table, adds `external_tournaments.event_id`, places every existing competition in one **Existing competitions** event, then makes the foreign key non-null. No applied migration is modified and no bracket or result is regenerated. Administrators can rename this event and move competitions through their settings or by updating `event_id` through the flat competition API.

For compatibility, older integrations and seeders that omit `event_id` on creation are assigned to **Existing competitions**. New web forms require an event selection. Supplied IDs must identify an existing event. An event with competitions cannot be deleted (HTTP 409); move or explicitly delete its competitions first. The database also restricts deletion of referenced events. Deleting a competition retains the existing cascade behavior for its own dependent data.

Event fields are `name`, `description`, `venue`, `starts_on`, and `ends_on`. The end date cannot precede the start date. Event dates describe the overall event; competition schedules remain independent.

To create the first administrator:

1. Set a long random `ADMIN_SETUP_TOKEN` in the server-side `.env` file.
2. Run `php artisan migrate --force`.
3. Open `/admin/setup`, enter that setup token, and create the administrator account.
4. Remove `ADMIN_SETUP_TOKEN` from `.env` after the account has been created, then run `php artisan config:clear` or redeploy.

The setup page disables itself as soon as an administrator exists. Additional administrator or viewer accounts can be managed from **Users** after signing in. Passwords must be at least 12 characters and include uppercase and lowercase letters, a number, and a symbol.

## Plesk deployment

Use Plesk Laravel Toolkit to install from this repository's `main` branch. Set the domain document root to `httpdocs/public`, select PHP 8.2 or newer, and configure production values in Laravel Toolkit's `.env` editor. The phpMyAdmin URL is not the database hostname; for a database on the same Plesk server, use the database server value shown by Plesk (commonly `localhost`).

Recommended deployment commands:

```bash
composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
php artisan migrate --force
npm ci
npm run build
php artisan optimize
```

Use `APP_ENV=production`, `APP_DEBUG=false`, HTTPS for `APP_URL`, `SESSION_SECURE_COOKIE=true`, `APP_LOCALE=th`, `APP_FALLBACK_LOCALE=en`, and `APP_TIMEZONE=Asia/Bangkok`. Never commit the production `.env` file. Do not run `php artisan db:seed` in production unless demonstration data is intentionally required.

Participant CSV import is available while a tournament is `DRAFT` or `READY`. Open its Overview & Participants page, download the template, then upload a UTF-8 CSV with these supported columns:

```text
Team Name, Team ID, School, Coach, Member 1, Member 2, Member 3, Member 4, Seed
```

The importer also accepts `Team` or `teamName` as the team-name header, `memberNames` for a comma-separated member list, and Thai headers such as `ชื่อทีม`, `รหัสทีม`, `โรงเรียน`, `โค้ช`, and `สมาชิก 1`–`สมาชิก 4`. Imports are limited to 1,000 data rows; invalid and duplicate rows are reported while valid rows are imported transactionally.

Useful commands:

```bash
docker compose ps
docker compose logs -f app web db
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint
docker compose down
```

MySQL data is kept in the `mysql-data` Docker volume. `docker compose down` preserves it; adding `--volumes` deletes it.

## API

Open `/api/docs` for the complete endpoint list and request examples.

The detailed Thai integration guide is available at [`docs/API_MANUAL_TH.md`](docs/API_MANUAL_TH.md).

Read endpoints are public; all API writes require an administrator bearer token. Existing competition APIs cover standard and advanced blueprints, stages, groups and assignments, participant/member CRUD and CSV import, lifecycle, match progress/results and score corrections, ranking attempts, and standings. Create or revoke a bearer token from **API access**. API messages can be selected with `?lang=th` / `?lang=en` or the `Accept-Language` header. An invalid bearer token on a flat read endpoint is rejected; anonymous reads need no token.

| Resource | Public read | Admin write |
| --- | --- | --- |
| Events | `GET /api/events`, `GET /api/events/{event}` | `POST /api/events`, `PUT/PATCH/DELETE /api/events/{event}` |
| Event competitions | `GET /api/events/{event}/competitions` | `POST /api/events/{event}/competitions` |
| Scoped competition | `GET /api/events/{event}/competitions/{tournament}` | `PUT/PATCH/DELETE` on the same URL |
| Bracket matches | `GET /api/events/{event}/competitions/{tournament}/bracket` | Use existing match-result endpoints |

Nested routes return 404 if a competition belongs to another event. Their URL determines the event on writes. To move a competition, use `PATCH /api/tournaments/{id}` with `event_id`. The existing flat list also supports `?event_id={uuid}`; pagination is capped at 100. Bracket responses use the existing match-list contract (unpaginated unless pagination is requested), including source and destination IDs.

Responses use a consistent `{ "success": true, "data": ... }` envelope.

## Docker Hub TLS error

If Docker reports that `registry-1.docker.io` has a certificate for another domain (for example `*.zerovar.com`), DNS is returning a poisoned/stale address. Do not disable TLS verification. Check it with:

```bash
getent ahostsv4 registry-1.docker.io
openssl s_client -connect registry-1.docker.io:443 -servername registry-1.docker.io </dev/null 2>/dev/null | openssl x509 -noout -subject -issuer
```

Configure trusted DNS servers in `/etc/docker/daemon.json` while preserving any existing settings:

```json
{
  "dns": ["1.1.1.1", "8.8.8.8"]
}
```

Then restart Docker and retry:

```bash
sudo systemctl restart docker
docker compose pull
docker compose build --pull
```

If the host itself still resolves the wrong IP, also correct the Linux/network-manager DNS configuration or the router DNS settings and flush the resolver cache.

## Architecture

### Data flow

1. Public event controllers query events and event-scoped competitions. The same views add management actions for administrators.
2. Admin requests validate input before saving events or competition metadata. Competition controllers create standard or advanced stage blueprints within database transactions.
3. `TournamentLifecycleService` seeds entrants, asks `BracketGenerator` for a complete graph, assigns stable database match IDs, and persists both outgoing winner/loser destinations and incoming participant-source references.
4. `MatchResultService` records scores and propagates winner/loser IDs atomically, recomputes standings, and starts the next playable match when no match is live. Corrections cannot change a winner once an affected downstream match has started. A playable match may therefore be READY **or LIVE**; consumers must handle both.
5. Bracket pages load every match, group them by stage/group and bracket type, and position equal-sized cards in centered round columns, with connectors following their actual dependency edges. Live-state checks refresh displayed results. Ranking competitions use standings instead of an empty bracket.

### Bracket principles

- Single elimination pads the seed field to the next power of two. The graph contains `size - 1` nodes, of which `size - N` are first-round byes; exactly `N - 1` games are played. Bye winners advance without a score.
- Retained placeholder bye nodes keep their outgoing edges. When group qualifiers populate the playoff skeleton, bye winners receive real participant IDs as well as display labels.
- Third-place games require two real semifinal losers. Three entrants have only one such loser, so no unplayable bronze game is created.
- Double elimination omits bye nodes after copying their automatic advances and builds a compact upper/lower graph with `2N - 2` base games. Every upper-bracket loser has a lower path, ending at the grand final. With reset mode enabled, a lower-bracket win in the first final adds one game (`2N - 1` total).
- Round robin schedules each unordered pair exactly once (`N(N - 1)/2`). Ranking uses attempt records, not match nodes.
- The connected layout uses 360px-wide cards and a shared height measured to fit the fullest card. Rounds share a centerline with 160px between columns and 64px between cards. High-contrast 2px silver connectors join the centers of card edges; skipped rounds use separate lanes below the cards. The tree remains connected on phones with scrolling inside keyboard-accessible bracket regions and optional zoom; names wrap without truncation.

### Frontend layout and styling

- `resources/css/app.css` is the Vite entry point. `ui.css` contains shared primitives and feature components; `responsive.css` owns the final palette, spacing, interaction states, and adaptive layouts. Do not add another theme layer or duplicate styles in Blade. The no-build fallback reads these shared styles plus bracket and motion styles.
- Grids use shrinkable tracks, long names wrap, navigation follows document flow, and card reorder controls have their own row. At narrow widths, labeled table cells become cards; bracket diagrams can be scrolled at full size or zoomed out. Native tables remain available to assistive technology.
- `resources/js/responsive.js` associates legacy field labels and prepares mobile table labels, including after live updates. Dropdowns retain keyboard selection, Escape handling, viewport bounds, and visible focus. Reduced-motion preferences disable animation.
- Browser regression check: seed a **disposable** local database with `php artisan migrate --seed`, serve the app, and launch a separate headless Chromium/Brave profile with remote debugging on port 9333. With Node 22+, run `UI_BASE_URL=http://127.0.0.1:8097 node tests/browser/responsive.mjs`. The check navigates demo events, competitions, brackets, results, forms, and documentation at 320, 390, 768, and 1366 pixels, failing on page overflow, overlapping match nodes, or JavaScript exceptions.
- Set `UI_ADMIN_EMAIL` and `UI_ADMIN_PASSWORD` to a disposable test admin to include management pages. `UI_EXTRA_ADMIN_PATHS` accepts a JSON array of paths for seeded ranking/group fixtures; `UI_REPORT_PATH` optionally saves the detailed report. This check clears cookies in the dedicated browser. Never point it at your personal browser profile or use production seeding.

### Validation and deployment

Run `php artisan test`, `vendor/bin/pint --test`, and `npm run build`. Tests use an isolated in-memory SQLite database. `BracketCompletenessTest` plays through multiple power-of-two and uneven fields through completion, including reset finals. `EventHierarchyTest` exercises public browsing, scoped API boundaries, validation, and protected mutations.

Before deploying in Atomhost/Plesk, back up the production database and use the deployment commands above on the intended Git revision. Verify `/events`, an existing event and bracket, `/api/events`, and anonymous write rejection after migration. Do not reset or regenerate live brackets during deployment. Code changes affect newly generated graphs; historical match data is preserved. A database rollback removes event grouping metadata, so keep the backup if rollback is necessary.

- Schema migration: `database/migrations/2026_08_21_100000_create_external_tournament_tables.php`
- Enums: `app/Enums`
- Tournament graph generator: `app/Services/BracketGenerator.php`
- Atomic result propagation: `app/Services/MatchResultService.php`
- Lifecycle: `app/Services/TournamentLifecycleService.php`
- Event model and controllers: `app/Models/Event.php`, `app/Http/Controllers/EventController.php`, `app/Http/Controllers/Api/EventController.php`
- Ranking and Round Robin standings: `app/Services/RankingService.php`, `app/Services/MatchStandingsService.php`
- Demo data: `database/seeders/DatabaseSeeder.php`

### Participant search

Event detail pages offer a **Participant or team** filter alongside competition text and status filters. The API supports the same optional `participant` parameter on `GET /api/events/{event}/competitions` and `GET /api/tournaments` (up to 100 characters). It matches partial team names and individual member names, treats `%` and `_` literally, and preserves filters in pagination links.

Bracket search highlights matching participant slots within the selected bracket view. **Next match** or Enter moves through matches; **Clear** or Escape removes highlights. Search remains active after live updates. Shared UI motion respects the operating system's reduced-motion preference.

With the disposable app and dedicated browser described above running, use `node tests/browser/search.mjs` to verify search, live-refresh highlights, navigation, mobile layout, and reduced motion.

Run `node tests/browser/bracket.mjs` against the disposable app/browser to check dimensions, spacing, connector endpoints, long names, and zoom.
