# Malpractice Makes Perfect

*Unlicensed character development*

A CHIM plugin for drafting, reviewing, selectively applying and narrowly undoing NPC profile edits. The web page and the Malpractice Makes Perfect tab in World of Drama-Llama share one editor and server dispatcher. Manual drafts do not call a model; AI drafts use CHIM's configured Profile Tasks connector. Fictional beliefs remain separate from native biography.

## Download

[Download the dev.4 TEST prerelease](https://github.com/Francisco-boop-001/CHIM-MalpracticeMakesPerfect/releases/tag/v0.1.0-dev.4). The standalone CHIM server package is [0.1.0-dev.4.dwpkg](https://github.com/Francisco-boop-001/CHIM-MalpracticeMakesPerfect/releases/download/v0.1.0-dev.4/0.1.0-dev.4.dwpkg). For the in-game interface, use a [Department release](https://github.com/Francisco-boop-001/CHIM-DepartmentOfQuestionableDecisions/releases) that explicitly includes this owner version. The older dev.3/Department prealpha.12 combined ZIP does not contain this fix. Use one matching installation source and replace older enabled copies.

Skyrim SE/AE, matching SKSE64 and Address Library, PRISMA UI, SkyUI, CHIM and the supported owner plugins remain required; follow the matching Department release requirements.

## Use

On the CHIM server, open `ext/brainwash/index.php`. The editor opens directly; there is no separate plugin login or passphrase to configure. A settled ordinary CHIM home works without activating a managed save: there must be no active managed profile, automatic switching must be off, and no switch may be pending. In this mode Brainwash uses the current public CHIM data, which is shared across save slots; it does not add per-save isolation. An active managed profile remains authoritative even when automatic switching is off. Access follows the CHIM server: anyone who can reach this page can edit NPCs, so use the same network access controls as the rest of your server. See [server setup](docs/server-implementation.md).

In PRISMA, refresh the normal Department connection to receive the explicit Malpractice Makes Perfect permission (`brainwash` scope); older credentials do not gain it implicitly. Select an NPC, choose fields, create a draft, review and accept the wanted changes, then apply. Undo restores only the last accepted fields and refuses conflicting later edits. If a response is lost, use Refresh to reconcile rather than resend it.

The legacy `brainwash` storage/API/scope identifiers remain stable. The new display name requires no migration.

## Source and verification

[Release notes and checksums](https://github.com/Francisco-boop-001/CHIM-MalpracticeMakesPerfect/releases/tag/v0.1.0-dev.3) - [matched Department source](https://github.com/Francisco-boop-001/CHIM-DepartmentOfQuestionableDecisions/tree/v0.1.0-prealpha.12).

Plugin source is in `server/`, checks in `tests/`, and the deterministic paired-package builder in `packaging/`. The source ZIP contains this tagged plugin; the matched Department source is separately tagged in its repository. [Direct-access verification](docs/direct-access-verification.md) records the current candidate. [Rename verification](docs/malpractice-rename-verification.md) records the earlier dev.2 candidate. [Earlier implementation verification](docs/verification.md) distinguishes server/browser/native proof from the remaining in-game gate. Local evidence paths in those historical records identify retained test artifacts, not extra installation downloads.
