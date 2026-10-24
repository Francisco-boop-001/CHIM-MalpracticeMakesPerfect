# Malpractice Makes Perfect

*Unlicensed character development*

A CHIM plugin for drafting, reviewing, selectively applying and narrowly undoing NPC profile edits. The authenticated web page and the Malpractice Makes Perfect tab in World of Drama-Llama share one editor and server dispatcher. Manual drafts do not call a model; AI drafts use CHIM's configured Profile Tasks connector. Fictional beliefs remain separate from native biography.

## Download the TEST prerelease

[Download Malpractice Makes Perfect 0.1.0-dev.2 + Department 0.1.0-prealpha.11](https://github.com/Francisco-boop-001/CHIM-MalpracticeMakesPerfect/releases/download/v0.1.0-dev.2/MalpracticeMakesPerfect-0.1.0-dev.2-WorldOfDramaLlama-0.1.0-prealpha.11-TEST.zip)

Use this one combined ZIP through MO2 in a disposable profile/save with an isolated CHIM server. Replace the older Department test mod; do not enable duplicate versions or install a second copy of the same paired bundle. The ZIP contains the matching DLL/UI and ordinary CHIM server packages. Existing ESP/scripts/notices and the complete PCV companion package are preserved. Skyrim SE/AE, matching SKSE64 and Address Library, PRISMA UI, SkyUI, CHIM and the supported owner plugins remain required; see the [Department requirements](https://github.com/Francisco-boop-001/CHIM-DepartmentOfQuestionableDecisions/tree/v0.1.0-prealpha.11#requirements).

This is a functional TEST prerelease. Manual draft/review/selective-apply/undo and lost-response reconciliation passed end-to-end TEST2 checks. The renamed browser suite passed 50/50, and the updated server packages were installed and read back through CHIM's normal package manager. Actual operation inside Skyrim, save/restore acceptance and real-model AI drafting remain unverified. Keep it off the live profile/server.

## Use

On the CHIM server, open `ext/brainwash/index.php` after configuring the server's private passphrase with `ext/brainwash/bin/provision-passphrase.php`. There is no default password. A valid active CHIM playthrough is required. See [server setup](docs/server-implementation.md).

In PRISMA, refresh the normal Department connection to receive the explicit Malpractice Makes Perfect permission (`brainwash` scope); older credentials do not gain it implicitly. Select an NPC, choose fields, create a draft, review and accept the wanted changes, then apply. Undo restores only the last accepted fields and refuses conflicting later edits. If a response is lost, use Refresh to reconcile rather than resend it.

The legacy `brainwash` storage/API/scope identifiers remain stable. The new display name requires no migration.

## Source and verification

[Release notes and checksums](https://github.com/Francisco-boop-001/CHIM-MalpracticeMakesPerfect/releases/tag/v0.1.0-dev.2) - [matched Department source](https://github.com/Francisco-boop-001/CHIM-DepartmentOfQuestionableDecisions/tree/v0.1.0-prealpha.11).

Plugin source is in `server/`, checks in `tests/`, and the deterministic paired-package builder in `packaging/`. The source ZIP contains this tagged plugin; the matched Department source is separately tagged in its repository. [Rename verification](docs/malpractice-rename-verification.md) records the current candidate. [Earlier implementation verification](docs/verification.md) distinguishes server/browser/native proof from the remaining in-game gate. Local evidence paths in those historical records identify retained test artifacts, not extra installation downloads.
