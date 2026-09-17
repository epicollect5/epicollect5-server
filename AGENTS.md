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

- PHP/Laravel style is defined in `docs/code-style.md` — follow it for every PHP change.
  If generic PHP/Laravel guidance conflicts with that file, the file wins.
- **Trait-Heavy Logic**: Shared functionality is often found in `app/Traits/` (e.g., `ec5\Traits\Eloquent\Entries` for
  entry database operations).
- **Front-end**: Public assets and views are in `public/` and `resources/views/`. Uses Gulp for asset management (
  `gulpfile.js`).

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

Follow `docs/code-style.md` (Strings section) for PHP string interpolation rules.

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

## Repository Skills

Repository skills are the operational instructions for how agents plan and implement work in
this repo. They are shared across every coding agent (Codex, OpenCode, CommandCode, JetBrains AI).

### Where skills live

- **Canonical skills**: `.agents/skills/<skill-name>/SKILL.md` — the single source of truth.
  Always author and edit skill content here, never anywhere else. This is the
  [Agent Skills](https://agentskills.io) standard, discovered natively by Codex
  (`$<name>` / `/skills`), OpenCode (skill tool), CommandCode (`/<name>`), and
  JetBrains AI shared skills (`+ > Skills` import).
- **Agent integration**: `.opencode/commands/<skill-name>.md` are thin wrappers
  (frontmatter `description` + `agent: build`, body forwards to the canonical file
  and passes `$ARGUMENTS` through verbatim). Never duplicate skill content in wrappers.

### Available skills

- `plan-review` — before any non-trivial plan or implementation: identify the real
  goal, avoid blindly following the proposed implementation, find existing patterns, evaluate
  simpler alternatives, recommend the simplest solution, then plan.
- `issue` — draft the current plan as a GitHub-issue-ready document, saved to `docs/issues/draft/`.
- `publish-issue` — publish a drafted issue from `docs/issues/draft/` to GitHub via `gh` (confirmation required).
- `qa` — generate QA documentation from a codebase change or QA spec file.
- `review` — review local changes against the repository base branch.
- `commit` — create Conventional Commits from changes, splitting by type and scope.
- `false-positive` — mark a review finding as false positive with a code comment and reason.

### Requirements

- Consult the relevant skill(s) when planning or implementing changes.
- **Plan review is required before creating any implementation plan.** For any
  non-trivial change, load `.agents/skills/plan-review/SKILL.md` and follow its output
  structure first. Trivial changes (typos, formatting, one-line fixes) may skip it.
- To add a new skill: author the canonical `.agents/skills/<name>/SKILL.md`, then add a matching
  thin wrapper to `.opencode/commands/<name>.md`
  (same `description` frontmatter plus `agent: build`).

## Restrictions

- **Tinker is strictly prohibited:** No AI agent must use, invoke, or interact with Tinker in any form during
  autonomous actions, suggestions, or when generating code within this repository.

## Laravel Upgrades

When reviewing or executing any change that touches a Laravel core or
first-party package — directly required **or pulled in transitively** —
(laravel/framework, laravel/passport, laravel/sanctum, laravel/horizon,
laravel/telescope, league/oauth2-server, etc.):

1. Establish the installed version first:
    - `composer.lock` is the **only** authoritative source for what is
      actually running. `composer.json` declares intent; the lockfile
      declares reality.
    - Read `vendor/<package>/UPGRADE.md` **for the installed version only**
      — not the latest, not the next major. The same package has one
      UPGRADE.md per major version in its history; reading the wrong one
      introduces noise.
    - `docs/release-review.md` defines this repo's upgrade workflow, not
      upstream breaking changes. Use it for process, not for facts.

2. Flag breaking changes that affect **infrastructure or environment**, not
   just application code — specifically:
    - Filesystem permissions (e.g. OAuth key files under Passport 13's
      `league/oauth2-server ^9.0` strict check)
    - New required environment variables or config keys
    - Changed artisan commands needed during deploy
    - Database migration requirements
    - Minimum PHP/extension version bumps

3. **Transitive dependencies count.** A breaking change introduced by a
   package you don't directly require (e.g. `league/oauth2-server` bumped
   via `laravel/passport`) is still in scope. When a direct dependency is
   upgraded, follow the chain in `composer.lock` and review breaking
   changes for anything whose major version moved.

4. When reviewing changes that touch auth, permissions, deploy scripts,
   or config files — even if no upgrade is in the same PR — verify the
   change is consistent with the **currently installed** versions of
   relevant packages and their transitive deps.

5. Output a checklist of required actions before the upgrade is considered
   complete, distinguishing between **code changes** and
   **deploy/server changes**. Cross-reference `docs/release-review.md`.

6. **Verify backward-compatibility claims against the actual read path, not
   just the write path.** "Backward compatible, no action required" in an
   UPGRADE.md can be conditionally false depending on whether optional
   migrations were skipped. Real case: Passport 13's
   `Bridge\ClientRepository::fromClientModel()`
   (`vendor/laravel/passport/src/Bridge/ClientRepository.php:55-65`) reads
   `$model->grant_types` unconditionally, but pre-13 `oauth_clients` has
   no `grant_types` column — existing clients fail at `/oauth/token` with
   "unsupported_grant_type" because `Client::hasGrantType()` returns false
   on `null`. The write path was backward compatible; the read path was
   not. "No action required" was wrong for any project that skipped the
   optional schema migration.

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


