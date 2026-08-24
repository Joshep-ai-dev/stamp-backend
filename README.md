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
php artisan cities:import reference/world-cities.csv --dataset-version=reference
php artisan serve
```

The API base URL is `http://localhost:8000/api/v1`. Set the Expo client to:

```env
EXPO_PUBLIC_API_URL=http://localhost:8000/api/v1
```

Use `10.0.2.2` instead of `localhost` from the Android emulator.

## Docker

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan cities:import reference/world-cities.csv --dataset-version=reference
```

The PostgreSQL port is intentionally not published. Change all default passwords before deploying.

## Catalog imports

The CSV must remain under private storage, never `public/`. The importer validates the exact header, streams rows, normalizes search text, upserts in 750-row transactions, records its SHA-256 checksum and version, and skips an already imported checksum.

```bash
php artisan cities:import reference/world-cities.csv --dataset-version=reference
php artisan cities:import reference/world-cities.csv --dataset-version=reference --force
```

`--prune` removes absent cities and is intentionally explicit. Back up PostgreSQL first. `--max-rejected=N` controls the rejected-row failure threshold (default `0`).

## Verification

```bash
php artisan test
vendor/bin/pint --test
php artisan route:list --path=api/v1
```

API validation errors use Laravel's standard 422 shape. Unexpected production exceptions return a generic message plus a request ID, also exposed in the `X-Request-Id` header.
