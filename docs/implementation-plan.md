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

## Task 1 — Brainwash server and shared web editor (server worker)
Files: server/runtime.php, service/store helpers as necessary, context_pre.php, manifest.json, index.php, api.php, server/assets/editor.js/css; tests/*.
Interfaces: the exact operation, response, PHP entry and component contracts in implementation-contract.md.
- [ ] Write and run failing service/PG/auth/editor tests with literal expected behaviors.
- [ ] Implement minimal transaction, scope, generation and auth paths; no model calls in tests.
- [ ] Run complete plugin checks, lint and report red/green evidence and remaining issues.

## Task 2 — Department PRISMA/native integration (Department worker)
Files: server/adapters/brainwash.php, server/runtime.php, server/lib/{protocol,auth,dispatcher}.php, ui/{app.js,index.html,app.css}, native/src/Contract.cpp and focused tests. Canonical editor assets synchronized by coordinator.
Interfaces: use the fixed Brainwash dispatch/component contracts; no changes to plugin source.
- [ ] Write and run failing API/native/UI contract tests.
- [ ] Add new explicit scope, capability, operations, fifth tab and reset behavior.
- [ ] Run relevant full existing tests and report evidence; defer exact Brainwash hashes until frozen.

## Task 3 — Integration, TEST2 deployment and deliverables (coordinator)
Files: docs/verification.md, scripts/build-package.ps1 or standard platform tools, tests/integration fixture, tasks/todo.md.
- [ ] Review both implementations, fill exact source pins, synchronize shared assets and check drift refusal.
- [ ] Run combined real PostgreSQL and HTTP/browser paths on a unique TEST2 database with no provider routes.
- [ ] Build actual updated Department DLL using pinned retained dependency cache; execute host tests.
- [ ] Package server DWPkg and MO2-test bundle with matching UI/DLL; install only TEST2 server extensions after preimage capture, preserving private settings/state.
- [ ] Independent fresh review; resolve significant findings, rerun affected checks, verify live/core inputs unchanged and report exact limits.

Review: pending. Native tool could not create worktree because this chat's root is not a Git repository; manual worktree created against the actual Department repository. Parallel workers own disjoint source trees and share only this fixed contract. Coordination with Dreamworld confirmed separate database/port ownership.
