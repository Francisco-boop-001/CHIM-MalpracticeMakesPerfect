# Normal CHIM compatibility verification - dev.4

The correction lets the existing editor and Department dispatcher use ordinary CHIM with Playthrough Saves off. It changes only the owner scope resolver and version manifest in the runtime package. Valid managed profiles retain their existing scope and validation. Ordinary public NPC data remains shared across save slots.

## Focused owner proof

The real pinned `pth_state`/`ptp_product` helpers and PostgreSQL were exercised in an owned temporary cluster inside a private TEST2 network namespace. The intended RED run failed ordinary factory/catalog and ordinary resolver cases on dev.3; managed and refusal cases passed. The final GREEN02 run passed 17 cases, including actual `brainwash_dispatch` factory/catalog delivery, ordinary empty/inactive-choice state, managed auto-switch on/off, and malformed/pending/contradictory-state refusals. A preceding identity-precondition failure was preserved separately and is not counted as RED.

Final owner log SHA256: `6277416f538fee1b3c67eb03dd3625a9f90724a4b10102fd38d24be796aacdf0`.

## Independent Department HTTP acceptance

Run `normal-five-final-13` used the real Department API, owner factory, service and pinned core helpers on a fresh disposable PostgreSQL cluster. Ordinary ingress and bootstrap passed with no managed profile or 32-hex character ID. All five owner reads succeeded. Restore-lock refusal/recovery and managed A/B stale-context tests passed.

The manual MMP roundtrip passed catalog/card read, draft with native fields unchanged, selective personality apply with unselected goals and other plugin data preserved, duplicate owner-operation replay under a new Department request ID without another database/history change, and undo restoring personality. The two expected history snapshots were observed. This was authenticated Department HTTP delivery; it was not a new standalone-browser or Skyrim test.

Receipt SHA256: `db167c8f9a9429687b0d2facdf62160ec663a838317872504b03ad9c870b378b`.

Exact synthetic actor/history/sequence state was restored. Whole-database logical dumps matched after normalizing only PostgreSQL random restrict keys and the ordering of the two known fixture NPC INSERTs; raw dumps were retained and a value-drift negative control passed. The owned cluster stopped and scratch was removed. No campaign database, live server, CHIM core, running game, provider or credentials were used.

## Host checks and package

Final editor checks: 5 passed. Web request/no-login checks: 5 passed. Existing package-builder tests: 4 passed. Changed PHP files lint successfully. The unchanged broad `tests/postgres.php` service suite was not rerun for dev.4; actual HTTP mutation/replay/undo acceptance and the focused owner run are the current regression evidence. Its new test-only DSN override retains database/owner guards; no absent-variable Unix-socket fallback was invoked.

Frozen DWPkg: `0.1.0-dev.4.dwpkg`, 23643 bytes, SHA256 `9f1d8221954a293116b5bc7c202f13c4eb2062982d3fdabeb9134dfa0e05cec8`. All ten runtime files are checksum-closed; only `runtime.php` and `manifest.json` differ from dev.3. Public payload text uses canonical LF. Source inputs may be uniform LF or CRLF; mixed newlines and BOMs are refused during release preparation.

## Rebuild the standalone package

From the extracted source ZIP root, run Python 3 with the existing builder. This reproduces the package without Department or an installed server:

```python
import hashlib, importlib.util, pathlib, sys, tempfile
sys.dont_write_bytecode = True
root = pathlib.Path.cwd()
spec = importlib.util.spec_from_file_location('builder', root / 'packaging/build_test_bundle.py')
builder = importlib.util.module_from_spec(spec)
spec.loader.exec_module(builder)
with tempfile.TemporaryDirectory() as temporary:
    stage = pathlib.Path(temporary)
    for source in (root / 'server').rglob('*'):
        assert not source.is_symlink()
        if not source.is_file():
            continue
        data = source.read_bytes()
        assert not data.startswith(b'\xef\xbb\xbf')
        lf = data.replace(b'\r\n', b'\n')
        assert b'\r' not in lf
        assert b'\r\n' not in data or data == lf.replace(b'\n', b'\r\n')
        target = stage / 'server' / source.relative_to(root / 'server')
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(lf)
    metadata, package = builder.package(stage, 'brainwash')
assert metadata['version'] == '0.1.0-dev.4'
assert hashlib.sha256(package).hexdigest() == '9f1d8221954a293116b5bc7c202f13c4eb2062982d3fdabeb9134dfa0e05cec8'
(root / '0.1.0-dev.4.dwpkg').write_bytes(package)
```

Independent final whole-change review approved publication without actionable findings after checking the full diff, pinned helper behavior, scope callers, fencing, tests and package/evidence hashes. Clean tagged-source rebuild and downloaded release verification are separate publication checks recorded by the coordinator.

## Limits

No dev.4 provider/AI generation, actual Skyrim/PRISMA rendering, gameplay, or real save/load/restore certification was performed. The web editor and shared UI assets are unchanged from dev.3. This remains a TEST prerelease; package publication does not install or update a live server.
