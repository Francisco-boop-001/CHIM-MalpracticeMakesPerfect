# Normal CHIM owner compatibility implementation plan

> For agentic workers: use superpowers:subagent-driven-development. Steps use checkboxes.

Goal: allow the existing no-login editor and Department operations when pinned CHIM reports settled ordinary/unprofiled public state, while preserving managed profiles and all lease/generation/revision/replay checks.
Architecture: extend only the owner factory's authoritative scope derivation. Ordinary public data remains shared by design; no save-slot isolation, migration or profile creation. Existing service/UI/native contracts are unchanged.
Tech: PHP/PostgreSQL, existing JS editor and Python package builder.
Spec: direct human approval verified in Department thread 01a15587-7327-7ea0-b152-4bc3bae9ca61, turn 01a17d2e-1cda-7193-80e7-e0f6f05a6d6b: Yes, coordinate and publish the necessary owner fix (GPT-6-LUNA/max, TEST only). Existing implementation-contract.md applies except its unconditional managed-profile requirement is superseded by this explicit correction.

## Global constraints
- NO WSL entry until Department grants an exclusive test window. Department is preparing fresh PG/private netns proof; do host-side preparation now.
- No live/core/running-game changes, model calls, migrations, settings writes, data resets or new storage semantics.
- Preserve published dev.3 and older releases. New owner version is 0.1.0-dev.4.
- Coordinator owns plan/task notes, review, candidate freeze/commit/package/release and cross-chat coordination. Worker owns only files listed under Task 1.
- Existing ordinary shared history is accepted. Existing valid managed profile remains authoritative even if automatic switching is off.
- No UI/CSS/DLL/service/context_pre.php changes. No source-pin weakening or request-supplied identity override.

## Review focus
- Reject unavailable/malformed/pending/contradictory home state rather than falling back.
- Inactive managed profiles may exist in ordinary mode and must not be edited or rejected.
- Current managed base hash must stay byte-identical; no global auto_switch==false restriction.
- Both base scope and generation are freshly derived and rechecked before writes/replay.
- Source-ready alone is not ordinary HTTP bootstrap or owner-factory evidence.

### Task 1: owner runtime and focused regression tests (LUNA/max)
Files owned: server/runtime.php, server/manifest.json, tests/runtime_scope.php (new if needed), tests/postgres.php, README.md, docs/server-implementation.md, docs/implementation-contract.md. Do not touch other files; no commit/push/publish. You are not alone; preserve others' work.
Read this entire task first. Baseline is published dev.3 commit 6cc94dc82a1b5d75cabd7cd46bbe5a4611777f61. Source root is this isolated worktree.
- [x] Write a focused failing test that exercises REAL brainwash_profile_scope using pinned core pth_state/ptp_product plus isolated PG. Do not prove the new factory branch by injecting a scope into BrainwashService only. Preserve a baseline test copy outside production files if the coordinator needs to execute it against dev.3.
- [x] Send root the exact test prerequisites/command and pause production edits until its RED run is confirmed. There is no host php on PATH; do NOT enter WSL. Department may execute the test in its private harness or grant a window.
- [x] Add the smallest ordinary-state branch in brainwash_profile_scope and set owner manifest dev.4. Derive hash('sha256', "qd-department-unprofiled-v1\0public") independently from authoritative state; existing owner scope remains hash('sha256', "brainwash-scope-v1\0" . base . "\0" . generation).
Normal predicate: available===true, valid list playthroughs, valid nonnegative active_id===0 after existing integer normalization, auto_switch===false, boolean/nonpending auto_switch_pending, no active choices; choices must not be malformed/duplicated/contradictory. Product meta must match pinned chim_meta. Existing inactive saved choices are allowed and remain untouched. Unknown/unavailable/pending state is never ordinary fallback. For active_id>0 preserve existing strict managed validation and base formula regardless of auto_switch flag; invalid managed state never falls back.
Keep existing shared work.lock/pause/controller/generation logic, connection/public search_path, service calls, context fencing, transactions and response validation untouched. Do not import Department helpers into the owner or accept its supplied scopeKey as authority.
- [x] Focused real scope cases: normal settled empty and inactive saved choices; managed auto on/off has old expected base; unavailable/missing/malformed home; pending; auto-on+id0; negative/missing ID; contradictory active choice; invalid/duplicate choices; bad managed character/schema/row. Retain raw failed run and preserve existing metadata in real PG fixtures.
- [x] Verify the owner scope/context boundaries with focused PostgreSQL and real Department HTTP managed-transition tests, then exercise selective apply, replay, undo and namespace preservation over actual HTTP. The unchanged broad service suite is not rerun; the paired coordinator accepted this bounded replacement. The optional test-only DSN override is available for future owned-cluster runs.
- [x] Update use/setup docs: no managed save setup required for settled ordinary mode; ordinary current public data is shared; managed users retain their configured behavior. No storage/migration/save-loss claim.
- [x] Self-review diff; report exact changes, test commands/results, unrun requirements and source hashes. Root will run independent review and freeze.

### Task 2: paired acceptance and immutable publication (coordinator)
- [x] Review worker code and real scope factory proof; preserve failure evidence. Host-only editor/package tests baseline first.
- [x] Coordinate Department test window/proof. Require real ordinary HTTP bootstrap/catalog/card/manual draft/selective apply/undo through owner factory without synthetic active profile. Preserve configured managed fixtures; test transitions, leases/generation and stale/replay refusal. No model calls.
- [x] Freeze dev.4 candidate/source hashes and exact DWPkg; share before publishing so Department owns updated pins/bundle. Department owns all-five actual HTTP proof and paired root review gate.
- [x] After paired gate: commit/tag immutable new owner source, clean-source rebuild/compare, publish verified TEST assets/checksums and verify downloads. Do not publish an old combined Department package or modify other owner's release assets.
- [x] Complete task notes and review evidence, report specific limits.

Review: focused owner GREEN02 and independent Department normal-five-final-13 accepted. Independent final whole-change review approved with no actionable findings. Clean tagged-source publication/download checks are tracked in the external release receipt. See docs/normal-chim-verification.md for exact authorities and limits.

Publication review: v0.1.0-dev.4 published from e203c0de17df0004e3643a42cafbf6a7e5368d58. Clean tagged export and documented standalone recipe both reproduce candidate-1. Authenticated draft and anonymous public downloads verify all three assets; older two releases and four tag refs unchanged. Immutable release/tag preserved; this later tracking-only commit completes the checklist. No live/game/provider changes.
