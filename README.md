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

## Deployment

Production deployments use [Deployer](https://deployer.org/) via `deploy.php`. Three environments are defined:

- **production** — branch `master`, deploy path `/var/www/html_prod`
- **dev** — branch `dev`, deploy path `/var/www/html_prod`
- **staging** — branch `staging`, deploy path `/var/www/html_prod`

### Prerequisites

- Install `dep` (Deployer 7.x): `composer global require deployer/deployer`
- The deploy user must not be root (enforced by `deploy.php`)

### Fresh install

```bash
dep install production
```

This runs the full setup pipeline:

1. Checks for a clean install (aborts if `/current` symlink exists)
2. Pulls code and installs Composer dependencies
3. Creates the MySQL database and user
4. Copies `.env.example` → `.env` and fills in DB credentials
5. Generates the app key
6. Creates the `public/storage` symlink
7. Generates Passport OAuth keys
8. Prompts for superadmin credentials (email, name, password)
9. Prompts for a system alert email
10. Runs database migrations
11. Sets file permissions on `.env`, API keys, and bash scripts
12. Runs `artisan system:stats --deployer`

### Updates

```bash
dep update production
```

This runs the update pipeline:

1. Puts the app in maintenance mode (`artisan down`)
2. Pulls code and installs Composer dependencies
3. Runs database migrations
4. Caches config, routes, and views
5. Sets file permissions on `.env`, API keys, and bash scripts
6. Dumps Composer autoload
7. Runs `artisan about`

**Important:** After `dep update`, the app remains in maintenance mode. You must verify the release works, then bring it back online manually:

```bash
php artisan up
```

If the deploy fails, the lock is automatically released but the app stays down until you investigate.

### Post-pull scripts

After every deploy (including `dep update`), run the appropriate cache-clearing script **manually** after updating `.env` with new keys and the updated `RELEASE` number:

```bash
# Production
./after_pull-prod.sh

# Dev
./after_pull-dev.sh
```

These scripts cannot be automated as part of the Deployer pipeline because they require `.env` changes first. The scripts clear config, route, view, and OPcache, then re-cache config. The prod script also refreshes the homepage cache; the dev script additionally cleans `bootstrap/cache/*.php`.

### Storage setup (deprecated)

`laravel_storage_folders.sh` creates the full storage directory tree (`app/entries/{audio,photo,video}`, `app/projects/project_thumb`, etc.) with correct permissions. This was used when `storage/` lived on a separate volume — after cloning a droplet, the script could rebuild the folder structure on the clone without copying the large media volume.

The architecture has since moved to a single droplet with S3 for media storage. The script is kept for reference but is no longer part of the standard deployment workflow.

### Deployment helpers

| File | Purpose |
|---|---|
| `deploy.php` | Deployer configuration: `install` and `update` tasks, environment definitions, permissions, DB setup |
| `after_pull-prod.sh` | Post-pull cache clearing for production |
| `after_pull-dev.sh` | Post-pull cache clearing for dev |
| `laravel_storage_folders.sh` | Storage directory structure builder (deprecated) |

### Media storage

Storage uploads should go through configured disks (local or S3). Check `config/filesystems.php` for disk aliases.

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
