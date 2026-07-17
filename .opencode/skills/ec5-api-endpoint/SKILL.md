---
name: ec5-api-endpoint
description: Guide adding or modifying Epicollect5 API endpoints. Auto-triggers when adding/changing a route in routes/api_external.php or routes/api_internal.php, or creating a controller under app/Http/Controllers/Api/.
---

# Adding a new API endpoint

Follow the canonical workflow from `AGENTS.md` ("Common Tasks → Adding a new API endpoint").

## Steps

1. **Route** — define it in `routes/api_external.php` (mobile apps / external consumers,
   uses `ec5\Http\Controllers\Api\`) or `routes/api_internal.php` (web front-end).
   Route URLs use **kebab-case**: `download-entries`, `project-users`, `verify-google`.

2. **Controller** — create it in `app/Http/Controllers/Api/` and keep it **thin**. Delegate
   all business logic to a service in `app/Services/`.

3. **Validation** — add validation rules in `app/Http/Validation/` when needed (there are
   per-domain subfolders: `Admin`, `Auth`, `Entries`, `Media`, `Project`, `Schemas`).

4. **Service** — create or reuse a service in `app/Services/` for the business logic.

5. **DTO** — use DTOs from `app/DTO/` to pass structured project/entry data between layers.

6. **Error codes** — add new error codes to `config/epicollect/codes.php` if introducing new
   error conditions. Do not hardcode strings; reference the config.

7. **Tests** — write tests in `tests/Routes/` or `tests/Services/` following existing
   patterns (see the `ec5-testing` skill for the full testing conventions and the mandatory
   targeted-run command).

## Domain reminders

- This is a Laravel app using the `ec5\` namespace; controllers map to routes via the
  `Api\...` namespace prefix (see existing entries in `routes/api_external.php`).
- Keep error responses shaped as the repo's standard error envelope
  (`errors[].code/title/source`) driven by `config/epicollect/codes.php`.
