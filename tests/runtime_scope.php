<?php
declare(strict_types=1);

const RUNTIME_SCOPE_TEST_OWNER = 'mmp-runtime-scope-v1';

$dsn = getenv('BRAINWASH_TEST_DSN');
$coreRoot = getenv('BRAINWASH_TEST_CORE_ROOT');
$runId = getenv('BRAINWASH_SCOPE_RUN_ID');
if (!is_string($dsn) || $dsn === '' || !is_string($coreRoot) || $coreRoot === '' || !is_string($runId) || $runId === '') {
    throw new RuntimeException('BRAINWASH_TEST_DSN, BRAINWASH_TEST_CORE_ROOT and BRAINWASH_SCOPE_RUN_ID are required.');
}
if (!function_exists('pg_connect')) throw new RuntimeException('The PHP pgsql extension is required.');
$coreRoot = realpath($coreRoot);
if (!is_string($coreRoot) || !is_dir($coreRoot) || is_link($coreRoot)) throw new RuntimeException('The isolated pinned core source root is invalid.');
$GLOBALS['ENGINE_PATH'] = $coreRoot;
require_once $coreRoot . '/lib/playthrough_preferences.php';
require_once $coreRoot . '/lib/playthrough_home.php';
require_once __DIR__ . '/../server/runtime.php';

function mmpScopeQuery(mixed $db, string $sql, array $params = []): mixed
{
    $result = $params ? pg_query_params($db, $sql, $params) : pg_query($db, $sql);
    if ($result === false) throw new RuntimeException('Scope fixture SQL failed: ' . pg_last_error($db));
    return $result;
}

function mmpScopeAssert(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) throw new RuntimeException($message . '; expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
}

function mmpScopeSetting(mixed $db, string $key, mixed $value): void
{
    mmpScopeQuery($db, 'INSERT INTO chim_meta.settings (key,value) VALUES ($1,$2) ON CONFLICT (key) DO UPDATE SET value=EXCLUDED.value',
        [$key, json_encode($value, JSON_THROW_ON_ERROR)]);
}

function mmpScopeReset(mixed $db): void
{
    mmpScopeQuery($db, 'CREATE SCHEMA IF NOT EXISTS chim_meta');
    mmpScopeQuery($db, 'CREATE TABLE IF NOT EXISTS chim_meta.settings (key TEXT PRIMARY KEY, value TEXT)');
    mmpScopeQuery($db, 'CREATE TABLE IF NOT EXISTS public.core_player (id TEXT PRIMARY KEY, value TEXT)');
    mmpScopeQuery($db, 'DROP TABLE IF EXISTS chim_meta.playthrough_profiles');
    mmpScopeQuery($db, "CREATE TABLE chim_meta.playthrough_profiles (
        id INTEGER PRIMARY KEY, name TEXT NOT NULL UNIQUE, schema_name TEXT, is_active BOOLEAN NOT NULL DEFAULT FALSE,
        storage_type TEXT NOT NULL DEFAULT 'schema', player_name TEXT, last_gamets BIGINT,
        created_at TIMESTAMP NOT NULL DEFAULT NOW(), size_bytes BIGINT NOT NULL DEFAULT 0,
        retention_kind TEXT NOT NULL DEFAULT 'manual', retention_pinned BOOLEAN NOT NULL DEFAULT FALSE
    )");
    mmpScopeQuery($db, 'TRUNCATE chim_meta.settings');
    mmpScopeQuery($db, 'DELETE FROM public.core_player');
    mmpScopeSetting($db, 'PLAYTHROUGH_HOME_REVISION', '0');
    mmpScopeSetting($db, 'PLAYTHROUGH_AUTO_SWITCH', false);
    mmpScopeSetting($db, 'PLAYTHROUGH_SESSION', []);
}

function mmpScopeAddProfile(mixed $db, ?string $id, string $name, string $schema, bool $active, string $storageType = 'schema'): void
{
    mmpScopeQuery($db, 'INSERT INTO chim_meta.playthrough_profiles (id,name,schema_name,is_active,storage_type,created_at,size_bytes) VALUES ($1,$2,$3,$4::boolean,$5,NOW(),0)',
        [$id, $name, $schema, $active ? 'true' : 'false', $storageType]);
}

function mmpScopeExpectBase(mixed $db, string $coreRoot, string $expected): void
{
    $scope = brainwash_profile_scope($db, $coreRoot);
    mmpScopeAssert($expected, $scope['base'] ?? null, 'Authoritative base scope');
    mmpScopeAssert('', $scope['generation'] ?? null, 'Fresh test generation');
}

function mmpScopeExpectUnavailable(mixed $db, string $coreRoot): void
{
    try {
        brainwash_profile_scope($db, $coreRoot);
    } catch (Throwable) {
        return;
    }
    throw new RuntimeException('Invalid or unsettled home state fell through to an owner scope.');
}

function mmpScopeCase(mixed $db, array &$failures, string $name, callable $test): void
{
    static $caseNumber = 0;
    $savepoint = 'mmp_scope_case_' . ++$caseNumber;
    mmpScopeQuery($db, 'SAVEPOINT ' . $savepoint);
    $failure = null;
    try {
        $test();
    } catch (Throwable $error) {
        $failure = $error;
    }
    if (@pg_query($db, 'ROLLBACK TO SAVEPOINT ' . $savepoint) === false) {
        throw new RuntimeException('Could not restore isolated test case savepoint: ' . pg_last_error($db));
    }
    if (@pg_query($db, 'RELEASE SAVEPOINT ' . $savepoint) === false) {
        throw new RuntimeException('Could not release isolated test case savepoint: ' . pg_last_error($db));
    }
    if ($failure === null) {
        echo "PASS {$name}\n";
        return;
    }
    $detail = get_class($failure) . ($failure->getMessage() !== '' ? ': ' . $failure->getMessage() : '');
    $failures[] = $name;
    fwrite(STDERR, "FAIL {$name}: {$detail}\n");
}

$db = @pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
if ($db === false) throw new RuntimeException('Could not connect to the reserved fresh scope-test database.');
$failures = [];
try {
    $identityResult = pg_query_params($db, 'SELECT current_database() AS database_name, current_user AS database_user, inet_server_addr()::text AS server_address, host(inet_server_addr()) AS server_host, (SELECT run_id FROM public.brainwash_runtime_scope_test_identity WHERE owner=$1) AS run_id', [RUNTIME_SCOPE_TEST_OWNER]);
    if ($identityResult === false) throw new RuntimeException('Could not verify the disposable database identity.');
    $identity = pg_fetch_assoc($identityResult);
    echo 'DATABASE_IDENTITY ' . json_encode($identity, JSON_THROW_ON_ERROR) . "\n";
    if (($identity['database_name'] ?? null) !== 'dwemer' || ($identity['database_user'] ?? null) !== 'dwemer'
        || ($identity['server_host'] ?? null) !== '127.0.0.1' || ($identity['run_id'] ?? null) !== $runId) {
        throw new RuntimeException('Refusing a database outside this private owned runtime-scope run.');
    }
    $preflightResult = pg_query($db, "SELECT to_regnamespace('chim_meta') IS NULL AS meta_absent, to_regclass('public.core_player') IS NULL AS player_absent, to_regclass('public.core_npc_master') IS NULL AS npc_absent");
    if ($preflightResult === false) throw new RuntimeException('Could not verify the fresh fixture database.');
    $preflight = pg_fetch_assoc($preflightResult);
    if (($preflight['meta_absent'] ?? null) !== 't' || ($preflight['player_absent'] ?? null) !== 't' || ($preflight['npc_absent'] ?? null) !== 't') {
        throw new RuntimeException('Refusing to modify a database that already contains CHIM metadata or player state.');
    }
    if (!function_exists('pth_state') || !function_exists('ptp_product') || !brainwash_runtime_ready()
        || (ptp_product()['meta'] ?? null) !== 'chim_meta') {
        throw new RuntimeException('Pinned core source closure did not pass the owner runtime checks.');
    }
    echo 'PINNED_HELPERS pth_state=present ptp_product=' . ptp_product()['meta'] . "\n";
    foreach (BRAINWASH_CORE_SOURCE_PINS as $relative => $_expectedHash) {
        echo 'PINNED_SOURCE ' . $relative . ' sha256=' . hash_file('sha256', $coreRoot . '/' . $relative) . "\n";
    }
    $generationPath = $coreRoot . '/log/playthrough_runtime/generation';
    if (is_file($generationPath) || is_link($generationPath)) throw new RuntimeException('The isolated source copy must not carry a generation file.');
    mmpScopeQuery($db, 'SET search_path TO public');

    $logPath = $coreRoot . '/log';
    $runtimeDir = $logPath . '/playthrough_runtime';
    if (is_link($logPath) || is_link($runtimeDir)) throw new RuntimeException('The isolated owner runtime path must not contain symlinks.');
    if (!is_dir($runtimeDir) && !@mkdir($runtimeDir, 0700, true)) throw new RuntimeException('Could not create the isolated owner runtime fixture.');
    $lockPath = $runtimeDir . '/work.lock';
    $lock = @fopen($lockPath, 'x+b');
    if (!is_resource($lock)) throw new RuntimeException('Could not create a fresh isolated owner work.lock fixture.');
    fclose($lock);

    mmpScopeReset($db);
    mmpScopeQuery($db, 'CREATE TABLE public.core_npc_master (id INTEGER PRIMARY KEY, npc_name TEXT NOT NULL)');
    mmpScopeQuery($db, "INSERT INTO public.core_npc_master (id,npc_name) VALUES (1,'Aela')");
    $probe = ptp_connect();
    if ($probe === false) throw new RuntimeException('Pinned ptp_connect failed before the owner factory catalog proof.');
    try {
        mmpScopeQuery($probe, 'SET search_path TO public');
        $probeIdentityResult = mmpScopeQuery($probe, 'SELECT current_database() AS database_name, current_user AS database_user, inet_server_addr()::text AS server_address, host(inet_server_addr()) AS server_host');
        $probeIdentity = pg_fetch_assoc($probeIdentityResult);
        mmpScopeAssert('dwemer', $probeIdentity['database_name'] ?? null, 'Owner factory probe database');
        mmpScopeAssert('dwemer', $probeIdentity['database_user'] ?? null, 'Owner factory probe role');
        echo 'PTP_CONNECT_IDENTITY ' . json_encode($probeIdentity, JSON_THROW_ON_ERROR) . "\n";
        mmpScopeAssert('127.0.0.1', $probeIdentity['server_host'] ?? null, 'Owner factory probe server');
        $probeHome = pth_state($probe);
        mmpScopeAssert(true, $probeHome['available'] ?? null, 'Owner factory probe home is available');
        mmpScopeAssert(0, $probeHome['active_id'] ?? null, 'Owner factory probe has no active profile');
        mmpScopeAssert(false, $probeHome['auto_switch'] ?? null, 'Owner factory probe has automatic switching off');
        mmpScopeAssert(false, $probeHome['auto_switch_pending'] ?? null, 'Owner factory probe has no pending switch');
        mmpScopeAssert([], $probeHome['playthroughs'] ?? null, 'Owner factory probe has no saved choices');
        $catalogFixture = mmpScopeQuery($probe, 'SELECT id,npc_name FROM public.core_npc_master ORDER BY id');
        mmpScopeAssert([['id' => '1', 'npc_name' => 'Aela']], pg_fetch_all($catalogFixture), 'Owner factory catalog fixture is visible on ptp_connect');
    } finally {
        pg_close($probe);
    }
    try {
        $catalog = brainwash_dispatch('brainwash.catalog.read', []);
        mmpScopeAssert([['actorKey' => 'npc:1', 'label' => 'Aela']], $catalog['actors'] ?? null, 'Owner factory catalog actor');
        mmpScopeAssert(false, $catalog['limited'] ?? null, 'Owner factory catalog bound');
        echo "PASS real brainwash_dispatch owner factory reads ordinary public catalog\n";
    } catch (Throwable $error) {
        $detail = get_class($error) . ($error->getMessage() !== '' ? ': ' . $error->getMessage() : '');
        $failures[] = 'real brainwash_dispatch owner factory catalog';
        fwrite(STDERR, "FAIL real brainwash_dispatch owner factory catalog: {$detail}\n");
    }

    mmpScopeQuery($db, 'BEGIN');
    $ordinaryBase = hash('sha256', "qd-department-unprofiled-v1\0public");
    mmpScopeCase($db, $failures, 'settled empty public home derives the ordinary base through the real owner resolver', static function () use ($db, $coreRoot, $ordinaryBase): void {
        mmpScopeReset($db);
        $home = pth_state($db);
        mmpScopeAssert(true, $home['available'] ?? null, 'Pinned home is available');
        mmpScopeAssert(0, $home['active_id'] ?? null, 'Pinned home has no active profile');
        mmpScopeAssert(false, $home['auto_switch'] ?? null, 'Pinned home is settled with automatic switching off');
        mmpScopeAssert(false, $home['auto_switch_pending'] ?? null, 'Pinned home has no pending switch');
        mmpScopeAssert([], $home['playthroughs'] ?? null, 'Pinned home has no choices');
        mmpScopeExpectBase($db, $coreRoot, $ordinaryBase);
    });

    mmpScopeCase($db, $failures, 'real owner resolver allows inactive saved choices with available and missing schemas', static function () use ($db, $coreRoot, $ordinaryBase): void {
        mmpScopeReset($db);
        mmpScopeQuery($db, 'CREATE SCHEMA IF NOT EXISTS chim_profile_saved');
        mmpScopeAddProfile($db, '9', 'Inactive Save', 'chim_profile_saved', false);
        mmpScopeAddProfile($db, '10', 'Unavailable Inactive Save', 'chim_profile_missing', false);
        $home = pth_state($db);
        $choicesById = [];
        foreach ($home['playthroughs'] ?? [] as $choice) $choicesById[$choice['id']] = $choice;
        mmpScopeAssert(2, count($choicesById), 'Both inactive choices are preserved');
        mmpScopeAssert(false, $choicesById[9]['active'] ?? null, 'Available saved choice remains inactive');
        mmpScopeAssert(true, $choicesById[9]['available'] ?? null, 'Available saved choice stays available');
        mmpScopeAssert(false, $choicesById[10]['active'] ?? null, 'Unavailable saved choice remains inactive');
        mmpScopeAssert(false, $choicesById[10]['available'] ?? null, 'Unavailable saved choice stays unavailable');
        mmpScopeExpectBase($db, $coreRoot, $ordinaryBase);
    });

    mmpScopeCase($db, $failures, 'managed active profile retains the old base with automatic switching both on and off', static function () use ($db, $coreRoot): void {
        $characterId = str_repeat('a', 32);
        $expected = hash('sha256', "qd-department-scope-v1\0" . '7' . "\0chim_profile_managed\0" . $characterId);
        mmpScopeQuery($db, 'CREATE SCHEMA IF NOT EXISTS chim_profile_managed');
        foreach ([false, true] as $autoSwitch) {
            mmpScopeReset($db);
            mmpScopeSetting($db, 'PLAYTHROUGH_AUTO_SWITCH', $autoSwitch);
            mmpScopeAddProfile($db, '7', 'Managed Save', 'chim_profile_managed', true);
            mmpScopeQuery($db, "INSERT INTO public.core_player (id,value) VALUES ('playthrough_id',$1),('player_name','Test')", [$characterId]);
            $home = pth_state($db);
            mmpScopeAssert(7, $home['active_id'] ?? null, 'Managed active profile ID');
            mmpScopeAssert(true, $home['playthroughs'][0]['active'] ?? null, 'Managed active choice');
            mmpScopeExpectBase($db, $coreRoot, $expected);
        }
    });

    mmpScopeCase($db, $failures, 'missing profile metadata stays unavailable', static function () use ($db, $coreRoot): void {
        mmpScopeReset($db);
        mmpScopeQuery($db, 'DROP TABLE chim_meta.playthrough_profiles');
        mmpScopeAssert(false, pth_state($db)['available'] ?? null, 'Pinned helper reports unavailable');
        mmpScopeExpectUnavailable($db, $coreRoot);
    });

    mmpScopeCase($db, $failures, 'missing settings metadata stays unavailable', static function () use ($db, $coreRoot): void {
        mmpScopeReset($db);
        mmpScopeQuery($db, 'DROP TABLE chim_meta.settings');
        mmpScopeAssert(false, pth_state($db)['available'] ?? null, 'Pinned helper reports unavailable');
        mmpScopeExpectUnavailable($db, $coreRoot);
    });

    mmpScopeCase($db, $failures, 'malformed home metadata fails closed', static function () use ($db, $coreRoot): void {
        mmpScopeReset($db);
        mmpScopeQuery($db, 'ALTER TABLE chim_meta.playthrough_profiles DROP COLUMN created_at');
        mmpScopeExpectUnavailable($db, $coreRoot);
    });

    mmpScopeCase($db, $failures, 'pending auto-switch is never treated as ordinary', static function () use ($db, $coreRoot): void {
        mmpScopeReset($db);
        mmpScopeSetting($db, 'PLAYTHROUGH_SESSION', ['status' => 'pending', 'message' => 'in progress']);
        mmpScopeAssert(true, pth_state($db)['auto_switch_pending'] ?? null, 'Pinned helper reports a pending switch');
        mmpScopeExpectUnavailable($db, $coreRoot);
    });

    mmpScopeCase($db, $failures, 'automatic switching with active ID zero is never ordinary', static function () use ($db, $coreRoot): void {
        mmpScopeReset($db);
        mmpScopeSetting($db, 'PLAYTHROUGH_AUTO_SWITCH', true);
        $home = pth_state($db);
        mmpScopeAssert(0, $home['active_id'] ?? null, 'No active profile');
        mmpScopeAssert(true, $home['auto_switch'] ?? null, 'Automatic switching is enabled');
        mmpScopeExpectUnavailable($db, $coreRoot);
    });

    mmpScopeCase($db, $failures, 'negative managed profile ID never falls back to ordinary', static function () use ($db, $coreRoot): void {
        mmpScopeReset($db);
        mmpScopeQuery($db, 'CREATE SCHEMA IF NOT EXISTS chim_profile_negative');
        mmpScopeAddProfile($db, '-7', 'Invalid ID', 'chim_profile_negative', true);
        mmpScopeQuery($db, "INSERT INTO public.core_player (id,value) VALUES ('playthrough_id',$1)", [str_repeat('b', 32)]);
        mmpScopeExpectUnavailable($db, $coreRoot);
    });

    mmpScopeCase($db, $failures, 'active choice with a missing ID is contradictory and rejected', static function () use ($db, $coreRoot): void {
        mmpScopeReset($db);
        mmpScopeQuery($db, 'DROP TABLE chim_meta.playthrough_profiles');
        mmpScopeQuery($db, "CREATE TABLE chim_meta.playthrough_profiles (
            id TEXT, name TEXT NOT NULL, schema_name TEXT, is_active BOOLEAN NOT NULL,
            storage_type TEXT NOT NULL, player_name TEXT, last_gamets BIGINT,
            created_at TIMESTAMP NOT NULL DEFAULT NOW(), size_bytes BIGINT NOT NULL DEFAULT 0
        )");
        mmpScopeQuery($db, 'CREATE SCHEMA IF NOT EXISTS chim_profile_missing_id');
        mmpScopeAddProfile($db, null, 'Missing ID', 'chim_profile_missing_id', true);
        mmpScopeExpectUnavailable($db, $coreRoot);
    });

    mmpScopeCase($db, $failures, 'contradictory multiple active choices fail closed', static function () use ($db, $coreRoot): void {
        mmpScopeReset($db);
        mmpScopeQuery($db, 'CREATE SCHEMA IF NOT EXISTS chim_profile_multi');
        mmpScopeAddProfile($db, '1', 'Active One', 'chim_profile_multi', true);
        mmpScopeAddProfile($db, '2', 'Active Two', 'chim_profile_multi', true);
        mmpScopeExpectUnavailable($db, $coreRoot);
    });

    mmpScopeCase($db, $failures, 'malformed inactive choice ID fails closed', static function () use ($db, $coreRoot): void {
        mmpScopeReset($db);
        mmpScopeQuery($db, 'DROP TABLE chim_meta.playthrough_profiles');
        mmpScopeQuery($db, "CREATE TABLE chim_meta.playthrough_profiles (
            id TEXT, name TEXT NOT NULL, schema_name TEXT, is_active BOOLEAN NOT NULL,
            storage_type TEXT NOT NULL, player_name TEXT, last_gamets BIGINT,
            created_at TIMESTAMP NOT NULL DEFAULT NOW(), size_bytes BIGINT NOT NULL DEFAULT 0
        )");
        mmpScopeQuery($db, 'CREATE SCHEMA IF NOT EXISTS chim_profile_bad_choice');
        mmpScopeAddProfile($db, 'bad-id', 'Malformed Inactive', 'chim_profile_bad_choice', false);
        mmpScopeExpectUnavailable($db, $coreRoot);
    });

    mmpScopeCase($db, $failures, 'duplicate inactive choices fail closed', static function () use ($db, $coreRoot): void {
        mmpScopeReset($db);
        mmpScopeQuery($db, 'DROP TABLE chim_meta.playthrough_profiles');
        mmpScopeQuery($db, "CREATE TABLE chim_meta.playthrough_profiles (
            id TEXT, name TEXT NOT NULL, schema_name TEXT, is_active BOOLEAN NOT NULL,
            storage_type TEXT NOT NULL, player_name TEXT, last_gamets BIGINT,
            created_at TIMESTAMP NOT NULL DEFAULT NOW(), size_bytes BIGINT NOT NULL DEFAULT 0
        )");
        mmpScopeQuery($db, 'CREATE SCHEMA IF NOT EXISTS chim_profile_duplicate');
        mmpScopeAddProfile($db, '8', 'Duplicate One', 'chim_profile_duplicate', false);
        mmpScopeAddProfile($db, '8', 'Duplicate Two', 'chim_profile_duplicate', false);
        mmpScopeExpectUnavailable($db, $coreRoot);
    });

    mmpScopeCase($db, $failures, 'managed profile with malformed character identity fails closed', static function () use ($db, $coreRoot): void {
        mmpScopeReset($db);
        mmpScopeQuery($db, 'CREATE SCHEMA IF NOT EXISTS chim_profile_bad_character');
        mmpScopeAddProfile($db, '7', 'Bad Character', 'chim_profile_bad_character', true);
        mmpScopeQuery($db, "INSERT INTO public.core_player (id,value) VALUES ('playthrough_id','not-a-character-hash')");
        mmpScopeExpectUnavailable($db, $coreRoot);
    });

    mmpScopeCase($db, $failures, 'managed profile with invalid schema identity fails closed', static function () use ($db, $coreRoot): void {
        mmpScopeReset($db);
        mmpScopeAddProfile($db, '7', 'Bad Schema', 'public', true);
        mmpScopeQuery($db, "INSERT INTO public.core_player (id,value) VALUES ('playthrough_id',$1)", [str_repeat('c', 32)]);
        mmpScopeExpectUnavailable($db, $coreRoot);
    });

    mmpScopeCase($db, $failures, 'managed profile with unsupported storage row fails closed', static function () use ($db, $coreRoot): void {
        mmpScopeReset($db);
        mmpScopeAddProfile($db, '7', 'Bad Row', 'chim_profile_unsupported', true, 'blob');
        mmpScopeQuery($db, "INSERT INTO public.core_player (id,value) VALUES ('playthrough_id',$1)", [str_repeat('d', 32)]);
        mmpScopeExpectUnavailable($db, $coreRoot);
    });

    mmpScopeQuery($db, 'ROLLBACK');
} catch (Throwable $error) {
    @pg_query($db, 'ROLLBACK');
    fwrite(STDERR, 'HARNESS ERROR: ' . get_class($error) . ': ' . $error->getMessage() . "\n");
    pg_close($db);
    exit(2);
}
pg_close($db);
if ($failures) {
    fwrite(STDERR, count($failures) . " focused owner scope case(s) failed.\n");
    exit(1);
}
echo "All focused owner scope cases passed.\n";