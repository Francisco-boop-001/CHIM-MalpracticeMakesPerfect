# Brainwash server handoff

This note describes the server-side implementation in `server/` and its focused verification. The PHP service is the single owner of reads, drafts, apply, and narrow undo; the web page and Department adapter call the same dispatcher.

## Runtime integration

`server/runtime.php` exposes `brainwash_runtime_ready()` and the trusted entry point `brainwash_dispatch($operation, $payload, $context = [])`. It locates the core from an existing `ENGINE_PATH`, `QD_CHIM_ROOT`, or the installed flattened package layout. Before dispatch it verifies the pinned core sources, checks the existing playthrough runtime pause/lock state, and reads the current restore generation. It does not create a missing generation marker. A missing generation is the valid initial timeline value; a paused, contended, switched, or ambiguous runtime fails closed.

Dispatch uses the core's native `ptp_connect()` connection for profile state, connector configuration, NPC reads/writes, history, and the Brainwash namespace. The one connection is kept through each service transaction. Profile state must have exactly one available active playthrough, a valid character ID, a metadata schema, and a matching profile row. The Brainwash timeline scope contains the Department base scope plus restore generation. A supplied Department `scopeKey` is checked against the base hash `sha256("qd-department-scope-v1\0" . activeId . "\0" . schemaName . "\0" . characterId)`; it intentionally does not include restore generation.

Profile Tasks is opt-in. For `mode: "ai"`, the runtime reads `CORE_CONNECTOR_PROFILES` and its enabled flag from the current core DB, resolves that configured connector through CHIM's `LLMConnector`, and calls `fast_request()` once with the selected fields and current actor card. The model must return a bounded JSON object containing only selected string fields (and optional beliefs when selected). The service rejects extra keys, wrong types, unselected keys, oversized output, and non-JSON output. The connector call occurs outside the Department auth lock and before the final draft transaction. Tests inject a deterministic generator through the trusted PHP constructor; HTTP payloads have no generator/DB/scope override. No provider calls are part of the test suite.

The web and Department adapters may pass trusted-only `assertCurrent`, `withCurrentContext`, `withLockedCurrentContext`, and `beginCommit` callbacks. These are never copied from a request. Draft generation happens before the final locked transaction. The service rechecks scope, actor revision, and authorization before persisting a draft; apply and undo do the same inside their short locked transactions. It builds and validates the complete 60,000-byte-capped response DTO before committing, so an oversized result rolls the entire mutation back. The web adapter releases the PHP session while generation is in flight, then reopens and holds it only around the bounded final read/transaction. The Department adapter's locked callback supplies its authoritative connection/view fence.

The core compatibility pins currently cover `lib/playthrough_preferences.php`, `lib/playthrough_home.php`, `lib/playthrough_runtime.php`, `lib/core/database_schema/core_npc_master.sql`, and `lib/core/database_schema/core_npc_master_history.sql`. When the pinned source changes, review the direct SQL and runtime assumptions before updating pins.

## Web page and provisioning

The installed page is `index.php` (the package manifest's `config_url` points to `../ext/brainwash/index.php`); `api.php` accepts same-origin `POST application/json` requests with the envelope `{"operation":"brainwash.card.read","payload":{"actorKey":"npc:123"}}`. Supported operations are `brainwash.catalog.read`, `brainwash.card.read`, `brainwash.draft`, `brainwash.apply`, and `brainwash.undo`. Catalog accepts an optional literal substring `query` of at most 128 UTF-8 bytes and returns at most 64 matches.

The web page requires a local passphrase. It reads only `password_hash` from a private PHP config file outside both the plugin directory and document root. Default path: `/etc/chim/brainwash-auth.php`. The `BRAINWASH_PRIVATE_CONFIG` environment variable can select another absolute path for a test or custom install; its containing directory must not be group/other writable, and the config file must be a non-symlink regular file that is not group/other writable. There is no default credential. Provision interactively from a trusted CLI with the web user's account and no command-line secret:

```sh
sudo install -d -o www-data -g www-data -m 0700 /etc/chim
sudo -u www-data php /path/to/ext/brainwash/bin/provision-passphrase.php
```

The command disables terminal echo when attached to a TTY, asks for the same passphrase twice, and atomically writes a PHP file containing only a `password_hash()` value with mode `0600`. It does not print the passphrase or hash. For a non-default test path, set `BRAINWASH_PRIVATE_CONFIG` in the command environment and create a private writable parent directory first. Keep that file outside the web root and remove temporary credentials after the test.

The login form posts to `index.php`; logout is the same page with `action=logout`. The authenticated page has `#brainwash-root` and a CSRF meta tag named `csrf-token`. The cookie name is `BRAINWASHSESSID`, HttpOnly and SameSite Strict (Secure on HTTPS). API clients send `X-CSRF-Token` from that meta value. The server checks exact origin scheme/host/effective port, rejects cross-site fetch metadata, rotates the session ID at login, expires auth after 30 minutes, and limits failed login attempts per session. No token is put in a URL or local storage.

## Shared editor

`server/assets/editor.js` is the canonical UMD module and exports `window.BrainwashEditor.mount(root, {request}) -> {reset(), setAvailable(bool)}` plus CommonJS for Node tests. `server/assets/web.js` provides the same-origin fetch transport. The module is also copied into Department's fifth PRISMA tab by the coordinator. It renders text through DOM text nodes/values, exposes all eight editable fields and separate fictional beliefs, and requires explicit field/belief acceptance before apply. A failed mutation response leaves edits locked until a card readback confirms the last operation or proves the proposal still exists. Read failure keeps the lock. A new scope on a same-actor read clears the prior draft/uncertainty, and repeated same-value availability notifications do not discard work.

## Tests and evidence

The Node editor tests use a small in-memory DOM shim and no external dependency:

```sh
node tests/editor.test.js
```

They cover uncertain apply/read failure, same-availability notification, same-actor restore scope change, confirmed readback, and stale promise invalidation after reset. The initial run exposed the same-availability and scope-change regressions; the fixed editor passes all five.

Web helper tests:

```sh
php tests/web_auth.php
```

They cover strict origin comparison, the `Referrer-Policy: same-origin` browser provenance setting while continuing to reject opaque `Origin: null`, password-hash authentication, 64-hex CSRF matching, and bounded object JSON decoding. Browser HTTP checks are run separately by the coordinator because they require the TEST2 loopback fixture and disposable credentials.

PostgreSQL service tests must run only in the reserved `brainwash_test_20261024` database. The test asserts both `current_database()` and the preserved `public.brainwash_test_identity` ownership marker before setup, then truncates/drops only its own `brainwash_test_fixture` objects. Example in the approved WSL test host:

```sh
unshare -n runuser -u postgres -g www-data -- php /mnt/k/ActorwrightExchange/projects/CHIM-Brainwash/tests/postgres.php
```

The suite passed 17 tests against PostgreSQL: manual draft isolation, trusted base scope, stale identity/ref/profile fences, Department commit-fence rollback, belief-only JSON object shape, escaped DTO limits, apply/undo atomicity and rollback, narrow undo, AI output validation and prompt isolation, plus catalog search beyond 64 rows. It makes no connector/provider calls.

All server/test PHP files passed `php -l`. The coordinator reports that both the source page and installed page passed all 12 real Edge workflow checks against the unmodified response headers; the installed Department facade also passed. The header uses `Referrer-Policy: same-origin`, and the server still rejects opaque `Origin: null`. The owned runtime fixture was cleaned up: original metadata was restored, 14 fixture history rows were removed, and the SAS daemon was resumed; coordinator verification reports core revision 31, all 153 original NPCs, and unrelated settings/plugins unchanged. On the unseeded TEST2 profile state, catalog correctly returned `unavailable`: `pth_state.available` was true but there was no active ID/profile or `core_player.playthrough_id`. That readiness condition was not weakened. This verifies web/Department workflows, not in-game Skyrim acceptance.
