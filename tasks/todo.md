# Brainwash Implementation Plan

> For agentic workers: use focused parallel agents for the independent server and Department domains under their shared immutable implementation-contract.md. Coordinator integrates and reviews. Steps use checkboxes.

Goal: deliver manual/AI draft, field-level review, guarded apply and narrow undo through an authenticated web page and Department PRISMA tab, built and tested only on TEST2.
Architecture: one Brainwash PostgreSQL service owns draft/apply/undo and beliefs; both UI routes use it. One dependency-free editor component serves both UIs. Existing Department auth/transport/restore fences remain intact.
Tech stack: PHP8.2/PostgreSQL15, plain JS/CSS, existing C++/SKSE/PRISMA Department build.
Spec: docs/implementation-contract.md and ../../artifacts/brainwash-feasibility-20261024/report.md. The user explicitly selected and authorized execution of this design in the current chat.

## Global constraints
- TEST2 only, no live/core/game writes or paid provider calls; no automatic growth or new daemon.
- Preserve all existing owner pins and other plugin state; no broad cleanup or new dependencies.
- Apply/undo atomic and scoped, stale drafts refused, HTML text escaped, no silent truncation.
- Separate server/browser/native build proof from witnessed Skyrim acceptance.

## Review focus
- Same-ID restore after a draft: scope must include restore generation, not just profile ID.
- Unknown mutation result: reconciliation locks editing rather than retrying blindly.
- Draft current-field race: model work outside row lock, revalidation before draft save/apply.
- Undo with unrelated changes: only touched fields restored; overlapping changes refused.
- Existing Department credentials without new scope: denied, ordinary refreshed connection grants it explicitly.

## Task 1 â€” Brainwash server and shared web editor (server worker)
Files: server/runtime.php, service/store helpers as necessary, context_pre.php, manifest.json, index.php, api.php, server/assets/editor.js/css; tests/*.
Interfaces: the exact operation, response, PHP entry and component contracts in implementation-contract.md.
- [x] Write and run failing service/PG/auth/editor tests with literal expected behaviors.
- [x] Implement minimal transaction, scope, generation and auth paths; no model calls in tests.
- [x] Run complete plugin checks, lint and report red/green evidence and remaining issues.

## Task 2 â€” Department PRISMA/native integration (Department worker)
Files: server/adapters/brainwash.php, server/runtime.php, server/lib/{protocol,auth,dispatcher}.php, ui/{app.js,index.html,app.css}, native/src/Contract.cpp and focused tests. Canonical editor assets synchronized by coordinator.
Interfaces: use the fixed Brainwash dispatch/component contracts; no changes to plugin source.
- [x] Write and run failing API/native/UI contract tests.
- [x] Add new explicit scope, capability, operations, fifth tab and reset behavior.
- [x] Run relevant full existing tests and report evidence; defer exact Brainwash hashes until frozen.

## Task 3 â€” Integration, TEST2 deployment and deliverables (coordinator)
Files: docs/verification.md, scripts/build-package.ps1 or standard platform tools, tests/integration fixture, tasks/todo.md.
- [x] Review both implementations, fill exact source pins, synchronize shared assets and check drift refusal.
- [x] Run combined real PostgreSQL and HTTP/browser paths on a unique TEST2 database with no provider routes.
- [x] Build actual updated Department DLL using pinned retained dependency cache; execute host tests.
- [x] Package server DWPkg and MO2-test bundle with matching UI/DLL; install only TEST2 server extensions after preimage capture, preserving private settings/state.
- [x] Independent fresh review; resolve significant findings, rerun affected checks, verify live/core inputs unchanged and report exact limits.

Review: completed; see docs/verification.md for direct evidence and limits. Native tool could not create worktree because this chat's root is not a Git repository; manual worktree created against the actual Department repository. Parallel workers own disjoint source trees and share only this fixed contract. Coordination with Dreamworld confirmed separate database/port ownership.

Completion review: both implementations and the narrow packaging fix were independently reviewed; concrete findings fixed and affected checks rerun. Real TEST2 install/browser/facade tests passed; original data/core/live/game preserved. Temporary context and credentials removed, background worker resumed. Skyrim acceptance and paid model quality remain separate unperformed gates.

## Malpractice Makes Perfect rename — 2026-10-24
- [x] Update visible web/shared-editor/PRISMA branding and exact tagline; preserve brainwash storage, API and permission identifiers.
- [x] Advance TEST identities to plugin0.1.0-dev.2 / Department0.1.0-prealpha.11; refresh owner pins and shared assets.
- [x] Run focused web/PRISMA and package checks; reuse the unchanged tested DLL; build a new named archive and preserve the prior one.
- [x] Validate/install only TEST2, inspect rendered branding and preservation, complete independent review and handoff.
Review: complete; focused independent review clear, installed branding and relevant suites pass. Concurrent Cammy data changes prevented a global DB-preservation claim; recorded without altering their work. Full plan: docs/superpowers/plans/2026-10-24-malpractice-rename.md.

## Direct editor access — 2026-10-24
Goal: fresh downloads open the editor without a plugin passphrase, while same-origin/CSRF, transaction and PRISMA connection fences still work. TEST2 only; preserve live server and published assets.
- [x] Reproduce the login gate with a failing no-credentials editor test.
- [x] Remove plugin login/provisioning and retain session-bound request fencing; update setup instructions and record the lesson.
- [x] Advance plugin/Department TEST versions and exact owner pins; verify web and PRISMA checks.
- [x] Build a fresh paired TEST package, verify/install only TEST2, capture the direct editor, audit and document limitations.
Review: complete. Installed no-login workflow 12/12; request checks 13/13; PRISMA 50/50; database 17/17. TEST2 state restored and exact package/readback verified. See docs/direct-access-verification.md.
