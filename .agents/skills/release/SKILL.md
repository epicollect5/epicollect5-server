---
name: release
description: Cut a server release — prepend changelog release notes from git history, tag it, and (on confirmation) push the tag that triggers the GitHub Release
---

# Release

Cut a new release. Given an optional release number, determine the version, prepend
release notes to `CHANGELOG.md`, then (on explicit confirmation) commit, tag, and
push the tag — which triggers `.github/workflows/releases.yml` to create the
GitHub Release automatically.

## Inputs

- `number` (optional): a full version (`X.Y.Z`). When omitted, the skill suggests
  the next version and asks for confirmation.

## Versioning scheme

  version = `<major>.<minor>.<patch>`   (plain semver)

- The release version is the `# X.Y.Z` heading in `CHANGELOG.md` and the matching
  git tag (`X.Y.Z`). The current latest is `13.0.1`.
- **Major version tracks the Laravel framework version** (currently `13`). Minor
  and patch follow the usual semver standards.
- **The git tag and the CHANGELOG heading are authoritative.** There is no
  `version` field in `composer.json` for this app. `.env.example` carries
  `PRODUCTION_SERVER_VERSION` (currently drifted to `0.0.1`) and `RELEASE`
  (cache-buster, `0010`) — warn about drift but do not treat them as authoritative.
- There is no native app or single deployable artifact: the app is deployed from
  the repo via Deployer (`deploy.php`).

## Steps

0. **Test suite notice** (first, before edits):
   - The full PHPUnit suite takes over 1 hour (`vendor/bin/phpunit`). Do NOT run it
     unfiltered. Warn the user and proceed without blocking.
   - Recommend running targeted tests for the changed areas instead, e.g.
     `vendor/bin/phpunit --no-progress tests/Path/To/ChangedTest.php`.
   - If the user explicitly wants the full suite, run it only on their request.

1. Read current state
   - `CHANGELOG.md` -> the latest `# X.Y.Z` heading (under `## Release Notes`)
   - `git tag` -> the highest `X.Y.Z` tag (the previous release)
   - If the tag and the CHANGELOG heading disagree, stop and ask which is correct.
   - Check `.env.example` `PRODUCTION_SERVER_VERSION` / `RELEASE` for drift and
     report (non-blocking).

2. Determine the new version
   - If `number` is `X.Y.Z`: use it verbatim.
   - If no `number`: suggest a patch bump `<current>.<minor>.<patch+1>`; for a
     feature/minor release ask whether to bump `<minor>` instead.
   - Print the suggestion and proceed only after confirmation (or immediately when
     an explicit `number` was passed).

3. Generate release notes
   - `git log --oneline <prev-tag>..HEAD` (base = previous release tag).
   - Collapse commits into concise, user-facing bullets grouped by area
     (features, fixes, build/migration, tests); drop internal/CI-only commits.
   - Prepend to `CHANGELOG.md` right after the `## Release Notes` line:
     `# <new>` followed by `- ...` bullets.
   - Mirror the existing bullet style (`- `).

4. Optional asset rebuild
   - If `resources/assets/**` changed since the previous tag, rebuild the tracked
     `public/` outputs with `npx gulp` (default task) and include the resulting
     diff in the release commit. Confirm the diff contains only expected bundles.
   - Skip if no asset source changes.

5. Report and confirm — LOCAL steps
   - Show version, changed files, and (if applicable) rebuilt assets.
   - Ask whether to commit (`chore: release <new>`) and create an annotated tag
     (`<new>`). Do not commit or tag unless confirmed.
   - Local commit and tag are reversible and may be done in one go.

6. Report and confirm — PUSH (a separate gate)
   - A request to cut a release is not consent to push. Ask separately and show
     exactly what will go out: branch, number of commits, tag name.
   - Wait for explicit yes. Do not push on silence or on approval of an earlier step.
   - Then, and only then:
     ```
     git push origin master
     git push origin <tag>
     ```
   - Pushing the tag triggers `.github/workflows/releases.yml`, which creates the
     GitHub Release automatically (extracting the new section from `CHANGELOG.md`).
     No manual `gh release create` is needed.

7. Verify the GitHub Release (after the tag is pushed)
   - Wait for the workflow to finish, then confirm the release exists:
     ```
     gh release view <tag>
     ```
   - If it failed, inspect the workflow run and fix the cause (usually missing/empty
     release notes for the version) before re-pushing.

8. Deploy is a **separate, explicit** step
   - Run via Deployer: `dep update production` (or staging/dev) after pushing.
   - Migrations run during deploy; ensure any new migration is backward compatible.
   - Bump `RELEASE` in the server `.env` to bust the asset cache when views/assets
     change; `PRODUCTION_SERVER_VERSION` should be updated to `<new>`.
   - Never deploy as part of the release unless the user asks for it, and confirm
     the target (production/staging/dev) first.

## Constraints

- The full PHPUnit suite is NOT run by this skill; it is too long. Warn and defer to
  targeted tests. Do not claim the suite is green.
- Pushing is always a separate, explicit confirmation. Never fold the push into the
  commit/tag question.
- The GitHub Release is created automatically by the tag-push hook; verify it
  rather than creating it manually.
- Deploying is a separate, explicit step and never implicit.
- Do not bump `composer.json` (no version field) and do not run `composer update`.
```<|end|><|start|><|start|>assistant to=write_file<|channel|>analysis <|constrain|>xml<|message|><rfl:parameter name="file_path">
/Users/mmenegazzo/Sites/epicollect5-server/.opencode/commands/release.md