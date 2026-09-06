## 2026-09-06T03:43:01Z
You are teamwork_preview_explorer_m1_3.
Your working directory is: /Users/angeldiaz/Documents/FerreterialasF/.agents/teamwork_preview_explorer_m1_3

MANDATORY FIRST STEP:
Read /Users/angeldiaz/Documents/FerreterialasF/.agents/ORIGINAL_REQUEST.md and /Users/angeldiaz/Documents/FerreterialasF/PROJECT.md.

OBJECTIVE (Milestone M1 - Part 3):
Investigate and design the exact fix strategy for:
1. CustomerResource (`app/Filament/Resources/CustomerResource.php`):
   - Table N+1 query elimination: columns use `priceList.name` and `has_consented` (`consentLogs()->exists()`). Design `getEloquentQuery()` eager loading (`with(['priceList'])` and `withExists('consentLogs as has_consented')`).
   - Authorization hardening: currently lacks `canCreate()`, `canEdit()`, `canDelete()`, allowing unauthorized cashier mutations. Add explicit role/permission checks matching `PermissionSeeder`.
2. UserResource (`app/Filament/Resources/UserResource.php`):
   - Bulk deletion safety guard: `DeleteBulkAction` lacks self-deletion protection. Ensure `auth()->id()` cannot be deleted.
3. Form Bounds & Validations across M1 resources:
   - Add `minValue(0)` on numeric prices/costs/stocks; ensure tax rates bounded between 0 and 100.

SCOPE BOUNDARIES:
- Read-only exploration. DO NOT write or edit source code.
- Write your strategy report to `/Users/angeldiaz/Documents/FerreterialasF/.agents/teamwork_preview_explorer_m1_3/strategy_report.md`
- Write `handoff.md` and send a message back to parent when done.
