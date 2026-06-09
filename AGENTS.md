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
- Use docs/architecture.md as the source of truth for server architecture
- Use docs/database-schema.md as the source of truth for database structure

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
- **Conventions**: Use `camelCase` for variable names, `snake_case` for configuration keys, JSON keys, database columns.
- **Code Style**: Use PSR-12 (and Laravel Pint)
- **Naming**: Do NOT prefix private/protected methods or properties with `_`

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
- Formatting using Laravel Pint - psr12
- Do NOT prefix private/protected methods with `_`

## Release candidate reviews

- Follow docs/release-review.md

## Restrictions

- **Tinker is strictly prohibited:** Copilot Agents must not use, invoke, or interact with Tinker in any form during
  autonomous actions, suggestions, or when generating code within this repository.

## Running Tools

Always use these flags when running CLI tools:

- Tests: `vendor/bin/phpunit --no-progress`
- phpcs: `vendor/bin/phpcs --report=emacs -q`

## Test Suite

- The full test suite takes over 1 hour to complete.
- NEVER run `php artisan test` or `./vendor/bin/pest` with no filter — it runs the full suite, UNLESS specified.
- NEVER run tests as a baseline check or between upgrade steps.
- ALWAYS run targeted tests after modifying a file, using the file path as filter:
  php artisan test tests/Unit/SomeTest.php
  ./vendor/bin/pest tests/Unit/SomeTest.php
- If you need to verify the app boots without running tests, use `php artisan about`.
- Only run the full suite if the user explicitly types "run the full test suite" in the chat.

## Commands That Are Never Run Autonomously

- `php artisan test` (no filter) — suite takes 1+ hour
- `./vendor/bin/pest` (no filter) — suite takes 1+ hour
- `php artisan migrate:fresh` — destroys local data
- `php artisan migrate:reset` — destroys local data
- `php artisan db:wipe` — destroys local data
- `php artisan vendor:publish --tag=*-migrations` — creates duplicate migrations
- `composer update` without showing a diff first

## /qa

**Purpose:** Generate QA documentation from a codebase change or QA spec file.

**Triggers:** `/qa` · "generate qa docs" · "qa checklist" · "qa summary" · "create qa report"

**Inputs (optional):**

- `path` — QA spec file (default: `docs/QA-{version}.md`)
- `diff` — git diff (if available; takes priority over spec)

---

### Pipeline

**1. Load Context**
Read QA spec from path and/or git diff. If both exist, lead with diff and reconcile against spec.

**2. Analyze Changes**
Identify: modified features, new endpoints/controllers, migrations, auth/permissions, frontend changes, API contract
changes.

**3. Generate QA Checks**
For each change produce:

- **Test Description** — what is being tested
- **Expected Result** — what success looks like
- **Manual Action** — concrete step-by-step staging instructions

Rules: every change needs at least one check; steps must be manually executable in staging; no abstract descriptions.

**4. Generate CSV**
Columns: `Test Description` | `Expected` | `Manual Action` — one row per check.

**5. Validate**
No uncovered changes · no duplicates · all steps reproducible in staging.

**6. Output**

1. QA Markdown report
2. CSV content block (copy/download ready)
