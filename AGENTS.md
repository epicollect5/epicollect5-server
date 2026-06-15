# Epicollect5 Server - AI Agent Guide

## Big Picture Architecture

- **Framework**: Laravel 13 on PHP 8.3+ for the Epicollect5 mobile and web platform.
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

- **Testing**: PHPUnit tests are located in `tests/`. Most database tests use the `DatabaseTransactions`
  trait. Integration and controller tests may use `clearDatabase()` from `tests/TestCase.php` instead.
  See existing tests for the current pattern.
    - Command: `vendor/bin/phpunit --no-progress`
- **Generators**: Use `ec5\Libraries\Generators\` (e.g., `ProjectDefinitionGenerator`, `EntryGenerator`) to create mock
  data for tests.
- **Deployment**: `deploy.php` manages full production deployments via Deployer (including migrations).
  `after_pull-dev.sh` and `after_pull-prod.sh` clear caches after pulling changes.
- **Setup**: Follow `README.md` for environment requirements and local setup instructions.

## Project-Specific Conventions

- **Trait-Heavy Logic**: Shared functionality is often found in `app/Traits/` (e.g., `ec5\Traits\Eloquent\Entries` for
  entry database operations).
- **Configuration**: Domain-specific config is under `config/epicollect/` (e.g., `limits.php`, `codes.php`,
  `tables.php`). Always reference these instead of hardcoding values.
- **Domain Enums**: Refer to `config/epicollect/strings.php` for project roles, access levels, statuses,
  and input types. Refer to `config/epicollect/permissions.php` for role hierarchy and management rules.
- **Error Handling**: Uses custom error codes defined in `config/epicollect/codes.php`.
- **Front-end**: Public assets and views are in `public/` and `resources/views/`. Uses Gulp for asset management (
  `gulpfile.js`).
- **Conventions**: Use `camelCase` for variable names, `snake_case` for configuration keys, JSON keys, database columns.
- **Code Style**: Use PSR-12 (and Laravel Pint)
- **Naming**: Do NOT prefix private/protected methods or properties with `_`
- **Typed Properties**: Use typed properties on classes. Eloquent model boilerplate (`$fillable`, `$casts`,
  `$table`) may remain untyped.
- **Return Types**: Declare explicit return types on all methods, including `void`.
- **Early Returns**: Prefer early returns over nested if/else. Handle error conditions first, success last.
- **Docblocks**: Do not add docblocks when the method signature already conveys the information. Use docblocks
  only to explain purpose, not to restate types.
- **Blade Templates**: Indent with 4 spaces. No space after Blade control structures: `@if($condition)`,
  not `@if ($condition)`.

## Integration & Communication

- **API Strategy**:
    - `routes/api_external.php`: Endpoints for mobile apps and external consumers (uses `ec5\Http\Controllers\Api\`).
    - `routes/api_internal.php`: Used by the web front-end.
    - Route URLs use kebab-case: `download-entries`, `project-users`, `verify-google`.
- **Storage**: Supports Local and S3 storage. Check `AppServiceProvider` for environment-specific bucket safety checks.
- **Authentication**: Supports Passwordless (email-based), Local, Google, and Apple login.

## Key Directories

- `app/DTO/`: Data contracts used across the application.
- `app/Services/`: Core business logic (Project management, Entry processing).
- `app/Libraries/`: Non-service utility classes and generators.
- `database/migrations/`: Database schema definitions.
- `config/epicollect/`: The "Source of Truth" for system limits and strings.

## Common Tasks

### Adding a new API endpoint

1. Define the route in `routes/api_external.php` or `routes/api_internal.php`.
2. Create a controller in `app/Http/Controllers/Api/` (keep it thin — delegate to services).
3. Add validation rules in `app/Http/Validation/` if needed.
4. Create or reuse a service in `app/Services/` for business logic.
5. Use DTOs from `app/DTO/` to pass structured data between layers.
6. Add error codes to `config/epicollect/codes.php` if introducing new error conditions.
7. Write tests in `tests/Routes/` or `tests/Services/`.

### Adding a migration

1. Create a migration file in `database/migrations/` with a timestamp prefix.
2. Update `docs/database-schema.md` to reflect the new schema.
3. Use `config/epicollect/tables.php` for table name references.

### Writing a new test

1. Extend `Tests\TestCase` (defined in `tests/TestCase.php`).
2. Use `DatabaseTransactions` trait for database tests (the dominant pattern).
3. Use `clearDatabase()` with specific params for tests that need explicit cleanup (e.g. sequence tests without transactions, or to clear pre-existing artifacts in setUp).
4. Use model factories from `ec5\Libraries\Generators\` for test data.
5. Run with: `vendor/bin/phpunit --no-progress tests/Path/To/YourTest.php`

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

## Release candidate reviews

- Follow docs/release-review.md

## Diff-First Intelligence (MANDATORY)

When analyzing code, generating reviews, or producing QA, always prioritize context in this order:

1. **git diff** vs base branch (primary source of truth)
2. **Modified file sections** (diff hunks)
3. **Minimal surrounding code context** (±20–50 lines if needed for clarity)
4. **Full file or repository analysis** (last resort only, if the diff is insufficient to understand behavior)

Strict rules:

- Never analyze unchanged parts of the repository unless required to resolve ambiguity in the diff.
- Never infer features, bugs, or changes not explicitly present in the diff.
- Avoid full-repository scanning unless absolutely necessary.
- Always anchor conclusions to observable changes in the diff.

## AI Workflows

This section routes agents to the canonical workflow definitions.

- **Code Review** → `docs/workflows/review.md`
- **QA Generation** → `docs/workflows/qa.md`

Agents must follow these workflow definitions when executing tasks.

## Restrictions

- **Tinker is strictly prohibited:** No AI agent must use, invoke, or interact with Tinker in any form during
  autonomous actions, suggestions, or when generating code within this repository.

## Running Tools

Always use these flags when running CLI tools:

- Tests: `vendor/bin/phpunit --no-progress`
- Code style: `vendor/bin/pint` (PSR-12 preset, configured in `pint.json`)

## Branching and Commit Conventions

- **Branch naming**: Use `type/short-description` with kebab-case: `feature/add-export-filters`,
  `fix/null-pointer-on-upload`, `hotfix/slow-admin-query`.
- **Branch types**: `feature/`, `fix/`, `hotfix/`, `migration/`, `admin/`.
- **Commit messages**: Use Conventional Commits format: `feat: add entry export filters`,
  `fix: handle null project mapping`, `test: cover edge case in upload validation`.
- **Environments**: `dev` → `staging` → `production` (promotion order). `master` is the canonical branch.
- **PRs**: Keep PRs focused on a single change. Run `vendor/bin/phpunit --no-progress` (targeted) before submitting.

## Test Suite

- The full test suite takes over 1 hour to complete.
- NEVER run `php artisan test` or `vendor/bin/phpunit` with no filter — it runs the full suite, UNLESS specified.
- NEVER run tests as a baseline check or between upgrade steps.
- ALWAYS run targeted tests after modifying a file, using the file path as filter:
  vendor/bin/phpunit --no-progress tests/Services/Project/ProjectExtraServiceTest.php
- If you need to verify the app boots without running tests, use `php artisan about`.
- Only run the full suite if the user explicitly types "run the full test suite" in the chat.

## Commands That Are Never Run Autonomously

- `php artisan test` (no filter) — suite takes 1+ hour
- `vendor/bin/phpunit` (no filter) — suite takes 1+ hour
- `php artisan migrate:fresh` — destroys local data
- `php artisan migrate:reset` — destroys local data
- `php artisan db:wipe` — destroys local data
- `php artisan vendor:publish --tag=*-migrations` — creates duplicate migrations
- `composer update` without showing a diff first


