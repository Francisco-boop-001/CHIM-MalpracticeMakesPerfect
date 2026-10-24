<?php
declare(strict_types=1);

const BRAINWASH_TEST_DATABASE = 'brainwash_test_20261024';
const BRAINWASH_TEST_OWNER = '01a16f21-c22c-7c41-a551-a890e9bfc178';
const BRAINWASH_TEST_SCHEMA = 'brainwash_test_fixture';

$db = pg_connect('host=/var/run/postgresql dbname=' . BRAINWASH_TEST_DATABASE . ' user=postgres');
if ($db === false) throw new RuntimeException('Could not connect to the reserved Brainwash PostgreSQL database.');
$identityResult = pg_query($db, 'SELECT current_database() AS name, owner FROM public.brainwash_test_identity');
$identity = $identityResult ? pg_fetch_assoc($identityResult) : false;
if (($identity['name'] ?? null) !== BRAINWASH_TEST_DATABASE || ($identity['owner'] ?? null) !== BRAINWASH_TEST_OWNER) {
    throw new RuntimeException('Refusing to run outside the reserved Brainwash test database.');
}

require_once __DIR__ . '/../server/lib/service.php';

function query(mixed $db, string $sql, array $params = []): PgSql\Result
{
    $result = $params ? pg_query_params($db, $sql, $params) : pg_query($db, $sql);
    if ($result === false) throw new RuntimeException('Fixture SQL failed: ' . pg_last_error($db));
    return $result;
}
function value(mixed $db, string $sql, array $params = []): mixed
{
    return pg_fetch_result(query($db, $sql, $params), 0, 0);
}

$schemaOwner = value($db, 'SELECT r.rolname FROM pg_namespace n JOIN pg_roles r ON r.oid=n.nspowner WHERE n.nspname=$1', [BRAINWASH_TEST_SCHEMA]);
if ($schemaOwner !== false && $schemaOwner !== 'postgres') {
    throw new RuntimeException('Refusing to use a Brainwash fixture schema owned by another role.');
}
query($db, 'CREATE SCHEMA IF NOT EXISTS ' . BRAINWASH_TEST_SCHEMA . ' AUTHORIZATION postgres');
query($db, 'SET search_path TO ' . BRAINWASH_TEST_SCHEMA . ', public');
query($db, "CREATE TABLE IF NOT EXISTS core_npc_master (
    id integer PRIMARY KEY, npc_name text NOT NULL, npc_favorite integer DEFAULT 0, lock_profile integer DEFAULT 0,
    prompt_head text, npc_static_bio text, oghma_knowledge_tags text, emote_moods text, personality text,
    relationships text, occupation text, appearance text, skills text, speechstyle text, goals text, voiceid text,
    metadata jsonb, gender text, race text, refid varchar(16), profile_id integer, dynamic_profile integer,
    extended_data jsonb, plugin_extended_data jsonb NOT NULL DEFAULT '{}'::jsonb, md5 text,
    gamets_last_updated numeric, core text, base text, tags text
)");
query($db, "CREATE TABLE IF NOT EXISTS core_npc_master_history (
    history_id serial PRIMARY KEY, npc_id integer NOT NULL, npc_name text, npc_favorite integer, lock_profile integer,
    prompt_head text, npc_static_bio text, oghma_knowledge_tags text, emote_moods text, personality text,
    relationships text, occupation text, appearance text, skills text, speechstyle text, goals text, voiceid text,
    metadata jsonb, gender text, race text, refid varchar(16), profile_id integer, dynamic_profile integer,
    extended_data jsonb, plugin_extended_data jsonb NOT NULL DEFAULT '{}'::jsonb, md5 text,
    gamets_last_updated numeric, created timestamp without time zone DEFAULT now(), core text, base text, tags text
)");

function expectSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . '; expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function expectTrue(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function resetFixture(mixed $db): void
{
    query($db, 'DROP TRIGGER IF EXISTS brainwash_test_reject ON core_npc_master');
    query($db, 'DROP FUNCTION IF EXISTS brainwash_test_reject()');
    query($db, 'TRUNCATE core_npc_master_history RESTART IDENTITY');
    query($db, 'DELETE FROM core_npc_master');
    query($db, "INSERT INTO core_npc_master
        (id,npc_name,core,npc_static_bio,personality,appearance,occupation,skills,speechstyle,goals,
         relationships,voiceid,extended_data,plugin_extended_data)
        VALUES (1,'Aela','core seed','bio seed','brave','iron armor','hunter','bow','direct','protect Whiterun',
         'friendship unchanged','voice preserved','{\"core_marker\":\"preserved\"}',
         '{\"other_plugin\":{\"keep\":\"yes\"}}'),
               (2,'Other NPC','other core','other bio','other secret','other look','other job','other skill','other style','other goal',
         NULL,NULL,'{}','{}')");
}

function makeService(mixed $db, string &$scope, ?callable $generator = null, string $baseScope = 'department-base'): BrainwashService
{
    return new BrainwashService($db, static function () use (&$scope): string { return $scope; }, $generator,
        static function () use ($baseScope): string { return $baseScope; });
}

function expectFault(string $wireCode, callable $work): void
{
    try { $work(); } catch (BrainwashFault $fault) {
        expectSame($wireCode, $fault->wireCode, 'Fixed Brainwash fault code');
        return;
    }
    throw new RuntimeException('Expected BrainwashFault ' . $wireCode);
}

function createDraft(BrainwashService $service, string $field, string $value, string $draftOperationId = 'a8c8e856-81b2-4dcb-8d3d-3d6b643fc371'): array
{
    $card = $service->dispatch('brainwash.card.read', ['actorKey' => 'npc:1']);
    return $service->dispatch('brainwash.draft', ['actorKey' => 'npc:1', 'expectedRevision' => $card['revision'],
        'operationId' => $draftOperationId, 'mode' => 'manual', 'changes' => [$field => $value], 'instruction' => '']);
}

function applyDraft(BrainwashService $service, array $draft, string $applyId = 'd745fa8e-9fd2-4d6a-b7ca-7cf81913c548', array $fields = ['personality']): array
{
    return $service->dispatch('brainwash.apply', ['actorKey' => 'npc:1', 'draftId' => $draft['draftId'],
        'operationId' => $applyId, 'acceptedFields' => $fields, 'acceptBeliefs' => false]);
}

$tests = [
    'card.read returns the eight editable fields and the supplied scope' => static function () use ($db): void {
        resetFixture($db);
        $scope = 'test-scope-a';
        $card = makeService($db, $scope)->dispatch('brainwash.card.read', ['actorKey' => 'npc:1']);
        expectSame('npc:1', $card['actorKey'], 'Stable actor key');
        expectSame('Aela', $card['label'], 'NPC label');
        expectSame($scope, $card['scope'], 'Timeline scope');
        expectSame([
            'core' => 'core seed', 'npc_static_bio' => 'bio seed', 'personality' => 'brave',
            'appearance' => 'iron armor', 'occupation' => 'hunter', 'skills' => 'bow',
            'speechstyle' => 'direct', 'goals' => 'protect Whiterun',
        ], $card['fields'], 'Only the allowlisted native card fields');
        expectSame('', $card['beliefs'], 'Empty Brainwash belief');
        expectSame(null, $card['draftId'], 'No draft initially');
        expectSame(null, $card['lastOperation'], 'No operation initially');
        expectTrue((bool)preg_match('/^[a-f0-9]{64}$/D', $card['revision']), 'Revision is a SHA-256 token');
    },
    'manual draft stores a selected proposal without changing core or history' => static function () use ($db): void {
        resetFixture($db);
        $scope = 'test-scope-a';
        $service = makeService($db, $scope);
        $card = $service->dispatch('brainwash.card.read', ['actorKey' => 'npc:1']);
        $draft = $service->dispatch('brainwash.draft', [
            'actorKey' => 'npc:1', 'expectedRevision' => $card['revision'],
            'operationId' => 'a8c8e856-81b2-4dcb-8d3d-3d6b643fc371', 'mode' => 'manual',
            'changes' => ['personality' => 'quietly observant'], 'instruction' => '',
        ]);
        expectSame('manual', $draft['origin'], 'Draft origin');
        expectSame('npc:1', $draft['actorKey'], 'Draft actor key');
        expectSame($card['scope'], $draft['scope'], 'Draft scope');
        expectSame($card['revision'], $draft['revision'], 'Draft original revision');
        expectSame(['personality' => 'brave'], $draft['before'], 'Draft before value');
        expectSame(['personality' => 'quietly observant'], $draft['changes'], 'Draft proposal');
        expectTrue((bool)preg_match('/^[0-9a-f-]{36}$/D', $draft['draftId']), 'Draft ID is a UUID');
        expectSame('brave', value($db, 'SELECT personality FROM core_npc_master WHERE id=1'), 'Core value remains unchanged');
        expectSame('0', value($db, 'SELECT count(*) FROM core_npc_master_history'), 'Draft creates no history row');
        expectSame('yes', value($db, "SELECT plugin_extended_data->'other_plugin'->>'keep' FROM core_npc_master WHERE id=1"), 'Other plugin namespace remains intact');
        expectSame($draft['draftId'], $service->dispatch('brainwash.card.read', ['actorKey' => 'npc:1'])['draftId'], 'Draft is available for review');
    },
    'Department base scope validates independently from Brainwash restore generation' => static function () use ($db): void {
        resetFixture($db);
        $scope = hash('sha256', 'active profile and restore generation');
        $base = hash('sha256', "qd-department-scope-v1\0" . '7' . "\0" . 'chim_profile_a' . "\0" . str_repeat('a', 32));
        $service = makeService($db, $scope, null, $base);
        expectSame($scope, $service->dispatch('brainwash.card.read', ['actorKey' => 'npc:1'], ['scopeKey' => $base])['scope'], 'Department base scope is accepted');
        expectFault('stale_context', static fn() => $service->dispatch('brainwash.card.read', ['actorKey' => 'npc:1'], ['scopeKey' => str_repeat('0', 64)]));
    },
    'apply snapshots once, changes accepted fields only, and duplicate operation is idempotent' => static function () use ($db): void {
        resetFixture($db);
        $scope = 'generation-a';
        $service = makeService($db, $scope);
        $draft = createDraft($service, 'personality', 'quietly observant');
        $result = applyDraft($service, $draft);
        expectSame('applied', $result['state'], 'Apply result state');
        expectSame('quietly observant', $result['card']['fields']['personality'], 'Selected field is applied');
        expectSame('hunter', value($db, 'SELECT occupation FROM core_npc_master WHERE id=1'), 'Unselected field is unchanged');
        expectSame('friendship unchanged', value($db, 'SELECT relationships FROM core_npc_master WHERE id=1'), 'Relationship data is unchanged');
        expectSame('yes', value($db, "SELECT plugin_extended_data->'other_plugin'->>'keep' FROM core_npc_master WHERE id=1"), 'Other plugin namespace is preserved');
        expectSame('1', value($db, 'SELECT count(*) FROM core_npc_master_history'), 'Apply created one history snapshot');
        expectSame(null, $result['card']['draftId'], 'Applied draft was cleared');
        expectSame('applied', applyDraft($service, $draft)['state'], 'Same operation is confirmed');
        expectSame('1', value($db, 'SELECT count(*) FROM core_npc_master_history'), 'Duplicate apply has no second write');
        expectFault('conflict', static fn() => $service->dispatch('brainwash.apply', ['actorKey' => 'npc:1', 'draftId' => $draft['draftId'],
            'operationId' => 'd745fa8e-9fd2-4d6a-b7ca-7cf81913c548', 'acceptedFields' => ['goals'], 'acceptBeliefs' => false]));
    },
    'an NPC identity rename invalidates an existing draft' => static function () use ($db): void {
        resetFixture($db);
        $scope = 'generation-a';
        $service = makeService($db, $scope);
        $draft = createDraft($service, 'personality', 'quietly observant');
        query($db, "UPDATE core_npc_master SET npc_name='Aela Renamed' WHERE id=1");
        expectFault('stale_revision', static fn() => applyDraft($service, $draft));
        expectSame('brave', value($db, 'SELECT personality FROM core_npc_master WHERE id=1'), 'Rename does not authorize the stale proposal');
        expectSame('0', value($db, 'SELECT count(*) FROM core_npc_master_history'), 'Stale identity creates no snapshot');
    },
    'refid and profile identity changes invalidate a draft' => static function () use ($db): void {
        foreach ([['refid', '0000000000000001'], ['profile_id', 17]] as [$column, $newValue]) {
            resetFixture($db);
            $scope = 'generation-a';
            $service = makeService($db, $scope);
            $draft = createDraft($service, 'personality', 'quietly observant');
            query($db, "UPDATE core_npc_master SET {$column}=$1 WHERE id=1", [(string)$newValue]);
            expectFault('stale_revision', static fn() => applyDraft($service, $draft));
            expectSame('0', value($db, 'SELECT count(*) FROM core_npc_master_history'), $column . ' reassignment creates no snapshot');
        }
    },
    'Department mutation fence surrounds draft persistence and rolls back a denied commit' => static function () use ($db): void {
        resetFixture($db);
        $scope = 'generation-a';
        $base = hash('sha256', 'department-base');
        $locked = false;
        $commits = 0;
        $fence = [
            'scopeKey' => $base,
            'assertCurrent' => static function (): void {},
            'withCurrentContext' => static fn(callable $callback): mixed => $callback(),
            'withLockedCurrentContext' => static function (callable $callback) use (&$locked): mixed {
                if ($locked) throw new RuntimeException('Non-reentrant lock was acquired twice.');
                $locked = true;
                try { return $callback(); } finally { $locked = false; }
            },
            'beginCommit' => static function () use (&$locked, &$commits): void {
                expectTrue($locked, 'Commit is marked only while the authoritative lock is held');
                $commits++;
            },
        ];
        $service = makeService($db, $scope, static function (array $input) use (&$locked): string {
            expectTrue(!$locked, 'Model generation runs outside the Department authorization lock');
            return '{"personality":"quietly observant"}';
        }, $base);
        $card = $service->dispatch('brainwash.card.read', ['actorKey' => 'npc:1'], $fence);
        $draft = $service->dispatch('brainwash.draft', ['actorKey' => 'npc:1', 'expectedRevision' => $card['revision'],
            'operationId' => 'a8c8e856-81b2-4dcb-8d3d-3d6b643fc371', 'mode' => 'ai', 'changes' => ['personality' => ''], 'instruction' => ''], $fence);
        expectSame('quietly observant', $draft['changes']['personality'], 'Fenced AI draft is persisted');
        expectSame(1, $commits, 'Draft commit is marked once after validation');

        $denied = $fence;
        $denied['beginCommit'] = static function (): void { throw new RuntimeException('revoked'); };
        $applyPayload = ['actorKey' => 'npc:1', 'draftId' => $draft['draftId'], 'operationId' => 'd745fa8e-9fd2-4d6a-b7ca-7cf81913c548',
            'acceptedFields' => ['personality'], 'acceptBeliefs' => false];
        expectFault('stale_context', static fn() => $service->dispatch('brainwash.apply', $applyPayload, $denied));
        expectSame('brave', value($db, 'SELECT personality FROM core_npc_master WHERE id=1'), 'Denied commit rolls back the native edit');
        expectSame('0', value($db, 'SELECT count(*) FROM core_npc_master_history'), 'Denied commit rolls back history');
        expectSame($draft['draftId'], $service->dispatch('brainwash.card.read', ['actorKey' => 'npc:1'], $fence)['draftId'], 'Denied apply leaves the draft available');

        resetFixture($db);
        $card = $service->dispatch('brainwash.card.read', ['actorKey' => 'npc:1'], $fence);
        expectFault('stale_context', static fn() => $service->dispatch('brainwash.draft', ['actorKey' => 'npc:1', 'expectedRevision' => $card['revision'],
            'operationId' => 'a8c8e856-81b2-4dcb-8d3d-3d6b643fc371', 'mode' => 'manual', 'changes' => ['personality' => 'quietly observant'], 'instruction' => ''], $denied));
        expectSame(null, $service->dispatch('brainwash.card.read', ['actorKey' => 'npc:1'], $fence)['draftId'], 'Revoked draft commit is rolled back');
    },
    'belief-only draft keeps empty field maps as JSON objects' => static function () use ($db): void {
        resetFixture($db);
        $scope = 'generation-a';
        $service = makeService($db, $scope);
        $card = $service->dispatch('brainwash.card.read', ['actorKey' => 'npc:1']);
        $draft = $service->dispatch('brainwash.draft', ['actorKey' => 'npc:1', 'expectedRevision' => $card['revision'],
            'operationId' => 'a8c8e856-81b2-4dcb-8d3d-3d6b643fc371', 'mode' => 'manual', 'changes' => [], 'instruction' => '', 'beliefs' => 'A fictional belief.']);
        expectTrue($draft['before'] instanceof stdClass, 'Empty before map is an object');
        expectTrue($draft['changes'] instanceof stdClass, 'Empty changes map is an object');
        $wire = json_decode(json_encode($draft, JSON_THROW_ON_ERROR), true, 16, JSON_THROW_ON_ERROR);
        expectSame([], $wire['before'], 'Wire before map remains an object-shaped empty mapping');
        expectSame([], $wire['changes'], 'Wire changes map remains an object-shaped empty mapping');
        expectSame('0', value($db, 'SELECT count(*) FROM core_npc_master_history'), 'Belief draft does not create a native history row');
    },
    'wire byte budget rejects oversized cards and escaped draft previews before saving' => static function () use ($db): void {
        resetFixture($db);
        $scope = 'generation-a';
        $service = makeService($db, $scope);
        $control = str_repeat(chr(1), 8192);
        query($db, 'UPDATE core_npc_master SET personality=$1 WHERE id=1', [$control]);
        $card = $service->dispatch('brainwash.card.read', ['actorKey' => 'npc:1']);
        $tooLargeDraft = str_replace(chr(1), chr(2), $control);
        expectFault('invalid_request', static fn() => $service->dispatch('brainwash.draft', ['actorKey' => 'npc:1', 'expectedRevision' => $card['revision'],
            'operationId' => 'a8c8e856-81b2-4dcb-8d3d-3d6b643fc371', 'mode' => 'manual',
            'changes' => ['personality' => $tooLargeDraft], 'instruction' => '']));
        expectSame(null, $service->dispatch('brainwash.card.read', ['actorKey' => 'npc:1'])['draftId'], 'Rejected escaped preview was never persisted');

        resetFixture($db);
        $service = makeService($db, $scope);
        query($db, 'UPDATE core_npc_master SET personality=$1,occupation=$1 WHERE id=1', [$control]);
        expectFault('invalid_request', static fn() => $service->dispatch('brainwash.card.read', ['actorKey' => 'npc:1']));
    },
    'apply validates its complete escaped response before commit' => static function () use ($db): void {
        resetFixture($db);
        $scope = 'generation-a';
        $service = makeService($db, $scope);
        $plain = str_repeat('p', 8192);
        $control = str_repeat(chr(1), 8192);
        query($db, 'UPDATE core_npc_master SET personality=$1,occupation=$2 WHERE id=1', [$plain, $control]);
        $card = $service->dispatch('brainwash.card.read', ['actorKey' => 'npc:1']);
        $draft = $service->dispatch('brainwash.draft', ['actorKey' => 'npc:1', 'expectedRevision' => $card['revision'],
            'operationId' => 'a8c8e856-81b2-4dcb-8d3d-3d6b643fc371', 'mode' => 'manual',
            'changes' => ['personality' => str_replace('p', chr(2), $plain)], 'instruction' => '']);
        expectFault('invalid_request', static fn() => applyDraft($service, $draft));
        expectSame($plain, value($db, 'SELECT personality FROM core_npc_master WHERE id=1'), 'Rejected response size rolls back the selected edit');
        expectSame('0', value($db, 'SELECT count(*) FROM core_npc_master_history'), 'Rejected response size creates no history');
        expectSame($draft['draftId'], $service->dispatch('brainwash.card.read', ['actorKey' => 'npc:1'])['draftId'], 'Rejected apply leaves the draft reviewable');
    },
    'undo validates the restored merged card before commit' => static function () use ($db): void {
        resetFixture($db);
        $scope = 'generation-a';
        $service = makeService($db, $scope);
        $largeCore = str_repeat('c', 8192);
        $largePersonality = str_repeat('p', 8192);
        $goals = str_repeat('g', 3000);
        query($db, 'UPDATE core_npc_master SET core=$1,personality=$2,goals=$3 WHERE id=1', [$largeCore, $largePersonality, $goals]);
        $draft = createDraft($service, 'core', 'x');
        applyDraft($service, $draft, 'd745fa8e-9fd2-4d6a-b7ca-7cf81913c548', ['core']);
        query($db, 'UPDATE core_npc_master SET goals=$1 WHERE id=1', [str_repeat('g', 8192)]);
        $undoPayload = ['actorKey' => 'npc:1', 'operationId' => 'fbdbd901-0318-49bf-97a7-f5412a64aec0',
            'appliedOperationId' => 'd745fa8e-9fd2-4d6a-b7ca-7cf81913c548'];
        expectFault('invalid_request', static fn() => $service->dispatch('brainwash.undo', $undoPayload));
        expectSame('x', value($db, 'SELECT core FROM core_npc_master WHERE id=1'), 'Rejected undo leaves the applied field untouched');
        expectSame('1', value($db, 'SELECT count(*) FROM core_npc_master_history'), 'Rejected undo adds no snapshot');
        expectSame('applied', $service->dispatch('brainwash.card.read', ['actorKey' => 'npc:1'])['lastOperation']['state'], 'Rejected undo leaves receipt applied');
    },
    'failed apply rolls back both history and native edits' => static function () use ($db): void {
        resetFixture($db);
        $scope = 'generation-a';
        $service = makeService($db, $scope);
        $draft = createDraft($service, 'personality', 'reject this write');
        query($db, "CREATE FUNCTION brainwash_test_reject() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN IF NEW.personality='reject this write' THEN RAISE EXCEPTION 'test'; END IF; RETURN NEW; END $$");
        query($db, 'CREATE TRIGGER brainwash_test_reject BEFORE UPDATE ON core_npc_master FOR EACH ROW EXECUTE FUNCTION brainwash_test_reject()');
        expectFault('unavailable', static fn() => applyDraft($service, $draft));
        expectSame('brave', value($db, 'SELECT personality FROM core_npc_master WHERE id=1'), 'Failed apply left card unchanged');
        expectSame('0', value($db, 'SELECT count(*) FROM core_npc_master_history'), 'Failed apply rolled back snapshot');
        query($db, 'DROP TRIGGER brainwash_test_reject ON core_npc_master');
        query($db, 'DROP FUNCTION brainwash_test_reject()');
    },
    'undo restores only touched fields and preserves an unrelated later edit' => static function () use ($db): void {
        resetFixture($db);
        $scope = 'generation-a';
        $service = makeService($db, $scope);
        $draft = createDraft($service, 'personality', 'quietly observant');
        applyDraft($service, $draft);
        query($db, "UPDATE core_npc_master SET occupation='innkeeper' WHERE id=1");
        $payload = ['actorKey' => 'npc:1', 'operationId' => 'fbdbd901-0318-49bf-97a7-f5412a64aec0', 'appliedOperationId' => 'd745fa8e-9fd2-4d6a-b7ca-7cf81913c548'];
        $undo = $service->dispatch('brainwash.undo', $payload);
        expectSame('undone', $undo['state'], 'Undo result');
        expectSame('brave', $undo['card']['fields']['personality'], 'Touched personality is restored');
        expectSame('innkeeper', $undo['card']['fields']['occupation'], 'Later unrelated occupation edit survives');
        expectSame('2', value($db, 'SELECT count(*) FROM core_npc_master_history'), 'Undo added one snapshot');
        expectSame('undone', $service->dispatch('brainwash.undo', $payload)['state'], 'Duplicate undo confirms');
        expectSame('2', value($db, 'SELECT count(*) FROM core_npc_master_history'), 'Duplicate undo does not write');
        expectFault('conflict', static fn() => applyDraft($service, $draft));
        expectSame('2', value($db, 'SELECT count(*) FROM core_npc_master_history'), 'An apply replay after undo is refused without writing');
    },
    'undo refuses overlap and generation changes invalidate a draft' => static function () use ($db): void {
        resetFixture($db);
        $scope = 'generation-a';
        $service = makeService($db, $scope);
        $draft = createDraft($service, 'personality', 'quietly observant');
        applyDraft($service, $draft);
        query($db, "UPDATE core_npc_master SET personality='changed elsewhere' WHERE id=1");
        expectFault('conflict', static fn() => $service->dispatch('brainwash.undo', ['actorKey' => 'npc:1', 'operationId' => 'fbdbd901-0318-49bf-97a7-f5412a64aec0', 'appliedOperationId' => 'd745fa8e-9fd2-4d6a-b7ca-7cf81913c548']));
        expectSame('changed elsewhere', value($db, 'SELECT personality FROM core_npc_master WHERE id=1'), 'Overlapping edit remains');

        resetFixture($db);
        $scope = 'generation-a';
        $service = makeService($db, $scope);
        $draft = createDraft($service, 'personality', 'quietly observant');
        $scope = 'generation-b';
        $card = $service->dispatch('brainwash.card.read', ['actorKey' => 'npc:1']);
        expectSame(null, $card['draftId'], 'Old generation draft is hidden');
        expectFault('stale_context', static fn() => applyDraft($service, $draft));
    },
    'AI draft uses only selected fields and rejects extra model keys' => static function () use ($db): void {
        resetFixture($db);
        $scope = 'generation-a';
        $calls = 0;
        $service = makeService($db, $scope, static function (array $input) use (&$calls): string {
            $calls++;
            expectSame(['personality'], $input['selectedFields'], 'Only selected fields reach generator');
            return '{"personality":"quietly observant"}';
        });
        $card = $service->dispatch('brainwash.card.read', ['actorKey' => 'npc:1']);
        $draft = $service->dispatch('brainwash.draft', ['actorKey' => 'npc:1', 'expectedRevision' => $card['revision'],
            'operationId' => 'a8c8e856-81b2-4dcb-8d3d-3d6b643fc371', 'mode' => 'ai', 'changes' => ['personality' => ''], 'instruction' => '']);
        expectSame(1, $calls, 'Generator called once');
        expectSame('quietly observant', $draft['changes']['personality'], 'Generated result remains draft text');
        resetFixture($db);
        $scope = 'generation-a';
        $bad = makeService($db, $scope, static fn(array $input): string => '{"personality":"ok","goals":"unselected"}');
        $card = $bad->dispatch('brainwash.card.read', ['actorKey' => 'npc:1']);
        expectFault('unavailable', static fn() => $bad->dispatch('brainwash.draft', ['actorKey' => 'npc:1', 'expectedRevision' => $card['revision'],
            'operationId' => 'a8c8e856-81b2-4dcb-8d3d-3d6b643fc371', 'mode' => 'ai', 'changes' => ['personality' => ''], 'instruction' => '']));
    },
    'duplicate NPC names stay isolated by stable actor ID in AI prompts' => static function () use ($db): void {
        resetFixture($db);
        query($db, "UPDATE core_npc_master SET npc_name='Aela' WHERE id=2");
        query($db, "UPDATE core_npc_master SET personality='other NPC secret' WHERE id=2");
        $scope = 'generation-a';
        $service = makeService($db, $scope, static function (array $input): string {
            expectSame('npc:1', $input['actorKey'], 'Generator receives the exact selected actor identity');
            expectSame('Aela', $input['label'], 'Duplicate display name does not alter the selected identity');
            expectSame('brave', $input['fields']['personality'], 'Only selected actor card text reaches the generator');
            expectTrue(!str_contains(json_encode($input, JSON_THROW_ON_ERROR), 'other NPC secret'), 'Neighbor NPC data is absent from the prompt input');
            return '{"personality":"quietly observant"}';
        });
        $card = $service->dispatch('brainwash.card.read', ['actorKey' => 'npc:1']);
        $draft = $service->dispatch('brainwash.draft', ['actorKey' => 'npc:1', 'expectedRevision' => $card['revision'],
            'operationId' => 'a8c8e856-81b2-4dcb-8d3d-3d6b643fc371', 'mode' => 'ai', 'changes' => ['personality' => ''], 'instruction' => '']);
        expectSame('quietly observant', $draft['changes']['personality'], 'Selected NPC receives only its own generated proposal');
    },
    'catalog bounds default listing and lets a name search reach actors past the cap' => static function () use ($db): void {
        resetFixture($db);
        for ($id = 3; $id <= 66; $id++) query($db, 'INSERT INTO core_npc_master(id,npc_name) VALUES($1,$2)', [$id, 'NPC ' . $id]);
        $scope = 'generation-a';
        $service = makeService($db, $scope);
        $catalog = $service->dispatch('brainwash.catalog.read', []);
        expectSame(64, count($catalog['actors']), 'Default listing is capped');
        expectSame(true, $catalog['limited'], 'Overflow is reported');
        $search = $service->dispatch('brainwash.catalog.read', ['query' => 'NPC 66']);
        expectSame([['actorKey' => 'npc:66', 'label' => 'NPC 66']], $search['actors'], 'Search reaches an actor after row 64');
        expectSame(false, $search['limited'], 'Narrow search has no overflow');
        expectFault('invalid_request', static fn() => $service->dispatch('brainwash.catalog.read', ['query' => str_repeat('x', 129)]));
    },
];

$failed = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
        echo "PASS {$name}\n";
    } catch (Throwable $e) {
        $failed++;
        fwrite(STDERR, "FAIL {$name}: {$e->getMessage()}\n");
    }
}
if ($failed > 0) exit(1);
echo count($tests) . " PostgreSQL tests passed.\n";
