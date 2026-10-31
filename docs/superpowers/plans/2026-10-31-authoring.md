# MMP real authoring implementation plan

> For agentic workers: use superpowers:subagent-driven-development. Steps use checkboxes.

**Goal:** Deliver the approved nearby-NPC and iterative AI-authoring correction in both web and PRISMA.
**Architecture:** Extend existing five owner operations and shared editor; use CHIM telemetry/audience helpers, retain review/apply/undo, update Department's strict boundary DTOs and pins.
**Tech Stack:** PHP, PostgreSQL, plain JS/CSS, existing C++ native contract, existing package tools.
**Spec:** docs/authoring-correction-design.md (exact wire/types/limits).

## Global Constraints
- No live/core/game/installed changes, no WSL/distro/database/provider calls in this phase.
- Preserve authored text, all current request guards and exact owner identity; no PCV prerequisite or automatic native edits.
- New owner version0.1.0-dev.5. No replacement of published assets or tags.
- One implementation agent owns MMP product files/tests; coordinator owns Department integration, plans/task notes and packaging/review.

## Review Focus
- Old/stale telemetry, duplicate names and unavailable roster cannot be called nearby.
- Every author edit reaches AI; only selected proposal fields can be changed.
- Event evidence obeys audience/delivery/privacy and cited IDs belong to supplied events.
- Actor/timeline switches and late replies cannot apply prior author's draft/conversation.
- All request/response bounds agree through web, PHP, JS, C++ native and archived shared assets.

### Task 1: MMP end-to-end authoring
Read docs/authoring-correction-design.md first. Ownership: server/**, tests/**, README.md, docs/implementation-contract.md, docs/server-implementation.md. Do not edit tasks/** or plan/design documents. You are not alone; preserve other edits. Do not delegate, commit, publish or access any runtime.
- [x] Write targeted failing tests for nearby parsing/freshness/unique identity; working-card/conversation inputs and bounds; evidence/privacy/citation validation; shared-editor nearby-first, AI refinement preserving edits and proposal-only state.
- [x] Run and retain meaningful RED before production changes.
- [x] Implement exact spec in existing runtime/service/shared editor; new pure helper module server/lib/authoring.php permitted. Keep existing transaction/fencing code; add provider-independent testable prompt/evidence functions.
- [x] Update existing PostgreSQL regression cases (source-only until runtime grant); old selective apply/replay/undo semantics preserved.
- [x] Run host native-PHP/helper/JS checks and regressions, inspect all callers, report any unmet gate plainly.
- [x] Update docs and owner dev5 manifest. Report exact DTO/exports, test commands/counts, changed files and caveats to artifacts/mmp-authoring-20261031/task1-report.md.

### Task 2: Department native/UI integration (coordinator)
Ownership: isolated Department server/adapters/brainwash.php, native/src/Contract.cpp, native/tests, ui/app.js, ui/brainwash-editor.*, corresponding server validation/tests and package pin metadata. Also repair the reproduced MMP transport omission and missing native write/capability classification in HttpTransport.cpp, Plugin.cpp and NativePolicy.* only as needed; avoid unrelated native behavior.
- [x] Add targeted failing native/JS/PHP validation cases for new optional catalog/authoring DTOs; keep strict legacy/invalid-key refusals.
- [x] Implement exact spec boundary validation, refresh source pins/shared assets after Task1 freeze, preserve host/game fence checks.
- [x] Run host contract/browser/package tests and offline DLL build if toolchain permits; inspect actual interface screenshots.

### Task 3: Review and delivery
- [x] Independent whole-change correctness/privacy/UI review and fixes; tests rerun only as affected.
- [x] Build isolated TEST artifacts, audit source/shared-assets/manifest/checksums, record runtime/provider/Skyrim limits precisely.
- [x] Update tasks/todo.md and review record; do not claim in-game or provider proof without a real run.

Review: source checks only per direct user selection. Independent review approved after one bounded fix wave. No server/DB/provider/game acceptance claimed. Local TEST archive audit follows; no publication/install is authorized by these checks.
