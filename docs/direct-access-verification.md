# Direct editor access — TEST verification

2026-10-24. Plugin 0.1.0-dev.3 paired with Department 0.1.0-prealpha.12. This corrects the unwanted separate web login in dev.2. Opening `ext/brainwash/index.php` immediately mounts the editor. No passphrase, password file, CLI provisioning or environment change is required. The old provisioning script and login/logout controls are removed.

The API retains POST/JSON validation, exact Origin checking, cross-site fetch rejection, a random session-bound CSRF token and the final session-token fence. There is no plugin-specific user authentication: anyone who can access this administration endpoint can edit NPCs, under the same access boundary as the CHIM server. PRISMA retains its normal Department connection and explicit scope; only its exact owner pins and shared CSS changed. No native/game behavior changed.

## Evidence

Retained artifacts: `K:/ActorwrightExchange/artifacts/malpractice-direct-access-20261024`.

- The no-credentials render regression failed before the fix and passes afterward. Web helper suite: 5/5, including automatic editor/session creation, wrong-token and cleared-session refusal, exact origins and bounded JSON. All eight server/test PHP files lint clean.
- Real Edge browser on the installed TEST2 extension: 13 direct-access/request-protection checks pass. Missing/wrong CSRF and a genuine browser cross-origin form POST are rejected. A token from another browser session is refused. No private password file was configured.
- The unseeded catalog returns unavailable because no active playthrough is selected; this is a readiness requirement, not a login gate. A narrowly owned temporary NPC/active-profile fixture then passes the installed no-login authoring workflow: 12/12 for draft isolation, selective apply, narrow undo, separate beliefs and lost-response reconciliation. Screenshots: `web-editor.png`, `web-review.png`.
- Shared-editor state tests: 5/5. Guarded PostgreSQL service suite: 17/17 in the reserved synthetic database with networking disabled. No real model call was made.
- PRISMA browser suite: 50/50, plus the focused Malpractice tab/transport/reset contract. The initial two runs were 49/50 because NATIVE_REFUSAL_FIXTURE was unset; supplying the existing hash-pinned native-producer output made all 50 pass. The failed harness run is retained as `ui-functional-rerun.json`.
- Department source/operation/scope/drift contract: 76 checks pass (the removed provisioner no longer contributes a drift-check case). Seven current PHP/manifest source pins are exact.
- Deterministic package-builder suite: 4/4. ZIP member/CRC/checksum/version-stem audit passes; shared-editor copies match. The actual CHIM package consumer accepted both DWPkgs, and the ordinary manager installed them only on TEST2. Readback matches all 10 plugin and 27 Department files. The old installed provisioner is absent.

## Package

`MalpracticeMakesPerfect-0.1.0-dev.3-WorldOfDramaLlama-0.1.0-prealpha.12-TEST.zip`

579,788 bytes. SHA-256: `c20f752f1a63483886bb528f07ae536129e3f6ee10f3d964524c404386efd704`.

The DLL is unchanged: `1451c64dc8285be897d6eeac8754980a0b90a3228f75c8780d2f5566aeacb4c9`. ESP, scripts, SEQ, INI, notices, complete PCV companion and all eight PCV overlay files are preserved. No native rebuild was needed. Replace the older paired TEST mod rather than enabling both versions.

## Preservation and limits

Final TEST2 comparison passes for 31 core files, 205 unrelated extension files, NPC data, settings, player metadata and profile metadata. The temporary synthetic NPC and its 12 owned history rows were removed; original metadata was restored and the previously running SAS worker resumed. Both owned loopback workers were stopped and temporary session directories removed. Live DwemerAI4Skyrim3 was not executed or modified; host inventory still shows it stopped. No game/MO2 changes, provider requests or catalog submission were performed.

Audit: traced every web authentication caller before removal, reviewed the full web/API/session diff and PRISMA pins, verified request rejection and successful final writes, and checked exact installed/package bytes and original-state restoration. No unresolved source/package issue found. Skyrim execution, save/restore acceptance and successful real-model AI drafting remain unverified. This stays a TEST prerelease.
