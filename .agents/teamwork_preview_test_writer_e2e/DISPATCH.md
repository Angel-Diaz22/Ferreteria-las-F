## 2026-09-06T03:43:01Z

You are teamwork_preview_test_writer_e2e for the E2E Testing Track.
Your working directory is: /Users/angeldiaz/Documents/FerreterialasF/.agents/teamwork_preview_test_writer_e2e

MANDATORY FIRST STEP:
Read:
1. /Users/angeldiaz/Documents/FerreterialasF/.agents/ORIGINAL_REQUEST.md
2. /Users/angeldiaz/Documents/FerreterialasF/PROJECT.md
3. /Users/angeldiaz/Documents/FerreterialasF/.agents/rules/pos-cash-registers-flow.md
4. /Users/angeldiaz/Documents/FerreterialasF/.ai/rules/boost/filament-tailwind-css-grid.md

OBJECTIVE:
Build a comprehensive 4-Tier E2E automated test suite in Pest for Ferretería Las F:
- Tier 1: Feature Coverage (>=5 test cases per feature across F01-F20)
- Tier 2: Boundary & Corner Cases (>=5 test cases per feature covering empty inputs, zero/negatives, max bounds, unique constraint duplicates)
- Tier 3: Cross-Feature Combinations (pairwise interactions: e.g. stock adjustment + POS sale, shift opening + cash payment, quote + customer price list)
- Tier 4: Real-World Application Scenarios (>=5 realistic store workflows: multi-item POS sale with payment on Caja 3, shift opening and cash reconciliation, quote conversion to sale, warehouse transfer and inventory audit)

TEST INFRASTRUCTURE:
- Test files must be placed in `tests/Feature/E2E/`
- Test command: `"$HOME/Library/Application Support/Herd/bin/php" artisan test --compact`
- Create `/Users/angeldiaz/Documents/FerreterialasF/TEST_INFRA.md` at project root using the standard template.
- When all test files are verified, create `/Users/angeldiaz/Documents/FerreterialasF/TEST_READY.md` at project root with full coverage tables.

SCOPE BOUNDARIES:
- You write tests and documentation ONLY (`tests/Feature/E2E/`, `TEST_INFRA.md`, `TEST_READY.md`).
- DO NOT modify application source code in `app/`.

OUTPUT REQUIREMENTS:
- Create `TEST_INFRA.md` and `TEST_READY.md` at project root.
- Write handoff to /Users/angeldiaz/Documents/FerreterialasF/.agents/teamwork_preview_test_writer_e2e/handoff.md.
- Send a completion message to parent when done.
