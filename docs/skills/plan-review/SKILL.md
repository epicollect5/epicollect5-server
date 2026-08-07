# Plan Review

*Canonical skill definition. Agent wrappers: `.opencode/skills/plan-review/` and `.codex/skills/plan-review/`.*

Perform a plan review before planning or implementing any non-trivial change. The
goal is to prevent blindly executing the user's proposed implementation and to ensure the
change reaches the real objective in the simplest way that satisfies it.

## When to use

- Before creating an implementation plan for any feature, refactor, or change.
- Whenever the user proposes an implementation ("add X to Y", "create a Z service",
  "introduce a new table"), as a safeguard against over-engineering.
- Skip only for trivial changes (typos, formatting, one-line fixes) at your discretion —
  when in doubt, run the review.

## Process

1. **Identify the real goal.** Determine what problem the change actually solves, who it
   serves, and what "done" looks like. Restate the goal in your own words and confirm it
   before moving on.
2. **Do not blindly follow the user's proposed implementation.** Treat the proposal as a
   starting point, not as the goal. A proposal is a hypothesis; verify it.
3. **Inspect the repository for existing patterns.** Use code search / CodeGraph to find
   how similar problems were solved. Look in `app/Services/`, `app/Traits/`, `app/DTO/`,
   `app/Http/Controllers/Api/`, `routes/`, and `config/epicollect/`. Read the relevant
   sections of `docs/architecture.md` and `docs/database-schema.md` for the source of truth.
4. **Check if the problem is already solved.** Verify whether existing code, a framework
   feature (Laravel/Eloquent), or an installed dependency (check `composer.json`) already
   covers the need. Never reinvent what already exists.
5. **Propose simpler alternatives when available.** If the current proposal is heavier
   than its requirements, find at least one simpler approach that still satisfies the goal.
6. **Compare alternatives.** For each candidate (the current proposal plus alternatives),
   evaluate and compare:
   - **Complexity** — how many new concepts, files, and moving parts it adds.
   - **Maintenance cost** — recurring maintenance burden over time.
   - **Implementation effort** — time and risk to build and test it now.
   - **Long-term impact** — whether it blocks or enables future work.
7. **Recommend the simplest solution** that satisfies the requirements.
8. **Only then produce an implementation plan.**

## Output structure

Produce the review with this exact structure:

### Goal

The real problem to solve and the success criteria.

### Current proposal

The user's proposed approach and why it may be heavier than needed.

### Existing patterns found

Existing code, framework features, or dependencies relevant to the goal.

### Simpler alternatives

Each alternative, with the requirement it satisfies and its tradeoffs.

### Recommendation

The preferred option, with the comparison (complexity vs. maintenance vs. effort vs.
long-term impact) that justifies it.

### Implementation plan

Concrete, ordered steps for the chosen solution.