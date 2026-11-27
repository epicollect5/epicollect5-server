# New PWA Roadmap: Replace Data Editor with PWA App

## Context

- **Current branch:** `feature/new-pwa` (branched from `master` @ `baab146b`)
- **Reference branch:** `feature/pwa` / `origin/pwa` @ `7217cc0e` ("Added saved answers lookup for PWA - #TASK-196")
- **PWA divergence:** based on `78f3d74a`; 20 commits, 86 files changed (+5297/−466) vs that base
- **`master` drift:** ~1170 commits ahead of the PWA base; many API/controller/validation classes refactored or renamed
- **Goal:** Replace the current data editor with the PWA app (do **not** run side-by-side). The PWA branch attempted coexistence/debug endpoints for production testing; that is unnecessary for this effort.

**Implication:** Treat `feature/pwa` commits as **intent/reference**. Most backend changes must be **ported to current `master` code paths**, not cherry-picked.

## Current State (master)

- Entry points (keep): `GET project/{project_slug}/add-entry` (`data-editor-add`) and `/edit-entry` (`data-editor-edit`) → `Web\Project\ProjectDataController@add|edit` → `resources/views/project/data_editor.blade.php`
- Current assets: `public/data-editor/data-editor.js`, `public/data-editor/data-editor.js.map`, `public/data-editor/vendor/*`
- Current view loads Leaflet + vendor CSS + `data-editor/data-editor.js`

## PWA End-State (feature/pwa)

- `public/data-editor/app/**` Ionic/Vue/Capacitor PWA shell (app/vendor chunks, CSS, assets/icons, `manifest.json`, `service-worker.js`, workbox, status codes, demo project, fullscreen helpers)
- `resources/views/project/data_editor.blade.php` loads from `data-editor/app/...`
- Same route/controller entry points
- Functional additions in branch: PWA upload/temp-file/uniqueness/debug endpoints, saved-answers lookup, edit/media cleanup, temp storage cleanup

## Implementation Strategy

1. **Frontend swap first:** replace `public/data-editor` with PWA app assets and update `project.data_editor` view while keeping existing routes/controller.
2. **Identify required backend contract:** confirm which APIs the PWA frontend actually calls (entries, saved-answers/search, upload, temp upload/delete, uniqueness, media, geocoding/auth).
3. **Port must-have backend pieces to current master:** saved-answers lookup, temp-media delete/validation, safe upload/edit file cleanup, and temp/subset storage+delete config.
4. **Re-assess sensitive changes case-by-case:** API error keys, edit-permission bypass, middleware/throttling changes, analytics removal.
5. **Validate:** targeted tests for touched areas (upload/edit/delete/entries search/validation) and manual add/edit entry flows end-to-end.

---

## 1. Frontend: Replace Data Editor with PWA App (Core)

| Commit(s) | What changed | Action | Notes |
|---|---|---|---|
| `ddfb1f7e`, `51c9137c`, parts of `f8058194`, `7217cc0e` | New PWA shell/build in `public/data-editor/app/**` (Ionic/Vue/Capacitor, assets, `manifest.json`, `service-worker.js`, workbox, status codes, demo project, fullscreen/leaflet helpers) replacing old `public/data-editor/data-editor.js(.map)`; view updates to `data_editor`, `dataviewer`, `formbuilder`. | **Re-implement (clean replacement)** | Take PWA end-state assets and update `project.data_editor` to load from `data-editor/app/...`. Align with master conventions (`config('app.release')`/cache-busting, analytics/CSP patterns). Keep existing routes/controller (`data-editor-add/edit` → `ProjectDataController`). Decide whether `dataviewer`/`formbuilder` view changes are needed or were side-by-side/testing artifacts. Verify service worker scope/paths, manifest, and removal of old `public/data-editor/vendor`/bundle. |
| `453a4352` | Material icons added to bulk upload options (`resources/views/project/manage_entries/bulk_upload_tab_panel.blade.php`). | **Re-assess / likely port** | Small UI tweak, possibly independent of PWA; apply manually if still desired (view may have drifted). |

**Suggested swap commands (starting point):**
```bash
git rm -r public/data-editor
git checkout feature/pwa -- public/data-editor
git checkout feature/pwa -- resources/views/project/data_editor.blade.php
# then align blade with master conventions (config('app.release'), asset helpers, etc.)
```

## 2. Saved Answers / Entry Lookup for PWA (Headline Feature)

| Commit(s) | What changed | Action | Notes |
|---|---|---|---|
| `7217cc0e` | Backend for searching/fetching saved answers: edits across `Api/Entries/Download(Subset)Controller`, `Entries/View/*` (entries/locations/search base), `Http/Validation/Entries/Search/RuleAnswersSearch` (added), `RuleAnswers`, `Repositories/QueryBuilder/Entry/Search/SearchBase`, `config/ec5Enums.php`, routes (`api_external.php`, `api_internal.php`); plus PWA frontend/build updates. | **Re-implement (high priority if PWA needs it)** | Port behavior (answer/saved-answer search & filtering, query params/validation/enums/routes) onto current master entry view/download/search/query/validation structure. Controller/class paths changed significantly on master. Confirm API contract expected by the PWA frontend. |

## 3. Entry Upload, Temp Media, Uniqueness, and Edit Support

| Commit(s) | What changed | Action | Notes |
|---|---|---|---|
| `d74f6629`, `13488961`, `063c8a17`, `a4d3ce19`, `e179d537` | Debug/side-by-side `*PWA` endpoints and wrappers: `UploadController@postUploadPWA` (commented), `TempFileController@storePWA`, `UniquenessController@indexPWA`, `WebUploadController@storePWA`, `EntriesController@showPWA`, `OpenCageController@fetchAPIPWA`; routes `api/pwa-upload`, `api/pwa-unique-answer`, `api/pwa-upload-file`, `api/pwa-entries`, `api/proxies/pwa-opencage`; local-only guards (`App::isLocal()`). | **Re-assess → drop wrappers, port needed capabilities** | If replacing the editor, prefer standard/current upload, temp-file, uniqueness, and entries endpoints (with correct auth/middleware/permissions/rate-limits). Determine which endpoints the PWA frontend calls, whether they must work in production (remove local-only gates), and whether to extend existing controllers vs add new actions. Do not blindly recreate `pwa-*` routes. |
| `8b1c0128` | Temp media delete for PWA: `TempFileController@destroy`, `Http/Validation/Media/RuleTempMediaDelete` (new), temp-file delete routes; plus `ApiRequest` error-key changes (`json-data-tag` → `data-key`/`data-invalid`/`type-invalid`), `RuleFileEntry`/`RuleVideo`/status-code tweaks. | **Re-implement selectively & re-assess** | **Temp media delete:** likely needed—port to current temp-media controller/validation/routes with proper auth/ownership checks. **ApiRequest/error keys:** re-assess carefully—renaming keys/codes can break existing clients (mobile/web/dataviewer) and tests; adopt only if intentional and consistent. **RuleFileEntry/RuleVideo/status tweaks:** port if they fix real bugs; verify against current validation logic. |
| `e179d537`, `f8058194` | `WebUploadController`: on edit, detect edited entries (`ec5_357`) and delete replaced stored files (`deleteStoredFiles` for photo sizes/audio/video) based on `files_to_delete`; cleanup/debug-log removal. | **Re-implement & review carefully** | Important for correctness when editing entries with media (avoid orphaned/replaced files). Port to current web/app upload flow: confirm payload shape (`entry.files_to_delete`), support branch entries if applicable, run only on appropriate edit paths, and handle all media disks/sizes/remote (S3) consistently with current storage abstraction. Keep cleaned-up behavior from `f8058194`. |
| `e179d537` | `EntryStructure@canEdit` / `EntryValidationBase`: allow anonymous web-platform edits when `App::isLocal()` and user is anonymous (PWA debugging). | **Re-assess — likely do NOT port as-is** | Weakens edit authorization in local env (debug bypass). Determine legitimate production edit-permission path for PWA instead. |
| `7217cc0e` (+ earlier) | Changes to `RuleAnswers`, upload/answer validation in PWA context. | **Re-assess / port with tests** | Ensure any relaxed/changed validation required by saved-answers/PWA edits does not regress existing upload validation. |

## 4. Storage, Config, and Cleanup on Delete

| Commit(s) | What changed | Action | Notes |
|---|---|---|---|
| `d21fea42`, `6d47f8ef` | Temp/subset cleanup on project/entries delete: added disks `temp-audio`, `temp-photo`, `temp-video`, `temp-subset` in `config/filesystems.php` and included them in `config/ec5Media.php` (`project_deletable`/`entries_deletable`); `DeleteRepository` adjusted. | **Re-implement / re-assess** | If PWA stores media in temp locations, project/entry deletion must purge them. Port to current delete/cleanup service/repository and media config (master structure may differ). Confirm disk roots/driver usage (local vs S3) match current PWA upload flow. |
| `5d84dec3` (config part) | Fixed `temp-video` disk root (`app/temp/audio` → `app/temp/video`). | **Re-assess / ensure correct** | Likely bug fix—verify correct paths if temp-video is (re)introduced. |
| `d21fea42` (routes) | Minor route tidy-ups alongside cleanup. | **Re-assess** | Apply only if relevant to chosen endpoint design. |

## 5. Middleware, Throttling, and Access Behavior

| Commit(s) | What changed | Action | Notes |
|---|---|---|---|
| `52916b0d` | Removed `IpMiddleware` import/usage from `app/Http/Kernel.php`. | **Re-assess carefully** | Check current master: if `IpMiddleware` exists/is used, understand why it was removed and whether removal is still correct/safe. Do not drop security/IP-filtering unintentionally. |
| `64352d07` | Removed throttling for auth on localhost in `routes/api_external.php`. | **Re-assess — likely dev-only** | Avoid weakening production rate-limiting; if needed, gate by environment explicitly and align with current throttling/rate-limiter setup. |

## 6. Product/UX/Content Tweaks (Not Strictly Required)

| Commit(s) | What changed | Action | Notes |
|---|---|---|---|
| `5d84dec3` (views part) | Removed Google Analytics from `resources/views/header.blade.php` and project views (`data_editor`, `dataviewer`, `formbuilder`). | **Re-assess (product decision)** | Separate from replacing data-editor with PWA. Views diverged on master—reapply only if GA removal is desired now and consistent with current privacy/analytics setup. |
| `d0abab81` | Comment fixes in `Api/Project/MediaController` and external routes. | **Skip / optional** | Non-functional; reapply only when touching those files. |
| `ca204298` | Amended dev server URL in `resources/assets/data-admin/app/config/parameters.js`. | **Likely skip** | Dev/build-time config, probably obsolete. |
| `a4d3ce19` (web route) | `users/search-by-email` pointed to `AdminUsersController` instead of `ManageUsersController`. | **Re-assess** | Verify correct controller on current master (may already be fixed differently). |

## 7. Skip / Do Not Bring Over

| Commit(s) | What changed | Action | Notes |
|---|---|---|---|
| `d74f6629` (commented code), `13488961`/`063c8a17`/`a4d3ce19`/`e179d537` debug-only wrappers | Commented-out method, local-only debug endpoints (`pwa-upload`, `pwa-unique-answer`, `pwa-upload-file`, `pwa-entries`, `pwa-opencage`), browser-debug entrypoints. | **Skip as designed** | Artifacts of side-by-side/testing approach. Replace with proper production-capable integration on standard endpoints where possible. |
| `aeca6a2f` | Cleaned up imports in old controller files. | **Skip** | Obsolete against refactored master. |
| `94b4da88` | “merged master into pwa” (merge commit at old point). | **Skip** | Do not cherry-pick. |

## Suggested Execution Order

1. **Frontend swap:** replace `public/data-editor` with PWA app assets and update `project.data_editor` view (keep existing routes/controller) — get the UI loading.
2. **Identify required backend contract:** from the PWA frontend, confirm which APIs it calls (entries, saved-answers/search, upload, temp upload/delete, uniqueness, media, geocoding).
3. **Port must-have backend pieces to current master:** saved-answers lookup (`7217cc0e`), temp-media delete/validation + safe upload/edit file cleanup (`8b1c0128`, `e179d537`/`f8058194`), and temp/subset storage+delete config (`d21fea42`, `6d47f8ef`, temp-video fix).
4. **Re-assess sensitive changes:** `ApiRequest` error keys, `EntryStructure@canEdit` bypass, `IpMiddleware` removal, throttling changes, GA removal—decide case-by-case against current behavior and clients.
5. **Validate:** run targeted tests for touched areas (upload/edit/delete/entries search/validation) and manually verify add/edit entry flows end-to-end.

## Useful References (from feature/pwa)

```bash
# Commits unique to PWA
git log --oneline master..feature/pwa

# End-state diff vs master (note: large; compare from merge-base for PWA-only view)
git diff --stat master...feature/pwa

# Inspect specific commit/file intent
git show <sha> -- <path>
git show feature/pwa:<path>
```