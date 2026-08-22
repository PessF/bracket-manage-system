# EasyKids Competition Tournament Management System

Laravel 12 + PHP 8.3 + MySQL 8 implementation of the EasyKids tournament engine. It supports Ranking, Round Robin, Single Elimination, and Double Elimination, including BYEs, winner/loser dependency propagation, a runtime Grand Final reset, ranking attempts, and Round Robin standings.

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

Viewer accounts and public visitors can see only competitions with `LIVE` status. Their tournament lists, brackets, matches, participants, and standings are read-only. Draft, ready, completed, and archived competitions return a not-found response unless opened by an administrator. Administrators sign in to create and configure tournaments, manage users and participants, import CSV files, control tournament status, and record results.

Each competition has a private viewer link in its administrator overview. This `/view/{token}` URL opens only that competition, preserves the read-only mode across Overview, Bracket, Matches, and Results, and is available only while the competition is `LIVE`. It can be copied before starting so it is ready to distribute when the competition goes live.

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

The importer also accepts `Team` as the team-name header and Thai headers such as `ชื่อทีม`, `รหัสทีม`, `โรงเรียน`, `โค้ช`, and `สมาชิก 1`–`สมาชิก 4`. Imports are limited to 1,000 data rows; invalid and duplicate rows are reported while valid rows are imported transactionally.

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

Public read endpoints include health plus live tournaments, participants, matches, and standings. Supplying an administrator bearer token also allows GET requests to read non-live competitions. Administrator write endpoints cover tournament CRUD, participant CRUD and CSV import, lifecycle changes, match results, and ranking attempts. Create or revoke a bearer token from **API access** in the administrator navigation. API messages can be selected with `?lang=th` / `?lang=en` or the `Accept-Language` header.

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

- Schema migration: `database/migrations/2026_08_21_100000_create_external_tournament_tables.php`
- Enums: `app/Enums`
- Tournament graph generator: `app/Services/BracketGenerator.php`
- Atomic result propagation: `app/Services/MatchResultService.php`
- Lifecycle: `app/Services/TournamentLifecycleService.php`
- Ranking and Round Robin standings: `app/Services/RankingService.php`, `app/Services/RoundRobinStandingsService.php`
- Demo data: `database/seeders/DatabaseSeeder.php`
