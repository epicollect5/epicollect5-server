# Epicollect5 Server - AI Agent Guide

## Big Picture Architecture

- **Framework**: Laravel-based PHP 8.3+ application for the Epicollect5 mobile and web platform.
- **Namespacing**: Uses `ec5\` as the root namespace instead of the default `App\`.
- **Core Entities**:
    - `Project`: Defines the data structure (forms, inputs) and access levels.
    - `Entry`: Data submitted by users, stored as JSON in `entry_data` columns but often indexed/cached.
    - `ProjectStructure`: Contains the JSON definition, "extra" (parsed) structure, and mappings.
- **Service Layer**: Business logic is encapsulated in `app/Services/`. Controllers should remain thin, delegating to
  services (e.g., `CreateEntryService`, `ProjectExtraService`).
- **Data Transfer Objects (DTOs)**: Used extensively in `app/DTO/` to pass structured project and entry data between
  layers.

## Critical Developer Workflows

- **Testing**: PHPUnit tests are located in `tests/`. Database tests typically use `DatabaseTransactions`.
    - Command: `php artisan test` or `./vendor/bin/phpunit`
- **Generators**: Use `ec5\Libraries\Generators\` (e.g., `ProjectDefinitionGenerator`, `EntryGenerator`) to create mock
  data for tests.
- **Deployment**: `after_pull-dev.sh` and `after_pull-prod.sh` manage post-deployment tasks like migrations and cache
  clearing.

## Project-Specific Conventions

- **Trait-Heavy Logic**: Shared functionality is often found in `app/Traits/` (e.g., `ec5\Traits\Eloquent\Entries` for
  entry database operations).
- **Configuration**: Domain-specific config is under `config/epicollect/` (e.g., `limits.php`, `codes.php`,
  `tables.php`). Always reference these instead of hardcoding values.
- **Error Handling**: Uses custom error codes defined in `config/epicollect/codes.php`.
- **Front-end**: Public assets and views are in `public/` and `resources/views/`. Uses Gulp for asset management (
  `gulpfile.js`).

## Integration & Communication

- **API Strategy**:
    - `routes/api_external.php`: Endpoints for mobile apps and external consumers (uses `ec5\Http\Controllers\Api\`).
    - `routes/api_internal.php`: Used by the web front-end.
- **Storage**: Supports Local and S3 storage. Check `AppServiceProvider` for environment-specific bucket safety checks.
- **Authentication**: Supports Passwordless (email-based), Local, Google, and Apple login.

## Key Directories

- `app/DTO/`: Data contracts used across the application.
- `app/Services/`: Core business logic (Project management, Entry processing).
- `app/Libraries/`: Non-service utility classes and generators.
- `database/migrations/`: Database schema definitions.
- `config/epicollect/`: The "Source of Truth" for system limits and strings.

