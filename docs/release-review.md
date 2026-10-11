## Release Candidate Review

When asked to review a release candidate, analyse all changes introduced since the given base commit.

Use:

```bash
git diff --stat <base-commit>..HEAD
git diff --name-status <base-commit>..HEAD
git diff <base-commit>..HEAD
````

Create a QA document under:

```text
docs/QA-YYYY-MM-DD-release-candidate.md
```

The document must include:

1. **Summary of changes**

    * Group changes by feature, bug fix, refactor, database, API, config, mobile/web UI, tests, and docs where
      applicable.
    * Explain user-visible behaviour changes.
    * Explain important internal behaviour changes.

2. **Manual QA checklist**

    * List concrete manual test cases.
    * Include setup steps, expected results, and edge cases.
    * Cover regression risks and side effects, not only the changed files.
    * Consider offline/online flows, permissions, cache invalidation, project updates/deletion, sync, uploads/downloads,
      and error handling where relevant.

3. **Environment/config changes**

    * List any new, removed, or changed `.env` keys.
    * Mention default values if present.
    * Mention whether production/staging/local config needs updating.
    * If no `.env` changes are found, explicitly state that.

4. **Database changes**

    * List migrations, schema changes, indexes, constraints, data backfills, or changed JSON structures.
    * Mention rollback/compatibility risks.
    * If no DB changes are found, explicitly state that.

5. **API/payload changes**

    * List changed endpoints, request parameters, response fields, validation rules, rate limits, cache headers, or
      payload schemas.
    * Mention backwards compatibility concerns.

6. **Architecture/docs updates**

    * Check whether existing architecture, database, schema, or operational docs are now outdated.
    * Update relevant docs if needed.
    * If no docs need updating, explicitly state that in the QA file.

7. **Risk assessment**

    * Identify high-risk areas.
    * Identify likely hidden side effects.
    * Mention anything that should be reviewed carefully before release.

Rules:

* Do not make unrelated refactors.
* Do not change application code unless explicitly asked.
* Prefer precise file references.
* Be conservative: if something might need manual testing, include it.
* Do not claim something was tested unless you actually ran the test.
* If tests are run, include the command and result.

## Notable API changes

### `POST api/import/project/validate` — optional `warning` field inside each error object (additive, non-breaking)

- Failure responses (`HTTP 400`) now attach a `warning` field **inside each error object**
  when the raw payload is rejected **solely** because of legacy issues that are
  automatically fixed during import (e.g. a too-short `small_description`, `<`/`>` in
  descriptions). The `warning` value is the resolved message from code `ec5_409`
  ("Legacy issue(s) will be automatically fixed on import.").
- Genuine validation failures (e.g. invalid `category`, unknown mapping ref) omit the
  `warning` field — sanitisation cannot fix them.
- Successful validation (`HTTP 200`) never includes a `warning` key.
- Detection: the endpoint re-validates a sanitised copy of the payload
  (`ProjectDTO::sanitiseProjectDefinitionForExport`). If the sanitised copy passes,
  every raw failure was auto-fixable and the warning is attached. The sanitised
  `meta.project_mapping` is **not** covered by sanitisation, so mapping-only errors are
  always treated as genuine (no warning).
- Source: `app/Http/Controllers/Api/Project/ProjectController.php::validateImport()`
  and `legacyAutoFixWarning()`; macros `ApiSchemaError` / `ApiErrorCode` (now accept an
  optional `$warning` string injected into each error object); new code `ec5_409` in
  `config/epicollect/codes.php`.
- Tests: `tests/Http/Controllers/Api/Project/ProjectValidateImportControllerTest.php`
  (`test_warns_on_legacy_autofixable_small_description`,
  `test_no_warning_on_genuine_schema_error`, `test_no_warning_on_success`).

### `description` whitespace-only normalisation (legacy import fix)

- `sanitiseProjectDefinitionForExport` now normalises a whitespace-only `description`
  (e.g. `"\r\n"` from a legacy / Windows-CRLF import) to the schema-legal
  empty string `""`. `Strings::collapseWhitespace` alone collapses `"\r\n"` to
  a single space, which still fails the JSON Schema `description` `anyOf` (empty
  OR 3-3000 chars), so the explicit trim-to-empty step was required.
- Root cause: production import (`RuleProjectExtraDetails` `'description' => ''`) never
  validated `description`, so `"\r\n"` values were persisted verbatim into
  `json_structure` / `json_structure_extra`. Master's stricter JSON Schema then
  surfaced them via `projects:validate` and `validateImport`.
- `small_description` is intentionally **unchanged** (it pads short values to 15
  characters for listing display). Only `description` is normalised to empty.
- This change lives inside `sanitiseProjectDefinitionForExport`, so it is picked up
  by both the **export** path (`getSanitisedProjectDefinition`) and **import**
  (`ProjectDTO::import`), cleaning data on the natural export -> re-import cycle
  without a separate backfill.
- Tests: `tests/DTO/ProjectDTOTest.php`
  (`test_sanitise_collapses_whitespace_only_description_to_empty`);
  `tests/Http/Controllers/Api/Project/ProjectValidateImportControllerTest.php`
  (`test_warns_on_legacy_autofixable_whitespace_only_description`,
  `test_no_warning_on_genuine_short_description`).
