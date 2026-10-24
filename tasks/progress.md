# Brainwash execution ledger — plan: docs/implementation-plan.md

User authorized implementation and TEST2-only deployment. Spec: docs/implementation-contract.md. No repeated design approval needed. Parallel domains chosen under dispatching-parallel-agents skill; workers use requested repository working-rule model GPT6LUNA/max. No worker may spawn agents.

Source state:
- Brainwash new repo K:/ActorwrightExchange/projects/CHIM-Brainwash, codex/brainwash, initial plan commit d11bb74.
- Department K:/ActorwrightExchange/worktrees/brainwash-department-20261024, codex/brainwash-20261024, baseline089067dc3467064e934e5aa1cb217c4a55cad690. Native worktree tool could not handle nonGit chat root; manual worktree fallback succeeded. Primary/main untouched.
- Actual server TEST2 root /var/www/html/HerikaServer at468584f7bfc34c2eaf76e6188efaf3294f4fd63c. Live DwemerAI4Skyrim3 stopped, not invoked.
- Unique DB brainwash_test_20261024 created empty, ownership marker public.brainwash_test_identity owner=01a16f21-c22c-7c41-a551-a890e9bfc178. Worker can manage its fixture tables after marker check; preserve marker, no broad DROP DB/schema.
- Port18086 reserved for loopback HTTP fixture, not started yet. No Apache or PostgreSQL restart.

Workers:
- /root/brainwash_server owns plugin server/, tests/, docs/server-implementation.md. Native/DB deploy/package coordinator only.
- /root/brainwash_department owns Department worktree (coordinator handles packaging and canonical editor asset copies). Adapter pin fill coordinator after source freezes.

Shared contract: brainwash_dispatch(operation,payload,context), brainwash_runtime_ready; catalog/card/draft/apply/undo operations; explicit new brainwash scope, max8 narrative fields/20k card total, beliefs separate. Canonical shared editor server/assets/editor.js/css -> Department ui/brainwash-editor.js/css via coordinator.

Coordination: user in Dreamworld chat directly authorized server coordination; human evidence read via read_thread. Sent response reserving DB/port; Dreamworld confirmed its only DB dreamworld_probe_20261024_01a16f1d, no ports, no shared settings/runtime/extensions/services. Do not disturb Dreamworld.

Baseline:
- Foundation/run.php CLEAN baseline fails PCV readiness expectedtrue actualfalse at line161. Receipt artifacts/brainwash-build-20261024/baseline-department-foundation.txt. Fixture settings omit overlay prerequisites; worker alerted, must repair legitimate fixture or expected refusal without weakening guards.
- Native headers pinned unchanged: nlohmann9BEA4C... and Prisma46F772...; native baseline host tests running exec session54184.
- Baseline browser UI running session28695. Bundled Node/Playwright path from load_workspace_dependencies; default Edge browser.
- Native paths in artifacts/brainwash-build-20261024/native-paths.json from retained pre9 CMakeCache. Reuse pinned CommonLib/fmt/spdlog etc rather than fresh downloads/copies. K free ~29.9GB at start.

Rulings:
- Initial missing runtime generation is valid core behavior (created only at first switch). Do not create/mutate shared generation/work.lock just to satisfy a test. Existing core pth_state reads metadata only; use it with owned shared lease.
- No paid provider evaluation: implement real Profile Tasks adapter and test deterministic injected generator/parse boundary. No shipped fake AI.
- Coordinator will produce new TEST candidate (Brainwash0.1.0-dev.1; Department new prealpha.10 candidate) without relabeling immutable pre9.

Next coordinator work: finish baseline receipts; prepare package builder/test and build tool environment. Wait for source workers before integration; never overwrite their files. Then freeze pins/shared assets, real PG/HTTP/browser tests, actual DLL build, package and TEST2-only deployment, fresh independent review. Skyrim gameplay remains a separate unperformed gate.

Coordinator updates:
- Packaging implementation committed d3c1a78; RED package-red.txt, GREEN3/3 package-green.txt. Existing base MO2 zip pre9 PCV package will be retained byte-for-byte; new Department pre10 and Brainwashdev1 DWPkgs added with exact filename stems. No publish.
- Baseline native5/5 passed (baseline-native-tests.txt). Baseline UI49/50 with sole missing NATIVE_REFUSAL_FIXTURE prereq; worker informed correct fixture path. No product failure inferred.
- Unchanged journal full baseline stopped after diagnosing4,100operation fsync/compaction path; sourceFD verified ownPID10664. Receipt baseline-journal-stopped-note.txt. This stress path is not changed; use bounded directory-fsync guard and targeted new-operation dispatch/replay checks instead.
- Native CMake configured fresh native-build using retained pinned dependency sources/install; only CommonLibSSE target currently builds (session84290), no worker-edited source compiled yet.
- TEST2 preimage captured: 31 corefile SHA pins,153NPC full-row integrity hash (no raw row export),25Department files archived test2-department-before.tar.gz; Brainwash absent. Receipt preservation-before.json.
- Server worker changed service test connection fromPDO to nativePgSql so runtime can reuse actualptp_connect and scope reads on SAME connection. Public dispatch contract unchanged.
- Department context.scopeKey hash excludesgeneration; Brainwash internalcard.scope includesgeneration. Workers told to compare supplied Departmentbase scope separately.

Ruling: end-to-end installed-runtime proof cannot select the reserved database through normal CHIM ptp_connect (fixeddbname dwemer). Rather than ship a test-only datasource override or build a second Postgresinstance, final TEST2 HTTP proof may create one uniquely named synthetic NPC with explicit highID and owner marker in TEST2dwemer, dynamic_profile0/locked, and remove only its owned rows afterward. Original153NPC integrity preserved, no core/live changes, no paidcalls. All broad/rollback/failure/concurrency tests remain confined to reservedBrainwash DB. This is within user's new TEST2 build/test authorization. Never test on ordinaryNPCs.
- Unchanged CommonLibSSE dependency build PASS, native-commonlib-build.txt. Frozen Department native edits now building actual DLL/all5tests in exec session32501; worker holds native files stable.
- Bounded journal directory-fsync guard PASS; full original4100stress was stopped with exit15, never represented as passing.
- TEST2 preimage receipt verified:31corefiles,153NPC rows,25oldDepartment files; Brainwash absent. Dreamworld has finished its isolated spike, retained own8.2MBDB with zero connections and no more TEST2 operations planned.
Ruling: optional128-byte catalog query is necessary because first64of153NPCs makes later actors unreachable. Both workers informed; preserve empty payload/defaultcap64, add bounded name-search/shared-editor control and >64fixture regression. This completes NPC selection without adding a pagination subsystem.
- Final catalog-query native compile PASS; CTest4/5 caught extra-key limit accepted in new catalog validator, worker fixing. Receipt native-build-final.txt/native-tests-final.txt. No final native PASS claim yet.
- Fresh reviewer /root/brainwash_review (gpt6astra/max, read-only) active. Concrete identity SELECT missing refid/profile_id reported to server worker; label-rename separate-schema regression PASS.
- Actual package-manager layout verified: package server/* activates directly as ext/plugin/*; Department adapter was incorrectly looking for server/runtime.php, worker fixing to installed-root-relative runtime.php/lib/*. Coordinator will pin CHIM-Brainwash/server closure.
- Supplemental preservation receipt captured unrelated installed extension file hashes, general_settings integrity, work.lock mode/owner/inode, absent generation. An attempted full original history JSON-sort was overlarge and cancelled only its verified own backend11769; no writes. Do not claim full original-history hash. Preservation-extra-before.json valid; print-only KeyError subsequently corrected.
- Native catalog strict-key fix rebuilt, all5CTestPASS: native-build-catalog-fixed.txt/native-tests-catalog-fixed.txt. Actual updated DLL available under artifacts/.../dll/WorldOfDramaLlama.dll. No game install.
- Fresh review caught Department generic cached replay bypassing current owner state/generation, generic16KBjournal resultcap incompatible with valid20KBcards/~40KBdrafts, and missing final authorization commit fence. Department/server workers coordinating narrow fixes and regressions. No globalbounds increase, no authlock during model work.
- Belief-only field maps must serialize JSONobjects, not emptyarrays; server worker notified for wire regression.
- Coordinator prepared (not executed) freeze-integration.py to pin all Brainwash server PHP+manifest relative to installed extension root and copy exact canonical editor JS/CSS into Department ui. Run only after both owners freeze. No package built/installed yet.
- Coordinator prepared runtime-fixture.php (PHP lint PASS), NOT seeded yet: explicitid2147483001, label Brainwash TEST01a16f21, ownershipnamespace, lock_profile1/dynamic_profile0, historyFloor PKbound for narrowcleanup. No fullhistoryscan; record only owned postfloor histories.
- Reviewer found apply/undo result validation afterCOMMIT; server worker moving fullmergedcard/wire preflight insideopen transaction. Undo restoringbigfield afterunrelatedlateredit must rollback ifcombined20KBcard bound exceeded.
- Department worker asked for actual installed-runtime harness using real qd_api_handle + owner + isolated auth/journal state, no fakegameapproval claim. Needs deployment and singleownedNPC before coordinator execution.
- Real source-route HTTP fixture now running TEST2 loopback18086 aswwwdata, execsession73677, documentroot Brainwash/server (notcore), QD_CHIM_ROOT points actualTEST2. New generated disposablepassphrase provisioned through actualCLI bin/provision-passphrase.php into /tmp/brainwash-http-01a16f21/auth.php mode0600. Secret retained root-only sibling passphrase, never output. Existingcredentials untouched.
- Real HTTP RED receipt http-smoke-red.json: loginform, unauthrefusal, passwordlogin, sessionIDrotation, missingCSRF andforeignoriginrefusal allPASS; authorizedcatalogfails fromtop-levelpayload stdClass/arraydecodebug. Workerfixing. Onlyreadquery foruniqueBrainwashfixturelabel, noNPCfixtureseeded.
- FINAL actualEdge/source workflow PASS12checks; actualinstalledpackage/defaultrootdiscovery alsoPASS12. Receipt web-workflow.json (installed) and web-workflow-source.json (prior source), screenshot web-review.png visuallyinspected. Browsercausaltest caught Referrer-Policy:no-referrer=>Origin:null; changedactualheader same-origin, keepsnullrefusal, auth5/5. Refreshedall8pins+assets.
- Matchedbundlebuiltsha db5821870f0d30d95490d1b2b6f1479531fa8d80c259a71da88b7284c58cfce6. NormalrealconsumeracceptedbothDWPkgs; bothinstalledaswwwdata viaordinarymanager. Receipt test2-install.json. Builderfirstfailedon8generatedPCVoverlayfilesabsentfromsource; regressionRED/package-overlay-red.txt then4/4GREEN/package-overlay-green.txt. Explicit8filebaseoverlaybytespreserved; PCVcompanionpre9 preservedexact. Revieweraskednarrowpackagingdeltareview.
- ActualinstalledDepartmentfacade + freshCLIissuedbrainwashscope + realruntime/owner passedbootstrap/catalog/card/draft/apply/sameIDreplay/undo/apply-after-undo-conflict, no provider. Receipt installed-department.json. Bothnative5/5 andDepartmentUI50/50+interaction1/1 passedseparately; noSkyrimexecutionclaim.
- Installedsourceclosurepositive/every8filedrift/unknownversionrefusal PASS onownedtemporarycopy, pin-drift.json.
- OwnedfixturecleanupPASS: removedsyntheticNPC and14ownpostfloorhistoryrows; public.core_player/chim_meta.playthrough_profiles hashesexactrestored. SAS723resumed, ownHTTPprocess12533stopped. Helperwatchdogsession33234exited0. RootwrapperSIGINTpreviouslyleftchild11959; verifiedexactcmdlineandstoppedonlyownchildbeforeinstalledserve.
- Preservation-after.json PASS:31corefilesunchanged,all153originalNPCrowsidentical,bothinstalledextensionsbyte-exacttodeliverables(11Brainwash/27Departmentfiles). preservation-extra-after.json PASS:unrelatedextensionfiles/general_settings/work.lockowner-mode-inode/absentgenerationallunchanged. LiveWSLdistrostillStopped,neverinvoked.
