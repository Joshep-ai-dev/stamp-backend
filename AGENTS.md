# Repository Guidelines

## Project Structure & Module Organization

This repository is a Laravel 13 API requiring PHP 8.3+. Application code lives in `app/`, with controllers in `app/Http/Controllers`, domain services in `app/Services`, models in `app/Models`, and console commands in `app/Console/Commands`. API and web routes are defined in `routes/`. Database migrations, factories, and seeders are under `database/`. Frontend/Vite resources are in `resources/`, and public entry-point assets are in `public/`. Tests mirror the application concerns under `tests/Unit` and `tests/Feature` (especially `tests/Feature/Api`). Private imports belong under `storage/`, not `public/`.

## Build, Test, and Development Commands

Run commands from `laravel_app/`:

- `composer install` installs PHP dependencies.
- `npm install` and `npm run build` install and compile Vite assets.
- `composer run setup` performs initial dependency, environment, key, migration, and asset setup.
- `composer run dev` starts the Laravel server, queue listener, log viewer, and Vite watcher.
- `php artisan migrate` applies database migrations; `php artisan serve` starts the API locally.
- `php artisan test` or `composer test` runs the PHPUnit suite.
- `vendor/bin/pint --test` checks Laravel coding style; run `vendor/bin/pint` to apply fixes.

## Coding Style & Naming Conventions

Follow `.editorconfig`: four spaces, UTF-8, LF line endings, and no trailing whitespace. Use PSR-4 namespaces and Laravel conventions: `PascalCase` classes, `camelCase` methods and variables, and descriptive `*Controller`, `*Service`, `*Test`, and `*Command` names. Prefer small, focused services and Form Requests for validation. Keep API behavior consistent with existing `/api/v1` routes.

## Testing Guidelines

Tests use PHPUnit 12 through Laravel's test runner. Name files after the subject with a `Test` suffix, and group endpoint behavior in `tests/Feature/Api`. Tests default to an in-memory SQLite database; avoid relying on local PostgreSQL state. Run targeted tests with `php artisan test tests/Feature/Api/AirportLookupTest.php`, then run the full suite before submitting changes.

## Commits and Pull Requests

Recent commits use short, imperative summaries (for example, `fix subscribe bug`); keep messages concise and focused on one change. Pull requests should explain the behavior changed, include relevant tests and migration/configuration notes, and identify any API contract changes. Include request/response examples or screenshots when they clarify a client-facing change. Never commit `.env` values, credentials, or private imported data.

## Security & Configuration

Copy `.env.example` to `.env` for local setup and keep secrets out of source control. Review authentication, ownership scoping, and request validation when changing API endpoints. For production, use PostgreSQL, change default credentials, and back up the database before destructive imports or pruning.
