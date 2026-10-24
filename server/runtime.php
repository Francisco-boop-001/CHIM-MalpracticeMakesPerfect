<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/service.php';

const BRAINWASH_CORE_SOURCE_PINS = [
    'lib/playthrough_preferences.php' => 'fc9562fad12b867a5593d06bc0b0b8167120b0270088c343ae3db074e4b3f475',
    'lib/playthrough_home.php' => 'e28ced10ee4410f0926f9366fca7bb80199b2f423c80afb1c960d074564b88b6',
    'lib/playthrough_runtime.php' => 'd480af9d658c8e754b78d1e4ca6056544d21942facbafd94b23ab1da4cb1cd9c',
    'lib/core/database_schema/core_npc_master.sql' => '9eae322a0183522da4ed9714eb1d538736b566beb04c264c82affdb71bc07fc6',
    'lib/core/database_schema/core_npc_master_history.sql' => 'f0c0c32d106cdc4c6ffb11d4b5490644d4f5fd2dec61822444aad829db225e1e',
];

function brainwash_engine_root(): ?string
{
    $candidate = $GLOBALS['ENGINE_PATH'] ?? (getenv('QD_CHIM_ROOT') ?: dirname(__DIR__, 2));
    if (!is_string($candidate) || str_contains($candidate, "\0") || is_link($candidate)) return null;
    $root = realpath($candidate);
    return is_string($root) && is_dir($root) && is_file($root . '/lib/playthrough_preferences.php') ? $root : null;
}

function brainwash_source_matches(string $path, string $expected): bool
{
    if (is_link($path) || !is_file($path) || !is_readable($path)) return false;
    $bytes = @file_get_contents($path);
    if (!is_string($bytes)) return false;
    return hash_equals($expected, hash('sha256', $bytes))
        || (preg_match('/(?<!\r)\n|\r(?!\n)/', $bytes) !== 1
            && hash_equals($expected, hash('sha256', str_replace("\r\n", "\n", $bytes))));
}

function brainwash_runtime_ready(): bool
{
    $root = brainwash_engine_root();
    if ($root === null || !is_file(__DIR__ . '/lib/service.php')) return false;
    foreach (BRAINWASH_CORE_SOURCE_PINS as $relative => $hash) {
        if (!brainwash_source_matches($root . '/' . $relative, $hash)) return false;
    }
    return function_exists('pg_connect') && function_exists('pg_query_params');
}

function brainwash_dispatch(string $operation, array $payload, array $context = []): array
{
    if (!in_array($operation, ['brainwash.catalog.read', 'brainwash.card.read', 'brainwash.draft', 'brainwash.apply', 'brainwash.undo'], true)) {
        throw new BrainwashFault('invalid_request');
    }
    if (!brainwash_runtime_ready()) throw new BrainwashFault('unavailable');
    $root = brainwash_engine_root();
    if ($root === null) throw new BrainwashFault('unavailable');

    try {
        return brainwash_with_profile_lease($root, static function (string $generation) use ($operation, $payload, $context, $root): array {
            require_once $root . '/lib/playthrough_preferences.php';
            require_once $root . '/lib/playthrough_home.php';
            if (!function_exists('ptp_connect') || !function_exists('pth_state')) throw new RuntimeException('Pinned playthrough helpers are unavailable.');
            $connection = ptp_connect();
            if ($connection === false) throw new RuntimeException('The active profile connection is unavailable.');
            try {
                if (@pg_query($connection, 'SET search_path TO public') === false) throw new RuntimeException('The active profile schema is unavailable.');
                $initial = brainwash_profile_scope($connection, $root);
                if (!hash_equals($generation, $initial['generation'])) throw new RuntimeException('The active restore generation changed.');
                $scopeProvider = static function () use ($connection, $root): string {
                    $state = brainwash_profile_scope($connection, $root);
                    return hash('sha256', "brainwash-scope-v1\0" . $state['base'] . "\0" . $state['generation']);
                };
                $baseScopeProvider = static function () use ($connection, $root): string {
                    return brainwash_profile_scope($connection, $root)['base'];
                };
                $generator = static fn(array $input): string => brainwash_profile_tasks_generate($input, $connection, $root);
                $service = new BrainwashService($connection, $scopeProvider, $generator, $baseScopeProvider);
                return $service->dispatch($operation, $payload, $context);
            } finally {
                pg_close($connection);
            }
        });
    } catch (BrainwashFault $fault) {
        throw $fault;
    } catch (Throwable) {
        throw new BrainwashFault('unavailable');
    }
}

/** Use the core work.lock without calling ptr_runtime_enter(), which exits on contention. */
function brainwash_with_profile_lease(string $coreRoot, callable $callback): mixed
{
    if (!empty($GLOBALS['ptr_runtime_controller'])) throw new BrainwashFault('unavailable');
    $runtimeDir = $coreRoot . '/log/playthrough_runtime';
    $lockPath = $runtimeDir . '/work.lock';
    $pausePath = $runtimeDir . '/paused';
    $generationPath = $runtimeDir . '/generation';
    if (is_link($coreRoot . '/log') || is_link($runtimeDir) || !is_dir($runtimeDir) || !is_readable($runtimeDir)
        || is_link($lockPath) || !is_file($lockPath) || is_link($pausePath)) throw new BrainwashFault('unavailable');

    $hadLease = array_key_exists('ptr_runtime_lease', $GLOBALS);
    $previousLease = $GLOBALS['ptr_runtime_lease'] ?? null;
    $hadGeneration = array_key_exists('ptr_runtime_generation', $GLOBALS);
    $previousGeneration = $GLOBALS['ptr_runtime_generation'] ?? null;
    $ownsLease = false;
    $lease = $previousLease;
    if (is_file($pausePath)) throw new BrainwashFault('unavailable');
    if (!$hadLease) {
        $lease = @fopen($lockPath, 'rb');
        if (!is_resource($lease) || !@flock($lease, LOCK_SH | LOCK_NB) || is_file($pausePath)) {
            if (is_resource($lease)) fclose($lease);
            throw new BrainwashFault('unavailable');
        }
        $ownsLease = true;
    } elseif (!is_resource($lease)) {
        throw new BrainwashFault('unavailable');
    }

    try {
        if (is_file($pausePath)) throw new BrainwashFault('unavailable');
        $generation = brainwash_read_generation($generationPath);
        if ($hadGeneration && (!is_string($previousGeneration) || !hash_equals($generation, $previousGeneration))) {
            throw new BrainwashFault('stale_context');
        }
        if (!$hadLease) $GLOBALS['ptr_runtime_lease'] = $lease;
        $GLOBALS['ptr_runtime_generation'] = $generation;
        return $callback($generation);
    } finally {
        if (!$hadLease) unset($GLOBALS['ptr_runtime_lease']);
        elseif ($hadLease) $GLOBALS['ptr_runtime_lease'] = $previousLease;
        if ($hadGeneration) $GLOBALS['ptr_runtime_generation'] = $previousGeneration;
        else unset($GLOBALS['ptr_runtime_generation']);
        if ($ownsLease && is_resource($lease)) fclose($lease);
    }
}

function brainwash_read_generation(string $path): string
{
    clearstatcache(true, $path);
    if (is_link($path)) throw new BrainwashFault('unavailable');
    $stat = @lstat($path);
    if ($stat === false) return '';
    if ((($stat['mode'] ?? 0) & 0170000) !== 0100000 || ($stat['size'] ?? 0) < 1 || ($stat['size'] ?? 0) > 256) {
        throw new BrainwashFault('unavailable');
    }
    $raw = @file_get_contents($path);
    if (!is_string($raw) || preg_match('/\A[0-9a-f]{32}\s*\z/D', $raw) !== 1) throw new BrainwashFault('unavailable');
    return trim($raw);
}

function brainwash_profile_scope(mixed $connection, string $coreRoot): array
{
    if (!function_exists('pth_state') || !function_exists('ptp_product')) throw new RuntimeException('Pinned profile state helpers are unavailable.');
    $home = pth_state($connection);
    $activeId = filter_var($home['active_id'] ?? null, FILTER_VALIDATE_INT);
    if (($home['available'] ?? null) !== true || ($home['auto_switch_pending'] ?? false) === true || !is_int($activeId) || $activeId < 1) {
        throw new BrainwashFault('unavailable');
    }
    $active = array_values(array_filter($home['playthroughs'] ?? [], static fn($profile): bool => is_array($profile) && ($profile['active'] ?? null) === true));
    if (count($active) !== 1 || (int)($active[0]['id'] ?? 0) !== $activeId || ($active[0]['available'] ?? null) !== true) {
        throw new BrainwashFault('unavailable');
    }
    $characterId = $active[0]['character_id'] ?? null;
    if (!is_string($characterId) || preg_match('/\A[a-f0-9]{32}\z/D', $characterId) !== 1) throw new BrainwashFault('unavailable');
    $meta = ptp_product()['meta'] ?? null;
    if (!is_string($meta) || preg_match('/\A[a-z][a-z0-9_]{0,62}\z/D', $meta) !== 1) throw new BrainwashFault('unavailable');
    $result = @pg_query_params($connection, "SELECT schema_name FROM {$meta}.playthrough_profiles WHERE id=$1 AND is_active IS TRUE", [$activeId]);
    if ($result === false || pg_num_rows($result) !== 1) throw new BrainwashFault('unavailable');
    $row = pg_fetch_assoc($result);
    $schemaName = $row['schema_name'] ?? null;
    if (!is_string($schemaName) || preg_match('/\Achim_profile_[a-z0-9_]{1,50}\z/D', $schemaName) !== 1) throw new BrainwashFault('unavailable');
    $base = hash('sha256', "qd-department-scope-v1\0" . $activeId . "\0" . $schemaName . "\0" . $characterId);
    return ['base' => $base, 'generation' => brainwash_read_generation($coreRoot . '/log/playthrough_runtime/generation')];
}

function brainwash_profile_tasks_generate(array $input, mixed $connection, string $coreRoot): string
{
    $previous = [];
    foreach ($GLOBALS as $key => $value) {
        if ($key !== 'GLOBALS') $previous[$key] = $value;
    }
    try {
        require_once $coreRoot . '/lib/settings.php';
        require_once $coreRoot . '/lib/core/llm_connector.class.php';
        $rows = @pg_query($connection, "SELECT id,value FROM public.general_settings WHERE id IN ('CORE_CONNECTOR_PROFILES','CORE_CONNECTOR_PROFILES_ENABLED')");
        if ($rows === false) throw new RuntimeException('Profile Tasks settings are unavailable.');
        while ($row = pg_fetch_assoc($rows)) {
            if ($row['id'] === 'CORE_CONNECTOR_PROFILES') $GLOBALS['CORE_CONNECTOR_PROFILES'] = (int)$row['value'];
            if ($row['id'] === 'CORE_CONNECTOR_PROFILES_ENABLED') $GLOBALS['CORE_CONNECTOR_PROFILES_ENABLED'] = in_array(strtolower((string)$row['value']), ['1', 'true', 'yes', 'on'], true);
        }
        if (!chimIsGlobalLlmConnectorEnabled('CORE_CONNECTOR_PROFILES')) throw new RuntimeException('Profile Tasks are disabled.');
        $connectorId = (int)($GLOBALS['CORE_CONNECTOR_PROFILES'] ?? 0);
        if ($connectorId < 1) throw new RuntimeException('Profile Tasks connector is not configured.');

        // Never reuse the page's possibly stale profile connection. Connector reads and audit writes
        // are deliberately pinned to Brainwash's current native connection.
        $GLOBALS['db'] = new BrainwashConnectorReadDb($connection);
        $GLOBALS['ENGINE_PATH'] = $coreRoot;
        $connector = new LLMConnector();
        $connectorData = $connector->getById($connectorId);
        if (!is_array($connectorData)) throw new RuntimeException('Profile Tasks connector is unavailable.');
        $connector->setOldGlobals($connectorData);
        $handler = $connector->getConnector($connectorData);
        if (!is_object($handler) || !method_exists($handler, 'fast_request')) throw new RuntimeException('Profile Tasks connector is unavailable.');

        $data = [
            'actorKey' => $input['actorKey'],
            'npcLabel' => $input['label'],
            'currentCard' => $input['fields'],
            'selectedFields' => $input['selectedFields'],
            'instruction' => $input['instruction'],
        ];
        if (($input['selectBeliefs'] ?? false) === true) {
            $data['userAuthoredFictionalBeliefForOptionalEditing'] = $input['beliefs'];
        }
        $messages = [
            ['role' => 'system', 'content' => 'You draft a fictional Skyrim NPC profile. Return exactly one JSON object with string values. Its keys must be exactly the selected profile field names'
                . (($input['selectBeliefs'] ?? false) === true ? ' plus beliefs' : '')
                . '. Do not add commentary or other keys. User-authored beliefs are fictional claims, not verified facts; keep them separate from native profile fields.'],
            ['role' => 'user', 'content' => json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        ];
        $response = $handler->fast_request($messages, ['MAX_TOKENS' => 2048, 'temperature' => 0.7], 'brainwash_profile_generation');
        if (!is_string($response) || strlen($response) > 65536) throw new RuntimeException('Profile Tasks returned no bounded response.');
        return $response;
    } finally {
        foreach (array_keys($GLOBALS) as $key) {
            if ($key !== 'GLOBALS' && !array_key_exists($key, $previous)) unset($GLOBALS[$key]);
        }
        foreach ($previous as $key => $value) $GLOBALS[$key] = $value;
    }
}

final class BrainwashConnectorReadDb
{
    public function __construct(private mixed $connection) {}

    public function fetchOne(string $sql, array $params = []): array
    {
        $result = $params ? @pg_query_params($this->connection, $sql, $params) : @pg_query($this->connection, $sql);
        if ($result === false) return [];
        return pg_fetch_assoc($result) ?: [];
    }

    public function fetchAll(string $sql, bool $log = false): array
    {
        $result = @pg_query($this->connection, $sql);
        return $result === false ? [] : (pg_fetch_all($result) ?: []);
    }

    public function insert(string $table, array $data): bool
    {
        if ($table !== 'audit_request' || $data === []) return false;
        foreach (array_keys($data) as $name) if (!is_string($name) || preg_match('/\A[a-zA-Z_][a-zA-Z0-9_]*\z/D', $name) !== 1) return false;
        $columns = array_keys($data);
        $values = array_values($data);
        $placeholders = array_map(static fn(int $index): string => '$' . ($index + 1), array_keys($values));
        $sql = 'INSERT INTO audit_request (' . implode(',', $columns) . ') VALUES (' . implode(',', $placeholders) . ')';
        return @pg_query_params($this->connection, $sql, $values) !== false;
    }
}
