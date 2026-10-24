<?php
declare(strict_types=1);

final class BrainwashFault extends RuntimeException
{
    public readonly string $wireCode;

    public function __construct(string $wireCode)
    {
        if (!in_array($wireCode, ['invalid_request', 'not_found', 'stale_revision', 'stale_context', 'conflict', 'unavailable', 'forbidden'], true)) {
            $wireCode = 'unavailable';
        }
        $this->wireCode = $wireCode;
        parent::__construct($wireCode);
    }
}

final class BrainwashCallbackFailure extends RuntimeException
{
    public function __construct(public readonly Throwable $original)
    {
        parent::__construct('Brainwash callback failed.', 0, $original);
    }
}

final class BrainwashService
{
    public const FIELDS = ['core', 'npc_static_bio', 'personality', 'appearance', 'occupation', 'skills', 'speechstyle', 'goals'];
    private const PLUGIN_ID = 'brainwash';
    private const FIELD_BYTES = 8192;
    private const CARD_BYTES = 20000;
    private const BELIEF_BYTES = 4000;
    private const INSTRUCTION_BYTES = 2000;
    private const WIRE_BYTES = 60000;

    private mixed $db;
    private Closure $scopeProvider;
    private Closure $baseScopeProvider;
    private ?Closure $generator;
    private bool $transactionOpen = false;

    public function __construct(mixed $db, callable $scopeProvider, ?callable $generator = null, ?callable $baseScopeProvider = null)
    {
        if (!function_exists('pg_query_params')) {
            throw new InvalidArgumentException('A PostgreSQL connection is required.');
        }
        try {
            if (pg_connection_status($db) !== PGSQL_CONNECTION_OK) throw new InvalidArgumentException('A PostgreSQL connection is required.');
        } catch (Throwable) {
            throw new InvalidArgumentException('A PostgreSQL connection is required.');
        }
        $this->db = $db;
        $this->scopeProvider = Closure::fromCallable($scopeProvider);
        $this->baseScopeProvider = Closure::fromCallable($baseScopeProvider ?? $scopeProvider);
        $this->generator = $generator === null ? null : Closure::fromCallable($generator);
    }

    public function dispatch(string $operation, array $payload, array $context = []): array
    {
        try {
            $scope = $this->scope();
            $this->checkContext($context);
            return match ($operation) {
                'brainwash.catalog.read' => $this->withCurrentContext($context, fn(): array => $this->catalog($payload)),
                'brainwash.card.read' => $this->withCurrentContext($context, fn(): array => $this->readCard($payload, $scope)),
                'brainwash.draft' => $this->draft($payload, $scope, $context),
                'brainwash.apply' => $this->withLockedCurrentContext($context, fn(): array => $this->apply($payload, $scope, $context)),
                'brainwash.undo' => $this->withLockedCurrentContext($context, fn(): array => $this->undo($payload, $scope, $context)),
                default => throw new BrainwashFault('invalid_request'),
            };
        } catch (BrainwashFault $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->rollback();
            throw new BrainwashFault('unavailable');
        }
    }

    private function catalog(array $payload): array
    {
        $this->onlyKeys($payload, ['query']);
        $query = array_key_exists('query', $payload) ? $this->text($payload['query'], 128) : '';
        $rows = $query === ''
            ? $this->all('SELECT id,npc_name FROM core_npc_master ORDER BY id LIMIT 65')
            : $this->all('SELECT id,npc_name FROM core_npc_master WHERE position(lower($1) in lower(npc_name)) > 0 ORDER BY id LIMIT 65', [$query]);
        $limited = count($rows) > 64;
        $actors = [];
        foreach (array_slice($rows, 0, 64) as $row) {
            $id = (int)($row['id'] ?? 0);
            $label = $row['npc_name'] ?? null;
            if ($id <= 0 || !is_string($label) || strlen($label) > 512 || !preg_match('//u', $label)) {
                throw new BrainwashFault('unavailable');
            }
            $actors[] = ['actorKey' => 'npc:' . $id, 'label' => $label];
        }
        return ['actors' => $actors, 'limited' => $limited];
    }

    private function readCard(array $payload, string $scope): array
    {
        $this->onlyKeys($payload, ['actorKey']);
        $actorId = $this->actorId($payload['actorKey'] ?? null);
        $row = $this->actorRow($actorId);
        if ($row === null) throw new BrainwashFault('not_found');
        return $this->card($row, $scope);
    }

    private function draft(array $payload, string $scope, array $context): array
    {
        $this->onlyKeys($payload, ['actorKey', 'expectedRevision', 'operationId', 'mode', 'changes', 'instruction', 'beliefs']);
        $actorId = $this->actorId($payload['actorKey'] ?? null);
        $expectedRevision = $this->revisionToken($payload['expectedRevision'] ?? null);
        $operationId = $this->uuid($payload['operationId'] ?? null);
        $mode = $payload['mode'] ?? null;
        if (!in_array($mode, ['manual', 'ai'], true)) throw new BrainwashFault('invalid_request');
        $instruction = $this->text($payload['instruction'] ?? null, self::INSTRUCTION_BYTES);
        $inputChanges = $this->selectedFields($payload['changes'] ?? null, $mode === 'ai');
        $selectBeliefs = array_key_exists('beliefs', $payload);
        $inputBeliefs = $selectBeliefs ? $this->text($payload['beliefs'], self::BELIEF_BYTES) : null;
        if ($mode === 'ai' && $selectBeliefs && $inputBeliefs !== '') throw new BrainwashFault('invalid_request');
        if (!$inputChanges && !$selectBeliefs) throw new BrainwashFault('invalid_request');

        $normalizedChanges = $inputChanges;
        ksort($normalizedChanges);
        $inputHash = $this->hashJson([
            'actorKey' => 'npc:' . $actorId,
            'expectedRevision' => $expectedRevision,
            'mode' => $mode,
            'changes' => $normalizedChanges,
            'instruction' => $instruction,
            'beliefsSelected' => $selectBeliefs,
            'beliefs' => $inputBeliefs,
        ]);

        [$initialCard, $initialNamespace] = $this->withCurrentContext($context, function () use ($actorId, $scope, $expectedRevision): array {
            $initial = $this->actorRow($actorId);
            if ($initial === null) throw new BrainwashFault('not_found');
            $card = $this->card($initial, $scope);
            if (!hash_equals($card['revision'], $expectedRevision)) throw new BrainwashFault('stale_revision');
            return [$card, $this->namespace($initial)];
        });
        $oldDraft = $initialNamespace['draft'] ?? null;
        if (is_array($oldDraft) && ($oldDraft['operationId'] ?? null) === $operationId) {
            if (!hash_equals((string)($oldDraft['inputHash'] ?? ''), $inputHash)) throw new BrainwashFault('conflict');
            return $this->publicDraft($oldDraft, 'npc:' . $actorId);
        }

        if ($mode === 'ai') {
            if ($this->generator === null) throw new BrainwashFault('unavailable');
            $generated = ($this->generator)([
                'actorKey' => 'npc:' . $actorId,
                'label' => $initialCard['label'],
                'fields' => $initialCard['fields'],
                'beliefs' => $initialCard['beliefs'],
                'selectedFields' => array_keys($inputChanges),
                'selectBeliefs' => $selectBeliefs,
                'instruction' => $instruction,
            ]);
            $changes = $this->parseGenerated($generated, array_keys($inputChanges), $selectBeliefs, $initialCard['fields']);
            $beliefs = $selectBeliefs ? $changes['beliefs'] : null;
            unset($changes['beliefs']);
        } else {
            $changes = $inputChanges;
            $beliefs = $inputBeliefs;
        }

        $this->validateCardSize(array_replace($initialCard['fields'], $changes));
        return $this->withLockedCurrentContext($context, function () use ($actorId, $scope, $context, $operationId, $inputHash, $expectedRevision, $mode, $changes, $selectBeliefs, $beliefs): array {
            $this->begin();
            try {
                $row = $this->actorRow($actorId, true);
                if ($row === null) throw new BrainwashFault('not_found');
                if (!hash_equals($scope, $this->scope())) throw new BrainwashFault('stale_context');
                $this->checkContext($context, false);
                $namespace = $this->namespace($row);
                $existing = $namespace['draft'] ?? null;
                if (is_array($existing) && ($existing['operationId'] ?? null) === $operationId) {
                    if (!hash_equals((string)($existing['inputHash'] ?? ''), $inputHash)) throw new BrainwashFault('conflict');
                    $this->commit();
                    return $this->publicDraft($existing, 'npc:' . $actorId);
                }
                $current = $this->card($row, $scope);
                if (!hash_equals($current['revision'], $expectedRevision)) throw new BrainwashFault('stale_revision');

                $before = [];
                foreach (array_keys($changes) as $field) $before[$field] = $current['fields'][$field];
                $draft = [
                    'draftId' => $this->newUuid(),
                    'operationId' => $operationId,
                    'inputHash' => $inputHash,
                    'scope' => $scope,
                    'revision' => $expectedRevision,
                    'origin' => $mode,
                    'before' => $before,
                    'changes' => $changes,
                ];
                if ($selectBeliefs) {
                    $draft['beliefsBefore'] = $current['beliefs'];
                    $draft['beliefs'] = $beliefs;
                }
                $publicDraft = $this->publicDraft($draft, 'npc:' . $actorId);
                $namespace['draft'] = $draft;
                $this->writeNamespace($actorId, $namespace);
                $this->beginCommit($context);
                $this->commit();
                return $publicDraft;
            } catch (Throwable $e) {
                $this->rollback();
                throw $e;
            }
        });
    }

    private function apply(array $payload, string $scope, array $context): array
    {
        $this->onlyKeys($payload, ['actorKey', 'draftId', 'operationId', 'acceptedFields', 'acceptBeliefs']);
        $actorId = $this->actorId($payload['actorKey'] ?? null);
        $draftId = $this->uuid($payload['draftId'] ?? null);
        $operationId = $this->uuid($payload['operationId'] ?? null);
        $accepted = $payload['acceptedFields'] ?? null;
        $acceptBeliefs = $payload['acceptBeliefs'] ?? null;
        if (!is_array($accepted) || !array_is_list($accepted) || count($accepted) > count(self::FIELDS) || !is_bool($acceptBeliefs)) {
            throw new BrainwashFault('invalid_request');
        }
        $selected = [];
        foreach ($accepted as $field) {
            if (!is_string($field) || !in_array($field, self::FIELDS, true) || isset($selected[$field])) throw new BrainwashFault('invalid_request');
            $selected[$field] = true;
        }
        $fieldNames = array_keys($selected);
        sort($fieldNames);
        $inputHash = $this->hashJson(['actorKey' => 'npc:' . $actorId, 'draftId' => $draftId,
            'acceptedFields' => $fieldNames, 'acceptBeliefs' => $acceptBeliefs]);

        $this->begin();
        try {
            $row = $this->actorRow($actorId, true);
            if ($row === null) throw new BrainwashFault('not_found');
            if (!hash_equals($scope, $this->scope())) throw new BrainwashFault('stale_context');
            $this->checkContext($context, false);
            $namespace = $this->namespace($row);
            $receipt = $namespace['receipt'] ?? null;
            if (is_array($receipt) && ($receipt['operationId'] ?? null) === $operationId) {
                if (($receipt['scope'] ?? null) !== $scope) throw new BrainwashFault('stale_context');
                if (($receipt['state'] ?? null) !== 'applied') throw new BrainwashFault('conflict');
                if (!hash_equals((string)($receipt['applyInputHash'] ?? ''), $inputHash)) throw new BrainwashFault('conflict');
                $result = ['operationId' => $operationId, 'state' => 'applied', 'card' => $this->card($row, $scope)];
                $this->commit();
                return $result;
            }

            $draft = $namespace['draft'] ?? null;
            if (!is_array($draft) || !hash_equals((string)($draft['draftId'] ?? ''), $draftId)) throw new BrainwashFault('conflict');
            if (($draft['scope'] ?? null) !== $scope) throw new BrainwashFault('stale_context');
            $current = $this->card($row, $scope);
            if (!hash_equals((string)($draft['revision'] ?? ''), $current['revision'])) throw new BrainwashFault('stale_revision');
            $draftChanges = $draft['changes'] ?? null;
            if (!is_array($draftChanges)) throw new BrainwashFault('conflict');

            $nativeChanges = [];
            $before = [];
            foreach ($fieldNames as $field) {
                if (!array_key_exists($field, $draftChanges) || !is_string($draftChanges[$field])) throw new BrainwashFault('conflict');
                $after = $this->text($draftChanges[$field], self::FIELD_BYTES);
                if ($current['fields'][$field] === $after) continue;
                $nativeChanges[$field] = $after;
                $before[$field] = $row[$field] ?? null;
            }

            $beliefsBefore = $current['beliefs'];
            $beliefsAfter = null;
            if ($acceptBeliefs) {
                if (!array_key_exists('beliefs', $draft) || !is_string($draft['beliefs'])) throw new BrainwashFault('conflict');
                $beliefsAfter = $this->text($draft['beliefs'], self::BELIEF_BYTES);
            }
            if ($nativeChanges === [] && (!$acceptBeliefs || $beliefsAfter === $beliefsBefore)) throw new BrainwashFault('invalid_request');
            $this->validateCardSize(array_replace($current['fields'], $nativeChanges));

            $this->snapshot($actorId);
            if ($acceptBeliefs) $namespace['beliefs'] = $beliefsAfter;
            unset($namespace['draft']);
            $receipt = [
                'scope' => $scope,
                'operationId' => $operationId,
                'applyInputHash' => $inputHash,
                'state' => 'applied',
                'before' => $before,
                'after' => $nativeChanges,
                'acceptedFields' => array_keys($nativeChanges),
            ];
            if ($acceptBeliefs) {
                $receipt['beliefsBefore'] = $beliefsBefore;
                $receipt['beliefsAfter'] = $beliefsAfter;
            }
            $namespace['receipt'] = $receipt;
            $this->updateActor($actorId, $nativeChanges, $namespace);
            $updated = $this->actorRow($actorId, true);
            if ($updated === null) throw new BrainwashFault('not_found');
            if (!hash_equals($scope, $this->scope())) throw new BrainwashFault('stale_context');
            $this->checkContext($context, false);
            $result = ['operationId' => $operationId, 'state' => 'applied', 'card' => $this->card($updated, $scope)];
            $this->beginCommit($context);
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    private function undo(array $payload, string $scope, array $context): array
    {
        $this->onlyKeys($payload, ['actorKey', 'operationId', 'appliedOperationId']);
        $actorId = $this->actorId($payload['actorKey'] ?? null);
        $operationId = $this->uuid($payload['operationId'] ?? null);
        $appliedOperationId = $this->uuid($payload['appliedOperationId'] ?? null);
        $inputHash = $this->hashJson(['actorKey' => 'npc:' . $actorId, 'appliedOperationId' => $appliedOperationId]);

        $this->begin();
        try {
            $row = $this->actorRow($actorId, true);
            if ($row === null) throw new BrainwashFault('not_found');
            if (!hash_equals($scope, $this->scope())) throw new BrainwashFault('stale_context');
            $this->checkContext($context, false);
            $namespace = $this->namespace($row);
            $receipt = $namespace['receipt'] ?? null;
            if (!is_array($receipt)) throw new BrainwashFault('conflict');
            if (($receipt['scope'] ?? null) !== $scope) throw new BrainwashFault('stale_context');
            if (($receipt['undoOperationId'] ?? null) === $operationId) {
                if (!hash_equals((string)($receipt['undoInputHash'] ?? ''), $inputHash)) throw new BrainwashFault('conflict');
                $result = ['operationId' => $operationId, 'state' => 'undone', 'card' => $this->card($row, $scope)];
                $this->commit();
                return $result;
            }
            if (($receipt['state'] ?? null) !== 'applied' || ($receipt['operationId'] ?? null) !== $appliedOperationId) {
                throw new BrainwashFault('conflict');
            }
            $current = $this->card($row, $scope);
            $after = $receipt['after'] ?? null;
            $before = $receipt['before'] ?? null;
            if (!is_array($after) || !is_array($before)) throw new BrainwashFault('conflict');
            foreach ($after as $field => $value) {
                if (!is_string($field) || !in_array($field, self::FIELDS, true) || !is_string($value)
                    || !array_key_exists($field, $before) || $current['fields'][$field] !== $value) {
                    throw new BrainwashFault('conflict');
                }
            }
            $hasBeliefs = array_key_exists('beliefsAfter', $receipt);
            if ($hasBeliefs && ($namespace['beliefs'] ?? '') !== $receipt['beliefsAfter']) throw new BrainwashFault('conflict');
            if ($after === [] && !$hasBeliefs) throw new BrainwashFault('conflict');

            $this->snapshot($actorId);
            foreach ($before as $field => $value) {
                if (!array_key_exists($field, $after)) throw new BrainwashFault('conflict');
            }
            if ($hasBeliefs) $namespace['beliefs'] = $receipt['beliefsBefore'];
            $receipt['state'] = 'undone';
            $receipt['undoOperationId'] = $operationId;
            $receipt['undoInputHash'] = $inputHash;
            $namespace['receipt'] = $receipt;
            $this->updateActor($actorId, $before, $namespace);
            $updated = $this->actorRow($actorId, true);
            if ($updated === null) throw new BrainwashFault('not_found');
            if (!hash_equals($scope, $this->scope())) throw new BrainwashFault('stale_context');
            $this->checkContext($context, false);
            $result = ['operationId' => $operationId, 'state' => 'undone', 'card' => $this->card($updated, $scope)];
            $this->beginCommit($context);
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    private function card(array $row, string $scope): array
    {
        $id = (int)($row['id'] ?? 0);
        $label = $row['npc_name'] ?? null;
        if ($id <= 0 || !is_string($label) || strlen($label) > 512 || !preg_match('//u', $label)) {
            throw new BrainwashFault('unavailable');
        }
        $fields = [];
        foreach (self::FIELDS as $field) {
            $value = $row[$field] ?? '';
            if (!is_string($value)) throw new BrainwashFault('unavailable');
            $fields[$field] = $value;
        }
        $this->validateCardSize($fields);
        $namespace = $this->namespace($row);
        $beliefs = $namespace['beliefs'] ?? '';
        if (!is_string($beliefs) || !$this->validText($beliefs) || strlen($beliefs) > self::BELIEF_BYTES) {
            throw new BrainwashFault('unavailable');
        }
        $draft = $namespace['draft'] ?? null;
        $revision = $this->revision($scope, $fields, $beliefs, [
            'id' => $id,
            'label' => $label,
            'refid' => $row['refid'] ?? null,
            'profileId' => isset($row['profile_id']) ? (int)$row['profile_id'] : null,
        ]);
        $draftId = is_array($draft) && ($draft['scope'] ?? null) === $scope && ($draft['revision'] ?? null) === $revision && $this->isUuid($draft['draftId'] ?? null)
            ? strtolower((string)$draft['draftId']) : null;
        $receipt = $namespace['receipt'] ?? null;
        $lastOperation = null;
        if (is_array($receipt) && ($receipt['scope'] ?? null) === $scope) {
            if (($receipt['state'] ?? null) === 'applied' && $this->isUuid($receipt['operationId'] ?? null)) {
                $lastOperation = ['operationId' => strtolower($receipt['operationId']), 'state' => 'applied'];
            } elseif (($receipt['state'] ?? null) === 'undone' && $this->isUuid($receipt['undoOperationId'] ?? null)) {
                $lastOperation = ['operationId' => strtolower($receipt['undoOperationId']), 'state' => 'undone'];
            }
        }
        $card = [
            'actorKey' => 'npc:' . $id,
            'label' => $label,
            'scope' => $scope,
            'revision' => $revision,
            'fields' => $fields,
            'beliefs' => $beliefs,
            'draftId' => $draftId,
            'lastOperation' => $lastOperation,
        ];
        $this->validateWireSize($card);
        return $card;
    }

    private function actorRow(int $id, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT id,npc_name,refid,profile_id,' . implode(',', self::FIELDS) . ",plugin_extended_data -> 'brainwash' AS brainwash_data FROM core_npc_master WHERE id=$1";
        if ($forUpdate) $sql .= ' FOR UPDATE';
        return $this->one($sql, [$id]);
    }

    private function namespace(array $row): array
    {
        $raw = $row['brainwash_data'] ?? null;
        if ($raw === null || $raw === '') return [];
        $data = json_decode((string)$raw, false, 32, JSON_THROW_ON_ERROR);
        if (!$data instanceof stdClass) throw new BrainwashFault('unavailable');
        return json_decode((string)$raw, true, 32, JSON_THROW_ON_ERROR);
    }

    private function writeNamespace(int $actorId, array $namespace): void
    {
        $this->updateActor($actorId, [], $namespace);
    }

    private function updateActor(int $actorId, array $nativeChanges, array $namespace): void
    {
        $assignments = [];
        $params = [];
        foreach ($nativeChanges as $field => $value) {
            if (!in_array($field, self::FIELDS, true) || (!is_string($value) && $value !== null)) throw new BrainwashFault('invalid_request');
            $params[] = $value;
            $assignments[] = $field . '=$' . count($params);
        }
        $json = json_encode((object)$namespace, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $params[] = $json;
        $jsonIndex = count($params);
        $assignments[] = "plugin_extended_data=jsonb_set(COALESCE(plugin_extended_data,'{}'::jsonb), ARRAY['brainwash'], \${$jsonIndex}::jsonb, true)";
        $params[] = $actorId;
        $idIndex = count($params);
        $row = $this->one('UPDATE core_npc_master SET ' . implode(',', $assignments) . ' WHERE id=$' . $idIndex . ' RETURNING id', $params);
        if ($row === null) throw new BrainwashFault('not_found');
    }

    private function snapshot(int $actorId): void
    {
        $historyColumns = 'npc_id,npc_name,npc_favorite,lock_profile,prompt_head,npc_static_bio,oghma_knowledge_tags,emote_moods,personality,relationships,occupation,appearance,skills,speechstyle,goals,voiceid,metadata,gender,race,refid,profile_id,dynamic_profile,extended_data,plugin_extended_data,md5,gamets_last_updated,core,base,tags';
        $sourceColumns = 'id,npc_name,npc_favorite,lock_profile,prompt_head,npc_static_bio,oghma_knowledge_tags,emote_moods,personality,relationships,occupation,appearance,skills,speechstyle,goals,voiceid,metadata,gender,race,refid,profile_id,dynamic_profile,extended_data,plugin_extended_data,md5,gamets_last_updated,core,base,tags';
        $result = $this->query('INSERT INTO core_npc_master_history (' . $historyColumns . ') SELECT ' . $sourceColumns . ' FROM core_npc_master WHERE id=$1', [$actorId]);
        if (pg_affected_rows($result) !== 1) throw new BrainwashFault('not_found');
    }

    private function selectedFields(mixed $changes, bool $selectionOnly): array
    {
        if (!is_array($changes) || count($changes) > count(self::FIELDS)) throw new BrainwashFault('invalid_request');
        $selected = [];
        foreach ($changes as $field => $value) {
            if (!is_string($field) || !in_array($field, self::FIELDS, true)) throw new BrainwashFault('invalid_request');
            $text = $this->text($value, self::FIELD_BYTES);
            if ($selectionOnly && $text !== '') throw new BrainwashFault('invalid_request');
            $selected[$field] = $text;
        }
        return $selected;
    }

    private function parseGenerated(mixed $raw, array $selected, bool $selectBeliefs, array $currentFields): array
    {
        if (!is_string($raw) || strlen($raw) > 65536) throw new BrainwashFault('unavailable');
        try {
            $decoded = json_decode($raw, false, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new BrainwashFault('unavailable');
        }
        if (!$decoded instanceof stdClass) throw new BrainwashFault('unavailable');
        $data = get_object_vars($decoded);
        $expected = $selected;
        if ($selectBeliefs) $expected[] = 'beliefs';
        $keys = array_keys($data);
        sort($keys);
        sort($expected);
        if ($keys !== $expected) throw new BrainwashFault('unavailable');
        foreach ($selected as $field) $data[$field] = $this->text($data[$field], self::FIELD_BYTES);
        if ($selectBeliefs) $data['beliefs'] = $this->text($data['beliefs'], self::BELIEF_BYTES);
        $newFields = $currentFields;
        foreach ($selected as $field) $newFields[$field] = $data[$field];
        $this->validateCardSize($newFields);
        return $data;
    }

    private function publicDraft(array $draft, string $actorKey): array
    {
        $result = [
            'actorKey' => $actorKey,
            'scope' => $draft['scope'],
            'revision' => $draft['revision'],
            'draftId' => strtolower((string)$draft['draftId']),
            'origin' => $draft['origin'],
            'before' => $draft['before'] === [] ? new stdClass() : $draft['before'],
            'changes' => $draft['changes'] === [] ? new stdClass() : $draft['changes'],
        ];
        foreach (['beliefsBefore', 'beliefs'] as $field) {
            if (array_key_exists($field, $draft)) $result[$field] = $draft[$field];
        }
        $this->validateWireSize($result);
        return $result;
    }

    private function validateCardSize(array $fields): void
    {
        $total = 0;
        foreach (self::FIELDS as $field) {
            $value = $fields[$field] ?? '';
            if (!is_string($value) || !$this->validText($value) || strlen($value) > self::FIELD_BYTES) {
                throw new BrainwashFault('invalid_request');
            }
            $total += strlen($value);
        }
        if ($total > self::CARD_BYTES) throw new BrainwashFault('invalid_request');
    }

    private function validateWireSize(array $dto): void
    {
        try {
            $encoded = json_encode($dto, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            throw new BrainwashFault('invalid_request');
        }
        if (strlen($encoded) > self::WIRE_BYTES) throw new BrainwashFault('invalid_request');
    }

    private function revision(string $scope, array $fields, string $beliefs, array $identity): string
    {
        $canonical = ['scope' => $scope, 'identity' => $identity, 'fields' => [], 'beliefs' => $beliefs];
        foreach (self::FIELDS as $field) $canonical['fields'][$field] = $fields[$field];
        return hash('sha256', $this->json($canonical));
    }

    private function actorId(mixed $actorKey): int
    {
        if (!is_string($actorKey) || !preg_match('/^npc:([1-9][0-9]{0,9})$/D', $actorKey, $match)) {
            throw new BrainwashFault('invalid_request');
        }
        $id = (int)$match[1];
        if ($id <= 0 || (string)$id !== $match[1]) throw new BrainwashFault('invalid_request');
        return $id;
    }

    private function revisionToken(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/D', $value)) throw new BrainwashFault('invalid_request');
        return $value;
    }

    private function uuid(mixed $value): string
    {
        if (!$this->isUuid($value)) throw new BrainwashFault('invalid_request');
        return strtolower($value);
    }

    private function isUuid(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD', $value) === 1;
    }

    private function newUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }

    private function text(mixed $value, int $maxBytes): string
    {
        if (!is_string($value) || !$this->validText($value) || strlen($value) > $maxBytes) throw new BrainwashFault('invalid_request');
        return $value;
    }

    private function validText(string $value): bool
    {
        return preg_match('//u', $value) === 1;
    }

    private function onlyKeys(array $value, array $allowed): void
    {
        foreach (array_keys($value) as $key) if (!is_string($key) || !in_array($key, $allowed, true)) throw new BrainwashFault('invalid_request');
    }

    private function scope(): string
    {
        $scope = ($this->scopeProvider)();
        if (!is_string($scope) || $scope === '' || strlen($scope) > 256 || !$this->validText($scope)) throw new BrainwashFault('unavailable');
        return $scope;
    }

    private function checkContext(array $context, bool $assertCurrent = true): void
    {
        if ($assertCurrent) $this->assertCurrent($context);
        if (!array_key_exists('scopeKey', $context)) return;
        if (!is_string($context['scopeKey']) || strlen($context['scopeKey']) !== 64
            || !preg_match('/^[a-f0-9]{64}$/D', $context['scopeKey'])) throw new BrainwashFault('stale_context');
        $base = ($this->baseScopeProvider)();
        if (!is_string($base) || !hash_equals($base, $context['scopeKey'])) throw new BrainwashFault('stale_context');
    }

    private function assertCurrent(array $context): void
    {
        if (!array_key_exists('assertCurrent', $context)) return;
        if (!is_callable($context['assertCurrent'])) throw new BrainwashFault('stale_context');
        try {
            ($context['assertCurrent'])();
        } catch (BrainwashFault $fault) {
            throw $fault;
        } catch (Throwable) {
            throw new BrainwashFault('stale_context');
        }
    }

    private function withCurrentContext(array $context, callable $callback): mixed
    {
        $this->assertCurrent($context);
        if (!array_key_exists('withCurrentContext', $context)) return $callback();
        if (!is_callable($context['withCurrentContext'])) throw new BrainwashFault('stale_context');
        [$wrapped, $wasCalled] = $this->callbackGuard($callback);
        try {
            $result = ($context['withCurrentContext'])($wrapped);
            if (!$wasCalled()) throw new BrainwashFault('stale_context');
            return $result;
        } catch (BrainwashCallbackFailure $failure) {
            throw $failure->original;
        } catch (BrainwashFault $fault) {
            throw $fault;
        } catch (Throwable) {
            throw new BrainwashFault('stale_context');
        }
    }

    private function withLockedCurrentContext(array $context, callable $callback): mixed
    {
        $this->assertCurrent($context);
        if (!array_key_exists('withLockedCurrentContext', $context)) return $callback();
        if (!is_callable($context['withLockedCurrentContext'])) throw new BrainwashFault('stale_context');
        [$wrapped, $wasCalled] = $this->callbackGuard($callback);
        try {
            $result = ($context['withLockedCurrentContext'])($wrapped);
            if (!$wasCalled()) throw new BrainwashFault('stale_context');
            return $result;
        } catch (BrainwashCallbackFailure $failure) {
            throw $failure->original;
        } catch (BrainwashFault $fault) {
            throw $fault;
        } catch (Throwable) {
            throw new BrainwashFault('stale_context');
        }
    }

    private function callbackGuard(callable $callback): array
    {
        $called = false;
        $wrapped = static function () use ($callback, &$called): mixed {
            if ($called) throw new BrainwashFault('conflict');
            $called = true;
            try {
                return $callback();
            } catch (Throwable $error) {
                throw new BrainwashCallbackFailure($error);
            }
        };
        $wasCalled = static function () use (&$called): bool { return $called; };
        return [$wrapped, $wasCalled];
    }

    private function beginCommit(array $context): void
    {
        if (!array_key_exists('beginCommit', $context)) return;
        if (!is_callable($context['beginCommit'])) throw new BrainwashFault('stale_context');
        try {
            ($context['beginCommit'])();
        } catch (Throwable) {
            throw new BrainwashFault('stale_context');
        }
    }

    private function hashJson(array $data): string
    {
        return hash('sha256', $this->json($data));
    }

    private function json(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function one(string $sql, array $params = []): ?array
    {
        $result = $params ? @pg_query_params($this->db, $sql, $params) : @pg_query($this->db, $sql);
        if ($result === false) throw new RuntimeException('Database operation failed.');
        $row = pg_fetch_assoc($result);
        return is_array($row) ? $row : null;
    }

    private function all(string $sql, array $params = []): array
    {
        $result = $params ? @pg_query_params($this->db, $sql, $params) : @pg_query($this->db, $sql);
        if ($result === false) throw new RuntimeException('Database operation failed.');
        $rows = pg_fetch_all($result);
        return is_array($rows) ? $rows : [];
    }

    private function query(string $sql, array $params = []): PgSql\Result
    {
        $result = $params ? @pg_query_params($this->db, $sql, $params) : @pg_query($this->db, $sql);
        if ($result === false) throw new RuntimeException('Database operation failed.');
        return $result;
    }

    private function begin(): void
    {
        if ($this->transactionOpen) throw new LogicException('Nested Brainwash transaction.');
        $this->query('BEGIN');
        $this->transactionOpen = true;
    }

    private function commit(): void
    {
        if (!$this->transactionOpen) return;
        $this->query('COMMIT');
        $this->transactionOpen = false;
    }

    private function rollback(): void
    {
        if (!$this->transactionOpen) return;
        try {
            $this->query('ROLLBACK');
        } catch (Throwable) {
        }
        $this->transactionOpen = false;
    }
}
