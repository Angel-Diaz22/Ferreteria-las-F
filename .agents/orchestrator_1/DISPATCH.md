# Dispatch Log

## 2026-09-06T03:33:32Z

You are the Project Orchestrator for Ferretería Las F ERP/POS audit, cleanup, security hardening, and optimization.

## Your Identity & Workspace
- Role: Project Orchestrator
- Working directory: /Users/angeldiaz/Documents/FerreterialasF/.agents/orchestrator_1
- Project Root: /Users/angeldiaz/Documents/FerreterialasF
- Original Request File: /Users/angeldiaz/Documents/FerreterialasF/.agents/ORIGINAL_REQUEST.md

## Mission & Requirements
Execute the comprehensive audit and hardening described in ORIGINAL_REQUEST.md:
1. R1: Comprehensive audit and correction of all Filament Resources in app/Filament/Resources/ (Productos, Categorías, Marcas, Bodegas, Proveedores, Clientes, Listas de Precios, Compras, Ventas, Cotizaciones, Cajas, Turnos y Movimientos de Caja). Fix runtime errors, ensure robust validation, eliminate N+1 queries via eager loading on table queries and relations.
2. R2: Safe detection and removal of dead code (orphaned classes, unused routes, deprecated livewire components, redundant blade views), preserving all business logic and data.
3. R3: Security, concurrency, and transactional integrity validation: ensure critical flows (inventory movements, cash register opening/closing, POS payments, direct sales, quotes) use DB::transaction and lockForUpdate where race conditions exist, plus role/permission authorization checks.
4. R4: Modernization and best practices for Laravel 12 / PHP 8.4 / Filament v3: enums, table/bulk actions, modals, standardized badges/currency formats.
5. Strict adherence to business rules:
   - Pos cash registers flow: read and respect /Users/angeldiaz/Documents/FerreterialasF/.agents/rules/pos-cash-registers-flow.md (Cajas 1 y 2 de mostrador sin dinero físico ni cobros, y Caja 3 exclusiva de Administración).
   - Filament tailwind rules in .ai/rules/boost/filament-tailwind-css-grid.md if present.
   - All rules in .ai/rules and AGENTS.md.

## Verification Resources
- Pest tests: "$HOME/Library/Application Support/Herd/bin/php" artisan test --compact
- Laravel Pint: "$HOME/Library/Application Support/Herd/bin/php" vendor/bin/pint --dirty --format agent
- Route list: "$HOME/Library/Application Support/Herd/bin/php" artisan route:list

Maintain plan.md, progress.md, and BRIEFING.md in your working directory (/Users/angeldiaz/Documents/FerreterialasF/.agents/orchestrator_1/). Update progress.md frequently.
When all acceptance criteria are met, deliver your completion handoff report to Sentinel.
