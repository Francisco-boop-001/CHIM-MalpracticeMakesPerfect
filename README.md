# Malpractice Makes Perfect

*Unlicensed character development*

A CHIM plugin for drafting, reviewing, selectively applying and narrowly undoing NPC profile edits. The web page and the Malpractice Makes Perfect tab in World of Drama-Llama share one editor and server dispatcher. Manual drafts do not call a model; AI drafts use CHIM's configured Profile Tasks connector. Fictional beliefs remain separate from native biography.

## Download

Night mode is the default. Use the header toggle for Paper mode; the editor remembers your choice when browser storage is available.

The dev.6 TEST prerelease adds an evidence desk to nearby-first, iterative card authoring. Choose **Creative rewrite** to direct the character yourself, or **Develop from experience** to request restrained changes from recorded events. Each AI-proposed field carries a reason and a basis: creative choice, inference, source-linked change, or unchanged. Experience-based changes require supplied source IDs; their acceptance starts unchecked. Genuine excerpts sit beside each proposed field. Source linkage does not prove that a claim follows from a record: review remains yours.

The shared web/PRISMA editor uses an ink-and-paper writing desk with a cast rail, current character summary, creative direction and field-specific findings. Manual editing stays available. All eight unsaved fields remain model context; unchanged decisions compare against the saved card, so unsaved fiction cannot bypass development checks. Latest apply receipts retain rationale and exact accepted fields. See [evaluation boundaries](docs/evidence-desk-evaluation.md).

Host checks: `php -n tests/authoring.php`, `node tests/editor.test.js`, and `php -n tests/web_auth.php`. `tests/authoring_sql.php` checks actual pinned helper SQL when `BRAINWASH_CAPTURED_CORE` points to captured CHIM sources. Updated PostgreSQL tests and pre-labelled model-evaluation cases remain unexecuted in this source-only phase. This candidate has not passed database, provider, installed-runtime or Skyrim acceptance; this remains a TEST prerelease. The matching Department source is prealpha.33 with native0.1.21.0; older DLLs do not understand the new evidence metadata.

[Download the dev.6 TEST prerelease](https://github.com/Francisco-boop-001/CHIM-MalpracticeMakesPerfect/releases/tag/v0.1.0-dev.6). For the in-game PRISMA interface, use the matching [Department prealpha.33 game ZIP](https://github.com/Francisco-boop-001/CHIM-DepartmentOfQuestionableDecisions/releases/download/v0.1.0-prealpha.33/WorldOfDramaLlama-0.1.0-prealpha.33.zip). The standalone [0.1.0-dev.6.dwpkg](https://github.com/Francisco-boop-001/CHIM-MalpracticeMakesPerfect/releases/download/v0.1.0-dev.6/0.1.0-dev.6.dwpkg) supplies the web/server plugin only. Use one matching installation source and replace older enabled copies; the old dev.3 combined ZIP does not contain these fixes. See [night-mode release notes](docs/night-mode-release.md).

Skyrim SE/AE, matching SKSE64 and Address Library, PRISMA UI, SkyUI, CHIM and the supported owner plugins remain required; follow the matching Department release requirements.

## Use

On the CHIM server, open `ext/brainwash/index.php`. The editor opens directly; there is no separate plugin login or passphrase to configure. A settled ordinary CHIM home works without activating a managed save: there must be no active managed profile, automatic switching must be off, and no switch may be pending. In this mode Brainwash uses the current public CHIM data, which is shared across save slots; it does not add per-save isolation. An active managed profile remains authoritative even when automatic switching is off. Access follows the CHIM server: anyone who can reach this page can edit NPCs, so use the same network access controls as the rest of your server. See [server setup](docs/server-implementation.md).

In PRISMA, refresh the normal Department connection to receive the explicit Malpractice Makes Perfect permission (`brainwash` scope); older credentials do not gain it implicitly. Select an NPC, choose fields, create a draft, review and accept the wanted changes, then apply. Undo restores only the last accepted fields and refuses conflicting later edits. If a response is lost, use Refresh to reconcile rather than resend it.

The legacy `brainwash` storage/API/scope identifiers remain stable. The new display name requires no migration.

## Source and verification

[Release notes and checksums](https://github.com/Francisco-boop-001/CHIM-MalpracticeMakesPerfect/releases/tag/v0.1.0-dev.3) - [matched Department source](https://github.com/Francisco-boop-001/CHIM-DepartmentOfQuestionableDecisions/tree/v0.1.0-prealpha.12).

Plugin source is in `server/`, checks in `tests/`, and the deterministic paired-package builder in `packaging/`. The source ZIP contains this tagged plugin; the matched Department source is separately tagged in its repository. [Direct-access verification](docs/direct-access-verification.md) records the current candidate. [Rename verification](docs/malpractice-rename-verification.md) records the earlier dev.2 candidate. [Earlier implementation verification](docs/verification.md) distinguishes server/browser/native proof from the remaining in-game gate. Local evidence paths in those historical records identify retained test artifacts, not extra installation downloads.
