# Orchestration Plan: Ferretería Las F ERP/POS Audit & Hardening

## 1. Objectives & Scope
Audit, clean up dead code, harden security and transactions, and optimize performance across all Filament v3 resources and POS workflows in `app/Filament/Resources/` and related domains, ensuring full compliance with:
- Business rules: POS Cash Register Flow (Cajas 1 & 2 no cash/cobro; Caja 3 Admin only with cash handling).
- Technical standards: PHP 8.4 / Laravel 12 / Filament v3 best practices, zero N+1 queries, pessimistic locking (`lockForUpdate`), `DB::transaction` atomicity, and clean Pest / Pint passes.

## 2. Execution Phases

### Phase 0: Survey & Discovery (Parallel Explorers)
- **Explorer 1 (Filament Resources & N+1)**: Map all 13+ resources in `app/Filament/Resources/`, their relations, table queries, missing eager loads, and form validations.
- **Explorer 2 (Dead Code & Route Audit)**: Identify orphaned controllers, unused routes, deprecated livewire components, and abandoned blade views.
- **Explorer 3 (Security, Transactions & POS)**: Audit `DB::transaction`, `lockForUpdate`, role authorizations in inventory movements, sales, purchases, cash opening/closing, and POS flows.
- **Synthesis**: Compile unified `PROJECT.md` with full architecture, feature inventory, milestones, and interface contracts.

### Phase 1: Dual Track Launch
- **Track A (E2E Testing Track)**:
  - Sub-orchestrator designs and implements automated opaque-box test suites (Tiers 1-4: Feature Coverage, Boundaries, Combinations, Real-world Scenarios).
  - Publishes `TEST_READY.md`.
- **Track B (Implementation Track)**:
  - Milestone M1: Master Data & Catalog Filament Resources (Productos, Categorías, Marcas, Bodegas, Proveedores, Clientes, ListasPrecio).
  - Milestone M2: Transactional Resources (Compras, Ventas, Cotizaciones).
  - Milestone M3: Cash Register & POS Flow Hardening (Cajas, Turnos, Movimientos, POS Terminal).
  - Milestone M4: Dead Code Elimination & Framework Modernization.

### Phase 2: Final Milestone & Hardening
- Run full E2E test suite (Tiers 1-4) against implemented codebase.
- Phase 2 Coverage Hardening: Adversarial Challenger (Tier 5) stress tests edge cases, concurrency, race conditions in stock and cash register balance.
- Forensic Auditor verification (Zero-tolerance integrity check).
- Pest test suite full pass (`"$HOME/Library/Application Support/Herd/bin/php" artisan test --compact`).
- Laravel Pint formatting pass (`"$HOME/Library/Application Support/Herd/bin/php" vendor/bin/pint --dirty --format agent`).

### Phase 3: Completion & Reporting
- Final handoff generation to Sentinel and human reporter.
