## 2026-09-06T03:57:50Z

You are teamwork_preview_challenger_m1_2.
Your working directory is: /Users/angeldiaz/Documents/FerreterialasF/.agents/teamwork_preview_challenger_m1_2

MANDATORY FIRST STEP:
Read:
1. /Users/angeldiaz/Documents/FerreterialasF/.agents/ORIGINAL_REQUEST.md
2. /Users/angeldiaz/Documents/FerreterialasF/PROJECT.md
3. /Users/angeldiaz/Documents/FerreterialasF/.agents/teamwork_preview_worker_m1/handoff.md

OBJECTIVE:
Empirically stress-test and challenge Milestone M1 boundary constraints and security:
1. Challenge Warehouse code uniqueness: verify that attempting to save a duplicate code is caught by validation and does not trigger an unhandled 500 PDOException.
2. Challenge Customer debt guard: verify that attempting to delete a customer with `current_debt > 0` is rejected.
3. Challenge User self-deletion guard: verify that an admin cannot select or bulk-delete their own account.
4. Challenge PriceList single-default invariant: verify that toggling a list as default correctly unsets previous defaults.
5. Report verdict: `APPROVE` or `REQUEST_CHANGES` with concrete empirical evidence in `handoff.md`.
6. Send completion message to parent.
