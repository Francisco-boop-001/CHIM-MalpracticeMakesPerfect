# Brainwash

A CHIM server plugin for drafting, reviewing, selectively applying and narrowly undoing NPC profile changes. One editor serves the authenticated web page and the Brainwash tab in World of Drama-Llama's PRISMA interface. AI drafts use CHIM's configured Profile Tasks connector; manual drafts do not call a model. Fictional beliefs remain separate from native biography.

Current local TEST pair: Brainwash 0.1.0-dev.1 and Department 0.1.0-prealpha.10. The matched archive and executed checks are documented in `docs/verification.md`. No Skyrim acceptance is claimed.

Use the combined TEST ZIP through MO2 in a disposable profile/save, replacing the prior Department test mod rather than enabling duplicate copies. It contains the matching DLL/UI and ordinary CHIM server-plugin packages. It preserves existing ESP/scripts/notices and the PCV companion package; the four owner plugins remain separate requirements. Do not copy this candidate to a live server.

On an installed CHIM server the web page is `ext/brainwash/index.php`. Provision a private passphrase from the trusted CLI using `ext/brainwash/bin/provision-passphrase.php`; see `docs/server-implementation.md`. There is no default credential. A valid active CHIM playthrough is required. Refresh the normal PRISMA connection to receive the new explicit Brainwash permission; old credentials do not gain it implicitly.

Sources: `server/` and `tests/`; Department integration lives in `K:/ActorwrightExchange/worktrees/brainwash-department-20261024`. Package builder: `packaging/build_test_bundle.py`. All test fixtures and deployment were confined to TEST2; live/game inputs were preserved.
