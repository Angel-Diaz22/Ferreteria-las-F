# Test Infrastructure Specification: Ferretería Las F ERP/POS

## 1. Overview & Objectives
This document establishes the architecture, test conventions, directory structure, execution protocol, and verification standards for the **4-Tier Automated E2E Test Suite** of Ferretería Las F.

## 2. Environment & Tooling
- **Framework**: Laravel 12.x
- **Language**: PHP 8.4 (typed arguments, match expressions, modern enum casting)
- **Test Runner**: Pest v3.7 / PHPUnit 11
- **Code Style Formatter**: Laravel Pint (`vendor/bin/pint --format agent`)
- **Database Engine**: SQLite In-Memory (`:memory:`) with `RefreshDatabase`
- **Authentication & Authorization**: Spatie Laravel-Permission (`PermissionSeeder`)

## 3. Test Command Matrix
| Action | Command | Purpose |
|---|---|---|
| **Run All E2E Tests** | `"$HOME/Library/Application Support/Herd/bin/php" artisan test tests/Feature/E2E --compact` | Runs the 4-Tier E2E automated test suite (221 tests) |
| **Run Full Application Suite** | `"$HOME/Library/Application Support/Herd/bin/php" artisan test --compact` | Runs the complete suite including baseline tests (275 tests) |
| **Run Specific Tier** | `"$HOME/Library/Application Support/Herd/bin/php" artisan test tests/Feature/E2E/Tier1 --compact` | Runs a designated tier (Tier1, Tier2, Tier3, Tier4) |
| **Run Single Test File** | `"$HOME/Library/Application Support/Herd/bin/php" artisan test <path-to-test-file> --compact` | Isolates a specific test suite |
| **Code Formatting** | `"$HOME/Library/Application Support/Herd/bin/php" vendor/bin/pint tests/Feature/E2E --format agent` | Enforces PSR-12 / Laravel Pint styling |

## 4. Test Suite Architecture: 4-Tier Organization

```
tests/Feature/E2E/
├── Tier1/                          # Feature Coverage (>=5 test cases per feature across F01-F20)
│   ├── MasterDataFeatureTest.php           # F01 - F06 (30 tests)
│   ├── TransactionalFeatureTest.php        # F07 - F11 (25 tests)
│   ├── PosAndConcurrencyFeatureTest.php    # F12 - F16 (25 tests)
│   └── QualityAndIntegrityFeatureTest.php  # F17 - F20 (20 tests)
├── Tier2/                          # Boundary & Corner Cases (>=5 test cases per feature)
│   ├── MasterDataBoundaryTest.php          # F01 - F06 boundaries (30 tests)
│   ├── TransactionalBoundaryTest.php       # F07 - F11 boundaries (25 tests)
│   ├── PosBoundaryTest.php                 # F12 - F16 boundaries (25 tests)
│   └── AdversarialBoundaryTest.php         # F17 - F20 boundaries (20 tests)
├── Tier3/                          # Cross-Feature Combinations (Pairwise interactions)
│   └── CrossFeatureCombinationsTest.php    # 16 pairwise integration tests
└── Tier4/                          # Real-World Application Scenarios (Store workflows)
    └── RealWorldStoreScenariosTest.php     # 5 complete real-world store workflows
```

### Tier Descriptions:
1. **Tier 1 — Feature Coverage**: Validates observable functionality for every feature F01 through F20 with at least 5 distinct test cases per feature (100 tests).
2. **Tier 2 — Boundary & Corner Cases**: Stresses zero values, negative numbers, maximal bounds, empty datasets, duplicate constraints, and adversarial injections across F01 through F20 (100 tests).
3. **Tier 3 — Cross-Feature Combinations**: Exercises pairwise domain intersections such as stock adjustment + POS sale race conditions, shift opening + cash payments, quote + customer price list tiering, and warehouse multi-inventory isolation (16 tests).
4. **Tier 4 — Real-World Application Scenarios**: Replicates complete, realistic multi-step store workflows:
   - Scenario 1: Mostrador (Caja 1) order generation -> Caja Central (Caja 3) payment confirmation -> Despacho delivery.
   - Scenario 2: Morning shift opening ($150,000 COP base), daily transactions, and evening cash arqueo with over/short reconciliation.
   - Scenario 3: B2B commercial quote creation with "Constructor" tiered pricing, PDF review, and conversion to credit sale.
   - Scenario 4: Warehouse physical recount, inventory surplus/shortage discovery, and atomic Kardex adjustment.
   - Scenario 5: Heavy materials supplier procurement, weighted average cost (CPP) recalculation, and retail sale.

## 5. Test Isolation & Determinism Standards
- Every test uses `RefreshDatabase` ensuring a pristine in-memory schema per test method.
- Role and permission definitions are initialized via `PermissionSeeder` in `beforeEach()`.
- Financial calculations use explicit centavos / decimal rounding (`round($val, 2)`).
- Time-dependent tests use Carbon helpers (`now()`, `addDays()`, `subHours()`).
- Eager loading assertions enable `DB::enableQueryLog()` and verify upper bounds on query counts.
