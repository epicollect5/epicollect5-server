---
apply: always
---

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

## PHP style: string interpolation

When writing PHP strings:

- **Do not use curly braces for simple variables** inside double-quoted strings.
    - Prefer: `"Expected an index on $entriesTable ..."`
    - Avoid: `"Expected an index on {$entriesTable} ..."`

- Use curly braces **only when required** to disambiguate complex expressions or adjacent characters (property/array
  access, method calls, or when immediately followed by letters/numbers/underscore).
    - Examples where braces may be needed:
        - `"Hello {$user->name}"`
        - `"Value: {$arr['key']}"`
        - `"table_${suffix}"` (or `"table_{$suffix}"` if needed for clarity)

- If the string contains mixed dynamic parts and reads better, prefer **explicit concatenation**:
    - `"Expected an index on " . $entriesTable . " covering ... Available indexes: " . json_encode($indexes)`

## Restrictions

- **Tinker is strictly prohibited:** Copilot Agents must not use, invoke, or interact with Tinker in any form during
  autonomous actions, suggestions, or when generating code within this repository.

## Running Tools

Always use these flags when running CLI tools:

- Tests: `vendor/bin/phpunit --no-progress`
- PHPStan: `vendor/bin/phpstan analyse --no-progress --error-format=raw`
- Psalm: `vendor/bin/psalm --no-progress --no-suggestions --output-format=text`
- phpcs: `vendor/bin/phpcs --report=emacs -q`
- PHP-CS-Fixer: `vendor/bin/php-cs-fixer fix --show-progress=none -q -n`
- Rector: `vendor/bin/rector process --no-progress-bar --output-format=github`Copy

# Writing Docs

- Write schema docs into plain Markdown sections and bullet lists so it renders consistently in the docs' viewer.
