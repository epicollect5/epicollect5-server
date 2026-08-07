# Epicollect5 Code Style

*Canonical skill definition. Agent wrappers: `.opencode/skills/ec5-code-style/` and `.codex/skills/ec5-code-style/`.*

Apply these conventions (from `AGENTS.md`) to every PHP change in this repo.

## Properties & types

- **Always add property's type declaration** — use typed properties on classes.
- No `_` prefix on private/protected methods or properties.
- Declare explicit return types on all methods, including `void`.
- Eloquent model boilerplate (`$fillable`, `$casts`, `$table`) may remain untyped.

## Formatting

- PSR-12, enforced via `vendor/bin/pint` (PSR-12 preset, configured in `pint.json`).
- Prefer early returns over nested if/else. Handle error conditions first, success last.
- Do not add docblocks when the method signature already conveys the information. Use
  docblocks only to explain *purpose*, not to restate types.
- `camelCase` for variable names; `snake_case` for configuration keys, JSON keys, and
  database columns.

## Templates

- Blade: indent with 4 spaces. No space after Blade control structures:
  `@if($condition)`, not `@if ($condition)`.

## Strings

- PHP string interpolation: do **not** use curly braces for simple variables inside
  double-quoted strings. Prefer `"value $var"`. Use braces only when required to
  disambiguate (property/array access, method calls, or adjacent alphanumerics).

## Domain rules

- Root namespace is `ec5\` (not `App\`).
- Reference domain config under `config/epicollect/` (`limits.php`, `codes.php`, `tables.php`,
  `strings.php`, `permissions.php`) instead of hardcoding values.
- Watch for N+1 queries when building queries or refactoring — eager load with `with()`/
  `load()`, use `withCount`, or select only needed columns. Be especially alert in services
  and controllers that iterate over collections of models.