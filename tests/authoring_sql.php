<?php
declare(strict_types=1);
require_once __DIR__.'/../server/runtime.php';
$root=getenv('BRAINWASH_CAPTURED_CORE');
if (!is_string($root) || !brainwash_source_matches($root.'/lib/eventlog_helper.php',BRAINWASH_CORE_SOURCE_PINS['lib/eventlog_helper.php'])) throw new RuntimeException('Pinned captured helper required.');
require_once $root.'/lib/eventlog_helper.php';
$adapter=new class { public function escape(string $value): string { return str_replace("'","''",$value); } };
$sql=brainwash_authoring_history_sql($adapter,"Aela O'Name",200);
foreach ([chimBuildNpcEventLogPeopleWhereClause($adapter,"Aela O'Name"),chimBuildVisibleEventLogWhereClause($adapter),"COALESCE(delivery_state,'spoken')='spoken'",'gamets <=','LIMIT 201'] as $needle) {
    if (!str_contains($sql,$needle)) throw new RuntimeException('Missing authoritative SQL condition');
}
if (str_contains($sql,'private_thought') || str_contains($sql,'SELECT *')) throw new RuntimeException('Private/wildcard query');
echo "PASS pinned audience, visible exclusions, spoken-only delivery, game-time cutoff, bounded evidence and private-column exclusion\n";
$lookup=brainwash_nearby_catalog_sql();
if (!str_contains($lookup,'GROUP BY lower(btrim(npc_name))') || !str_contains($lookup,'HAVING count(*)=1') || str_contains($lookup,'LIMIT')) throw new RuntimeException('Unbounded or ambiguity-hiding nearby lookup');
echo "PASS nearby uniqueness aggregation returns at most one row per requested name without hiding duplicates\n";
$identity=brainwash_authoring_identity_sql();
if (!str_contains($identity,'lower(btrim(npc_name))=lower(btrim($1))') || !str_contains($identity,'LIMIT 2')) throw new RuntimeException('Missing bounded normalized history identity lookup');
echo "PASS evidence identity lookup normalizes names and reads at most two matches\n";
