# Code Style — Epicollect5 Server

These rules define the required coding style for the Epicollect5 server (Laravel 13 on PHP 8.3+).

If generic PHP/Laravel guidance conflicts with this file, **this file wins**.

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

PHP string interpolation:

- Do **not** use curly braces for simple variables inside double-quoted strings.
    - Prefer: `"Expected an index on $entriesTable ..."`
    - Avoid: `"Expected an index on {$entriesTable} ..."`
- Use braces only when required to disambiguate complex expressions or adjacent
  characters (property/array access, method calls, or when immediately followed by
  letters/numbers/underscore).
    - Examples where braces may be needed:
        - `"Hello {$user->name}"`
        - `"Value: {$arr['key']}"`
        - `"table_${suffix}"` (or `"table_{$suffix}"` if needed for clarity)
- If the string contains mixed dynamic parts and reads better, prefer explicit
  concatenation:
    - `"Expected an index on " . $entriesTable . " covering ... Available indexes: " . json_encode($indexes)`

## Domain rules

- Root namespace is `ec5\` (not `App\`).
- Reference domain config under `config/epicollect/` (`limits.php`, `codes.php`,
  `tables.php`, `strings.php`, `permissions.php`) instead of hardcoding values.
- Refer to `config/epicollect/strings.php` for project roles, access levels, statuses,
  and input types. Refer to `config/epicollect/permissions.php` for role hierarchy and
  management rules.
- Error handling uses custom error codes defined in `config/epicollect/codes.php`.
- Watch for N+1 queries when building queries or refactoring — eager load with `with()`/
  `load()`, use `withCount`, or select only needed columns. Be especially alert in services
  and controllers that iterate over collections of models.
