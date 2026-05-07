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
