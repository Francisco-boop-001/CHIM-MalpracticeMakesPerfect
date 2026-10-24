# Malpractice Makes Perfect — rename verification

2026-10-24. Approved display name: **Malpractice Makes Perfect**. Exact tagline: **Unlicensed character development**.

The web title/header, sign-in copy, editor status/undo text, provisioning prompts, PRISMA tab/panel and package branding use the new name. Existing `brainwash` API operations, permission scope, NPC data namespace, module/CSS identifiers and private-config paths are preserved. No migration or behavior change was introduced. The renamed TEST pair is plugin0.1.0-dev.2 / Department0.1.0-prealpha.11.

## Deliverable

`K:/ActorwrightExchange/artifacts/malpractice-rename-20261024/package-output/MalpracticeMakesPerfect-0.1.0-dev.2-WorldOfDramaLlama-0.1.0-prealpha.11-TEST.zip`

583,188 bytes; SHA-256 `66a85ee031f98d7f65344a159fd24722a1fb927409010d9fc0019a485032d621`.

The prior Brainwash TEST archive remains intact. The new bundle contains the same tested native DLL (SHA-256 `1451c64dc8285be897d6eeac8754980a0b90a3228f75c8780d2f5566aeacb4c9`), ESP/scripts/SEQ/INI/notices and complete PCV companion. All8 generated PCV overlay files are unchanged. Only the two server candidates and relevant UI/branding assets changed. Native source was not changed, so no native rebuild or repeated native suite was needed.

## Executed checks

- The focused PRISMA and archive-branding expectations failed against old copy, then passed after the rename (`ui-red.txt`, `package-red.txt`, `ui-green.txt`, `package-green.txt`).
- Full browser functional suite:50/50. Shared editor state/reconciliation:5/5. Web auth helpers:5/5. Package builder:4/4. All7 server PHP files passed syntax checks.
- Department contract:77 checks, including exact renamed-owner version and source-pin compatibility with the installed candidate.
- Real installed web page: HTTP200, exact title/H1/tagline, no visible old name, same-origin security header retained. Screenshot `web.png`.
- PRISMA browser fixture: exact full tab name/tagline and existing operation/reset/auth behavior. Screenshot `prisma.png`. Both screenshots were visually inspected; this is browser evidence, not in-game acceptance.
- ZIP audit: member paths, CRC, inner/outer versions, filename stems, LF checksums, unchanged base assets, exact shared-editor copies and unchanged DLL all pass.
- Both DWPkgs passed the actual TEST2 package consumer and installed through its ordinary manager. Readback matches all11 plugin and27 Department payload files exactly.
- Fresh focused independent review found no source/package blockers. Its README link/display-wording notes were corrected. Source hashes, UTF-8, namespace stability and package identities were reviewed.

Evidence directory: `K:/ActorwrightExchange/artifacts/malpractice-rename-20261024/`. Primary receipts: `deliverable-audit.json`, `test2-install.json`, `installed-readback.json`, `integration-freeze.json`, `web-branding.json`, `ui-functional.json` and the named test logs.

## Preservation and concurrent TEST2 activity

31 protected core files,205 unrelated extension files, restore-lock metadata and generation, and original current-player/playthrough metadata match the fresh pre-update baseline. Exact old extension payloads were archived in `extensions-before.tar.gz`; the normal installer also created backups. No live/game/core-file writes, NPC edits, settings edits or provider calls were performed by these rename checks.

The broad database preservation comparison did **not** pass: `core_npc_master` and `general_settings` changed during concurrently running Cammy tests. Process inspection found `stage_cammy12c.sh`, `stage_cammy12_plain.sh`, Apache and additional CHIM workers started outside this task; a bounded source check confirmed those scripts contain UPDATE statements targeting both tables, without exporting values. Evidence: `preservation-after.json` and `concurrent-runtime.json`. Their processes/data were left alone; no rollback or artificial passing baseline was substituted. This task therefore makes no claim that all TEST2 database contents stayed unchanged during the rename window.

Only this task's loopback worker was stopped after the branding check. Other TEST2 services remain as found. Live `DwemerAI4Skyrim3` was never executed by this task; final distro status is recorded separately.

## Limits

No Skyrim run, new gameplay/save/restore validation or paid-model evaluation was performed. Existing server behavior was preserved and relevant focused suites rerun; prior deeper implementation evidence remains in `docs/verification.md`. Use the rebuilt archive only in a disposable MO2 profile/save, replacing the older Department test mod to avoid duplicate enabled versions.
