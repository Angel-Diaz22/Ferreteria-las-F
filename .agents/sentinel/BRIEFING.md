# BRIEFING — 2026-09-06T03:33:45Z

## Mission
Supervise and monitor the full audit, dead code cleanup, security hardening, and performance optimization of ERP/POS Ferretería Las F resources.

## 🔒 My Identity
- Archetype: sentinel
- Working directory: /Users/angeldiaz/Documents/FerreterialasF/.agents/sentinel
- Orchestrator: c4204e0d-d0ed-4151-add9-8a27df422772
- Victory Auditor: to be spawned on victory claim

## 🔒 Key Constraints
- No technical decisions — relay only
- Victory Audit is MANDATORY before reporting completion
- Must not write code or analyze problems
- Must run progress reporting cron and liveness check cron
- Must enforce verification of business rules (POS cash registers flow)

## User Context
- **Last user request**: Comprehensive audit, dead code cleanup, security hardening (concurrency/transactions), and performance optimization across all Filament resources and CRUDs.
- **Pending clarifications**: none
- **Delivered results**: none

## Project Status
- **Phase**: in progress
- **Route**: General (`teamwork_preview_orchestrator`)
- **Routing Rationale**: The request is a full-system engineering audit, cleanup, security hardening, and optimization touching multiple resources, files, and layers (ERP/POS CRUDs, dead code, concurrency, Filament v3). Fits General SWE orchestration.
- **Active Crons**:
  - Cron 1 (Progress Reporting, */8): task-16
  - Cron 2 (Liveness Check, */10): task-18

## Victory Audit Status
- **Triggered**: no
- **Verdict**: pending
- **Retry count**: 0

## Artifact Index
- /Users/angeldiaz/Documents/FerreterialasF/.agents/ORIGINAL_REQUEST.md — Authoritative record of user request
- /Users/angeldiaz/Documents/FerreterialasF/.agents/orchestrator_1/ — Working directory of Project Orchestrator
