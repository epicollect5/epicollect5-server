---
name: migration-safety
description: Enforce safe Laravel/first-party package upgrades and migration changes. Auto-triggers when editing composer.json, composer.lock, database/migrations/*, vendor/**, or any PR touching auth, permissions, deploy scripts, or config files.
---

# Migration & Upgrade Safety

Follow the **Laravel Upgrades** discipline from `AGENTS.md` for any change that touches a
Laravel core or first-party package — directly required **or pulled in transitively**
(`laravel/framework`, `laravel/passport`, `laravel/sanctum`, `laravel/horizon`,
`laravel/telescope`, `league/oauth2-server`, etc.).

## Rules

1. **Establish the installed version first.**
   - `composer.lock` is the **only** authoritative source for what is actually running.
     `composer.json` declares intent; the lockfile declares reality.
   - Read `vendor/<package>/UPGRADE.md` **for the installed version only** — not the latest,
     not the next major. Reading the wrong major's UPGRADE.md adds noise.
   - `docs/release-review.md` defines this repo's upgrade *process*, not upstream facts.
     Use it for process, not for version-specific breaking changes.

2. **Flag breaking changes that affect infrastructure/environment**, not just application code:
   - Filesystem permission requirements (e.g. OAuth key files under Passport 13's
     `league/oauth2-server ^9.0` strict check)
   - New required environment variables or config keys
   - Changed artisan commands needed during deploy
   - Database migration requirements
   - Minimum PHP/extension version bumps

3. **Transitive dependencies count.** A breaking change from a package you don't directly
   require (e.g. `league/oauth2-server` bumped via `laravel/passport`) is still in scope.
   When a direct dependency is upgraded, follow the chain in `composer.lock` and review
   breaking changes for anything whose major version moved.

4. **Verify consistency with installed versions even when no upgrade is in the PR.** For
   any change touching auth, permissions, deploy scripts, or config files, confirm it is
   compatible with the currently installed versions of relevant packages and their
   transitive deps.

5. **Output a checklist** of required actions before the upgrade is considered complete,
   distinguishing **code changes** from **deploy/server changes**. Cross-reference
   `docs/release-review.md`.

6. **Verify backward-compatibility claims against the read path, not just the write path.**
   "Backward compatible, no action required" in an UPGRADE.md can be conditionally false
   depending on skipped optional migrations. Real case: Passport 13's
   `Bridge\ClientRepository::fromClientModel()`
   (`vendor/laravel/passport/src/Bridge/ClientRepository.php:55-65`) reads
   `$model->grant_types` unconditionally, but pre-13 `oauth_clients` has no `grant_types`
   column — existing clients fail at `/oauth/token` with "unsupported_grant_type". The
   write path was backward compatible; the read path was not.

7. **Never run `composer update` without showing a diff first.**

## Forbidden autonomously
- `php artisan migrate:fresh`, `php artisan migrate:reset`, `php artisan db:wipe`
- `php artisan vendor:publish --tag=*-migrations` (creates duplicate migrations)
- `composer update` without a reviewed diff
