---
name: false-positive
description: Mark a review finding as false positive — comment code and record the reason
---

# False Positive

Mark a review finding as a false positive.

Follow the **Code Review Workflow** defined in `docs/workflows/review.md`: read the code comments in every changed file and the whole of `docs/architecture.md` before dismissing any candidate finding. Only dismiss a finding the existing code or architecture explicitly justifies as by design.

Forward `$ARGUMENTS` verbatim to the workflow. If `$ARGUMENTS` is empty, derive the target (file, condition, reported text, and reason) from the session context — the most recent review report in the conversation — and ask the user for any missing `reason` or to disambiguate between multiple findings. Do not assume a finding without confirmation.

Annotate the related code with a concise PHP comment (`//` or `/* ... */`, PSR-12 — no logic changes, no unrelated refactoring) stating the finding is a false positive by design with a 1–2 sentence reason. This repo has no central false-positives registry file yet, so the code comment is the record. If false positives start to accumulate, propose creating a known-review-false-positives registry under `docs/` so future reviews can cross-check it.
