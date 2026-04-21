# Epicollect5 Server

Modern deployment of the Epicollect5 backend is a Laravel 12 (PHP 8.3+) application that exposes both REST APIs for
mobile clients and the web interface.

## Quick context

- **Framework**: Laravel 12.1.1 with `ec5\` namespace rooted in `app/`.
- **Key concerns**: custom services in `app/Services/`, DTOs (`app/DTO/`), and generator utilities (
  `app/Libraries/Generators/`).
- **Frontend assets**: managed via Gulp (see `gulpfile.js`, `package.json`).
- **CLI tooling**: PHPUnit (`vendor/bin/phpunit --no-progress`), formatting using Laravel Pint - psr12
- **Deployment helpers**: `deploy.php`, `after_pull-dev.sh`, `after_pull-prod.sh`, `laravel_storage_folders.sh`.

## Requirements

- PHP >= 8.3 with extensions `json`, `pdo`, `zlib`, `zip`, `fileinfo`, `posix`, `openssl`, `mbstring`, `simdjson`,
  `ldap` (if uploading media metadata). Install PHP via your preferred package manager or Docker container that matches
  the version in `composer.json`.
- **Media tooling**: install FFmpeg (required for `pbmedia/laravel-ffmpeg` + `php-ffmpeg/php-ffmpeg`) and ImageMagick
  (`imagick` with `intervention/image`) so audio/video compression and image manipulations run without errors.
- MySQL 8+ (or compatible) for storing project metadata and entries.
- Composer (no specific version; repo uses modern Laravel installer scripts).
- Node.js + npm/yarn for asset builds (Gulp, Sass, etc.).
- Optional: `dep` if you use the PHP Deployer `deploy.php` script for production releases.
- Swap file (4GB) recommended for high usage servers

## Setup and local workflow

1. Clone the repo and install PHP dependencies: `composer install` (scripts will copy `.env.example` -> `.env` and
   generate the app key).
2. Install frontend tooling: `npm install` (or `yarn`), then build assets with `npm run prod` for production or
   `npm run dev` for watch mode.
3. Configure `.env` (database credentials, mail, storage drivers). Run `php artisan storage:link` to expose
   `storage/app/public` via `public/storage`.
4. Run database migrations and seeders: `php artisan migrate --seed` (adjust env and database as needed).
5. Start the dev server: `php artisan serve --host=0.0.0.0` or use your preferred web server pointing at `public/`.

## Testing and quality gates

- PHPUnit: `vendor/bin/phpunit --no-progress` (configured via `phpunit.xml`).
- Static analysis: `vendor/bin/phpstan analyse --no-progress --error-format=raw`,
  `vendor/bin/psalm --no-progress --no-suggestions --output-format=text`.
- Coding standards: `vendor/bin/phpcs --report=emacs -q`, `vendor/bin/php-cs-fixer fix --show-progress=none -q -n`.
- Rector: `vendor/bin/rector process --no-progress-bar --output-format=github`.
  See `AGENTS.md` for context on each tooling command.

## Composer scripts

- `post-root-package-install`: copies `.env.example` to `.env`.
- `post-create-project-cmd`: runs `php artisan key:generate`.
- `post-install-cmd`: runs Laravel’s post install hooks.
- `post-update-cmd`: runs Laravel’s post update hooks plus IDE helper generators.
- `test`: runs `vendor/bin/phpunit`.

## Storage and deploy helpers

- Shared deployment scripts (`after_pull-dev.sh`, `after_pull-prod.sh`, `laravel_storage_folders.sh`) ensure migrations,
  cache clearing, and storage permissions after deployments.
- `deploy.php` encapsulates Deployer configuration (set up `dep` separately if required).
- Storage uploads should go through configured disks (local or S3). Check `config/filesystems.php` for disk aliases.

## Notes

- Legacy LAMP notes have been replaced by Laravel-centric tooling. For previous PHP 7.1 instructions see the project
  history.
- If you run into PHP extension issues, install the missing ones listed in `composer.json`.

## External builds sources

- Formbuilder https://github.com/epicollect5/epicollect5-formbuilder
- Dataviewer https://github.com/epicollect5/epicollect5-dataviewer
- Data Editor (legacy) https://github.com/epicollect5/epicollect5-data-editor

## Docs

User Guide https://docs.epicollect.net/
Developer Guide https://developers.epicollect.net/
