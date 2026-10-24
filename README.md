# Malpractice Makes Perfect

*Unlicensed character development*

A CHIM plugin for drafting, reviewing, selectively applying and narrowly undoing NPC profile edits. The web page and the Malpractice Makes Perfect tab in World of Drama-Llama share one editor and server dispatcher. Manual drafts do not call a model; AI drafts use CHIM's configured Profile Tasks connector. Fictional beliefs remain separate from native biography.

## Download the TEST prerelease

[Download Malpractice Makes Perfect 0.1.0-dev.3 + Department 0.1.0-prealpha.12](https://github.com/Francisco-boop-001/CHIM-MalpracticeMakesPerfect/releases/download/v0.1.0-dev.3/MalpracticeMakesPerfect-0.1.0-dev.3-WorldOfDramaLlama-0.1.0-prealpha.12-TEST.zip)

This correction removes the separate plugin login and command-line passphrase setup. The editor opens directly through the ordinary CHIM server; PRISMA keeps its normal automatic connection.

Use this one combined ZIP through MO2 in a disposable profile/save with an isolated CHIM server. Replace the older Department test mod; do not enable duplicate versions or install a second copy of the same paired bundle. The ZIP contains the matching DLL/UI and ordinary CHIM server packages. Existing ESP/scripts/notices and the complete PCV companion package are preserved. Skyrim SE/AE, matching SKSE64 and Address Library, PRISMA UI, SkyUI, CHIM and the supported owner plugins remain required; see the [Department requirements](https://github.com/Francisco-boop-001/CHIM-DepartmentOfQuestionableDecisions/tree/v0.1.0-prealpha.12#requirements).

This is a functional TEST prerelease. Manual draft/review/selective-apply/undo and lost-response reconciliation passed end-to-end TEST2 checks. The current PRISMA browser suite passed 50/50; the installed no-login authoring workflow passed 12/12, and the updated server packages were installed and read back through CHIM's normal package manager. Actual operation inside Skyrim, save/restore acceptance and real-model AI drafting remain unverified. Keep it off the live profile/server.

## Use

On the CHIM server, open `ext/brainwash/index.php`. The editor opens directly; there is no separate plugin login or passphrase to configure. A valid active CHIM playthrough is required. Access follows the CHIM server: anyone who can reach this page can edit NPCs, so use the same network access controls as the rest of your server. See [server setup](docs/server-implementation.md).

In PRISMA, refresh the normal Department connection to receive the explicit Malpractice Makes Perfect permission (`brainwash` scope); older credentials do not gain it implicitly. Select an NPC, choose fields, create a draft, review and accept the wanted changes, then apply. Undo restores only the last accepted fields and refuses conflicting later edits. If a response is lost, use Refresh to reconcile rather than resend it.

The legacy `brainwash` storage/API/scope identifiers remain stable. The new display name requires no migration.

## Source and verification

[Release notes and checksums](https://github.com/Francisco-boop-001/CHIM-MalpracticeMakesPerfect/releases/tag/v0.1.0-dev.3) - [matched Department source](https://github.com/Francisco-boop-001/CHIM-DepartmentOfQuestionableDecisions/tree/v0.1.0-prealpha.12).

Plugin source is in `server/`, checks in `tests/`, and the deterministic paired-package builder in `packaging/`. The source ZIP contains this tagged plugin; the matched Department source is separately tagged in its repository. [Direct-access verification](docs/direct-access-verification.md) records the current candidate. [Rename verification](docs/malpractice-rename-verification.md) records the earlier dev.2 candidate. [Earlier implementation verification](docs/verification.md) distinguishes server/browser/native proof from the remaining in-game gate. Local evidence paths in those historical records identify retained test artifacts, not extra installation downloads.
