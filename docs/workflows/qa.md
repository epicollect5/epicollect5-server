# QA Generation Workflow

Generate QA documentation from a codebase change or QA spec file.

## Inputs

- **git diff** (preferred if available).
- **QA spec file** (optional): `docs/QA-{version}.md`.

If both exist:

- Prioritize git diff.
- Reconcile against QA spec.

## Responsibilities

Identify all user-impacting changes across:

- Feature changes
- New endpoints/controllers
- Migrations/schema changes
- Authentication/authorization changes
- Frontend/UI changes
- API contract changes
- Business logic changes

## QA Generation Rules

For every meaningful change, generate at least one QA check.

Each QA check must include:

- **Test Description** — what is being tested.
- **Expected Result** — what success looks like.
- **Manual Action** — step-by-step staging instructions.

Rules:

- Must be executable in staging.
- No abstract descriptions.
- No unit/integration test instructions.
- Full coverage of changes required.
- No duplicates.

## Output Format

### 1. QA Report (Markdown file)

Grouped by feature or area.

### 2. QA CSV file (.csv)

Columns: `Action Description` | `Expected`

- One row per QA check.
- Action must describe a human action in staging.
- Expected must describe observable result.

## Validation Rules

Before output, ensure:

- Full coverage of changes.
- No duplicates.
- All steps reproducible in staging.
- Diff fully mapped to QA checks.
