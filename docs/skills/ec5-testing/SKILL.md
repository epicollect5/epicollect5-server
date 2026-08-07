# Epicollect5 Testing Conventions

*Canonical skill definition. Agent wrappers: `.opencode/skills/ec5-testing/` and `.codex/skills/ec5-testing/`.*

Follow these rules whenever writing or running tests in this repo (from `AGENTS.md`).

## Writing tests

1. Extend `Tests\TestCase` (defined in `tests/TestCase.php`).
2. Use the `DatabaseTransactions` trait for database tests — the dominant pattern.
3. Use `clearDatabase()` with specific params **only** for tests needing explicit cleanup
   (e.g. sequence tests without transactions, or clearing pre-existing artifacts in `setUp`).
4. Use model factories / generators from `ec5\Libraries\Generators\`
   (`ProjectDefinitionGenerator`, `EntryGenerator`) for fixtures. Never make real external
   calls; mock as needed.
5. Place tests in `tests/Routes/` or `tests/Services/` following existing patterns.
6. **Tinker is strictly prohibited** in this repo — never use it to set up or inspect test state.

## Running tests — CRITICAL

- **NEVER** run `php artisan test` or `vendor/bin/phpunit` with no filter. The full suite
  takes **1+ hour**.
- Always target a file or directory:
  `vendor/bin/phpunit --no-progress tests/Path/To/YourTest.php`
- If the app only needs to boot without running tests, use `php artisan about`.
- Only run the full suite when the user explicitly types "run the full test suite".

## Maintenance-mode test pattern

When testing behavior under maintenance mode, follow the existing pattern
(`tests/Http/Controllers/MaintenanceModeTest.php`): enable with `$this->artisan('down')`,
assert the expected status/error code, then disable with `$this->artisan('up')` in the same
test so state never leaks.