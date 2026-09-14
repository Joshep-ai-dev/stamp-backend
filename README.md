# Stampo Backend

Production-oriented Laravel 13 and PostgreSQL API for the Stampo mobile client. It implements bearer-token authentication with Sanctum, profiles, ownership-scoped visits, travel state, collections, the home dashboard, and an indexed city/country catalog imported from the supplied CSV.

## Requirements

- PHP 8.3–8.5 with `intl`, `mbstring`, `pdo_pgsql`, and `zip`
- Composer 2
- PostgreSQL 14+ with permission to enable `pg_trgm`
- Or Docker Compose

## Local setup

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan cities:import storage/app/imports/world-cities.csv --dataset-version=reference
php artisan serve
```

The API base URL is `http://localhost:8000/api/v1`. Set the Expo client to:

```env
EXPO_PUBLIC_API_URL=http://localhost:8000/api/v1
```

Use `10.0.2.2` instead of `localhost` from the Android emulator.

## First member and initial invitation

After configuring and migrating the database used by the app, run:

```bash
php artisan kroo:founding-member founder@example.com --name="Your Name"
```

Replace the example email with an address you control. The command prints a generated
referral code. Enter it on the app's welcome screen and tap Continue. You can then
sign in to the pre-created account using the same email and an email verification
code; email delivery must be configured. Do not select Create Account for this email.

The command does not require an existing inviter. It is available only through the
backend console. If the email already belongs to a member, it preserves their profile
and password and retrieves their existing code, generating one only if needed.
Run the command again with the same email to retrieve the code later.

## Docker

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan cities:import storage/app/imports/world-cities.csv --dataset-version=reference
```

The PostgreSQL port is intentionally not published. Change all default passwords before deploying.

## Catalog imports

### Collection access and US state checklist

Run `php artisan migrate` after deploying collection access support. In Admin >
Collections, set **Available to** to **Everyone** or **Kroo+ only**. Existing
collections default to Everyone. A Kroo+ collection restricts every place in it;
an Everyone collection still respects any existing Kroo+ restrictions on its
individual places. Non-members can see collection previews, but cannot read the
restricted place details or record their completion through the API.

The US state checklist can be completed from the imported US city catalog:

```bash
php artisan collections:complete-us-states usa
php artisan collections:complete-us-states usa --apply
```

The first command previews missing states. The second adds them without changing
existing entry IDs or saved visits. The command stops without writing if a state
has no matching catalog city. Re-running it does not duplicate entries. Completing
the checklist changes its denominator to 50, so existing percentage values may fall
while the same visited states remain recorded.

The CSV must remain under private storage, never `public/`. The importer validates the exact header, streams rows, normalizes search text, upserts in 750-row transactions, records its SHA-256 checksum and version, and skips an already imported checksum.

```bash
php artisan cities:import storage/app/imports/world-cities.csv --dataset-version=reference
php artisan cities:import storage/app/imports/world-cities.csv --dataset-version=reference --force
```

`--prune` removes absent cities and is intentionally explicit. Back up PostgreSQL first. `--max-rejected=N` controls the rejected-row failure threshold (default `0`).

## Verification

```bash
php artisan test
vendor/bin/pint --test
php artisan route:list --path=api/v1
```

API validation errors use Laravel's standard 422 shape. Unexpected production exceptions return a generic message plus a request ID, also exposed in the `X-Request-Id` header.
