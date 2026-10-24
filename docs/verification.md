# Brainwash TEST candidate â€” verified 2026-10-24

Delivered Brainwash `0.1.0-dev.1` with Department `0.1.0-prealpha.10`. The matched MO2 archive is `K:/ActorwrightExchange/artifacts/brainwash-build-20261024/package-output/Brainwash-0.1.0-dev.1-WorldOfDramaLlama-0.1.0-prealpha.10-TEST.zip` (582,950 bytes; SHA-256 `db5821870f0d30d95490d1b2b6f1479531fa8d80c259a71da88b7284c58cfce6`). This is a local TEST candidate, not a published release.

The web page and fifth PRISMA tab share the same editor and server dispatcher. Select narrative fields or separate fictional beliefs, create a manual or AI draft, review before/after values, accept a subset, apply, and narrowly undo the last change. Drafts never update native narrative fields. Apply/undo snapshot and update atomically, preserve other plugin namespaces, and reject changed identity, revision, timeline, or authorization. Unknown mutation responses lock editing until explicit readback; no automatic retry occurs.

## What was executed

| Check | Result | Evidence |
| --- | --- | --- |
| PostgreSQL service on reserved owned database | 17/17 | `tests/postgres.php`, `docs/server-implementation.md` |
| Web security helpers / editor state tests | 5/5 + 5/5 | `tests/web_auth.php`, `tests/editor.test.js` |
| Department payload/auth/replay/pins | 77 checks | Department `docs/BRAINWASH-IMPLEMENTATION.md` |
| Existing Department foundation / UI | 162; 50/50 + interactions1/1 | Department handoff |
| Actual updated DLL build / all native host tests | 5/5 | `native-build-catalog-fixed.txt`, `native-tests-catalog-fixed.txt` |
| Real HTTP login/session/origin/CSRF/catalog | 7/7 | `http-smoke.json` |
| Real Edge web workflow, source and installed bytes | 12/12 each | `web-workflow-source.json`, `web-workflow.json` |
| Installed Department facade + CLI-issued scoped auth + installed Brainwash owner | PASS | `installed-department.json` |
| Every pinned installed-source file drift + unknown version | PASS | `pin-drift.json` |
| Package builder / ZIP/checksum/payload audit | 4/4; PASS | `package-overlay-green.txt`, `deliverable-audit.json` |
| Normal CHIM package manager validation and TEST2 activation | Both completed | `test2-install.json` |

Unless otherwise noted, evidence files are under `K:/ActorwrightExchange/artifacts/brainwash-build-20261024/`. The 12 browser checks include search past the first 64 NPCs; draft isolation; literal rendering of HTML-like content; selective apply; native/belief undo; and an apply whose server response was deliberately dropped, followed by receipt reconciliation without retry. The review screenshot `web-review.png` was visually inspected.

The installed facade check called the installed Department API, auth, runtime and Brainwash files with isolated test credentials/journal. It exercised bootstrap, catalog, card, draft, apply, same-ID replay, undo, and refusal to replay an apply after undo. This is actual server integration proof. Native contracts and browser PRISMA behavior passed separately; it is not a witnessed in-Skyrim run.

A fresh independent reviewer checked transaction/identity/auth/replay/size changes, source pins, shared-asset identity and the final package-builder delta. The reviewer independently executed editor tests and extra maximum-size/unresolved-readback probes; core PHP/database paths were reviewed statically. Concrete findings were fixed and affected checks rerun. The real browser caught the no-referrer/opaque-Origin login defect; `Referrer-Policy: same-origin` fixed it while null-Origin rejection remains enforced.

## Isolation and final server state

Only `DwemerAI4Skyrim3-test2` was executed. Live `DwemerAI4Skyrim3` remained stopped. TEST2 core revision was `468584f7bfc34c2eaf76e6188efaf3294f4fd63c`; Department source started at `089067dc3467064e934e5aa1cb217c4a55cad690` in an isolated worktree. Neither primary Department checkout nor installed game files were changed.

TEST2 initially had no active playthrough or current character identity, so the normal runtime correctly refused requests. End-to-end tests used an explicitly owned temporary metadata profile/schema and one locked, non-dynamic synthetic NPC (`npc:2147483001`). TEST2's existing drama worker was paused only during this fixture and resumed as the same PID 723 afterward. No save switch or restore was performed, and shared generation/work.lock contents were not changed.

The synthetic NPC, its 14 own post-floor history rows, temporary profile/schema and current-character row were removed. Original profile/current-player metadata hashes match exactly. All 153 original NPC rows, 31 pinned core files, unrelated installed extension files, general settings, and work.lock ownership/mode/inode match their preimages. The original game AIAgent.dll still hashes to `1d6ac28f28f778041f016328743ffc91fbbb3717214c2e9e87dc23db7c464e01`. Receipts: `runtime-context-cleanup.json`, `preservation-after.json`, `preservation-extra-after.json`.

The two candidate extensions remain normally installed on TEST2 (11 Brainwash / 27 Department files), byte-identical to the DWPkgs. Exact prior Department source is preserved in `test2-department-before.tar.gz` and by the normal manager's backup. Existing private production settings were preserved. The loopback test HTTP worker was stopped; temporary plaintext passphrase/hash and test pairing/state files were removed. A private synthetic journal and isolated test database remain for evidence; they are not production credentials/state.

The archive preserves the base ESP/SEQ/PEX/INI/notices, the complete PCV companion prealpha.9 DWPkg, and all 8 generated PCV overlay files byte-for-byte. New DLL and UI are matched by hashes. No game/MO2 installation, release publication, push, or provider call occurred.

## Explicit limits

No Skyrim launch, real PRISMA focus/input/audio, save/load or real restore transition was tested. Same-ID restore invalidation was tested through isolated generation/scope fixtures; the shared TEST2 generation file was only read. AI generation is wired to the configured CHIM Profile Tasks connector, with deterministic injected-output validation tests; paid/provider model quality and latency were not tested.

Web ingress is capped at 32KB serialized JSON, and owner replies at 60KB before commit inside the existing 64KB Department envelope. Escaping-heavy or oversized cards/drafts are explicitly refused rather than truncated. Core compatibility and owner source hashes fail closed after unsupported upgrades.

The unrelated 4,100-operation journal retention/fsync stress was stopped during baseline diagnosis and is not counted as passing. Changed replay behavior, bounded journal guard and actual installed replay/undo were tested. Department UI render generated 11 captures under `K:/ActorwrightExchange/worktrees/evidence/implementation`; that inherited out-of-worktree destination was identified and left intact.

TEST2 has returned to its original no-active-playthrough state. Normal web use therefore requires a valid active CHIM playthrough, the usual HTTP service and a newly provisioned private passphrase; the temporary test context/credentials are deliberately gone. Use the archive only in a disposable MO2 profile/save until in-game acceptance is performed.
