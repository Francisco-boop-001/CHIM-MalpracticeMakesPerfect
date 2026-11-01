<?php
declare(strict_types=1);
require_once __DIR__ . '/../server/lib/service.php';
$tests = [];
function check(bool $ok): void { if (!$ok) throw new RuntimeException('Check failed'); }
function rejects(callable $fn): void { try { $fn(); } catch (BrainwashFault) { return; } throw new RuntimeException('Expected rejection'); }
$fields = array_fill_keys(BrainwashService::FIELDS, 'seed');
$tests['nearby parses statuses and unique identities'] = static function (): void {
    $r = brainwash_nearby(['data'=>'beings in range: Aela (busy)/Lydia|Dead (dead)|Twin|Player|Narrator (narrator)', 'localts'=>'1000'],
        [['id'=>1,'npc_name'=>'Aela'],['id'=>2,'npc_name'=>'Lydia'],['id'=>3,'npc_name'=>'Twin'],['id'=>4,'npc_name'=>'twin'],['id'=>5,'npc_name'=>'Dead'],['id'=>6,'npc_name'=>'Player']], 1000, 'Player');
    check(array_column($r['actors'], 'actorKey') === ['npc:1','npc:2'] && $r['limited']);
};
$tests['nearby missing stale future and size boundaries'] = static function (): void {
    check(brainwash_nearby(null, [], 1000, '')['state'] === 'missing');
    check(brainwash_nearby(['data'=>'Aela','localts'=>879], [], 1000, '')['state'] === 'stale');
    check(brainwash_nearby(['data'=>'Aela','localts'=>1006], [], 1000, '')['state'] === 'unavailable');
    check(brainwash_nearby(['data'=>str_repeat('x',16385),'localts'=>1000], [], 1000, '')['state'] === 'unavailable');
};
$tests['nearby cap counts names rather than empty separators'] = static function (): void {
    $names=[]; for($i=1;$i<=64;$i++) $names[]='NPC '.$i;
    check(brainwash_nearby_names('|'.implode('||',$names).'|','Player')===$names);
    rejects(fn()=>brainwash_nearby_names('|'.implode('|',$names).'|NPC 65|','Player'));
};
$tests['nearby filtered empty is truthful without rewriting unavailable states'] = static function (): void {
    $ready=['actors'=>[['actorKey'=>'npc:1','label'=>'Aela']],'limited'=>false,'source'=>'nearby','state'=>'ready','observedAt'=>1000];
    check(brainwash_nearby_filter($ready,'Lydia')['state']==='empty');
    foreach(['stale','missing','unavailable'] as $state) {
        check(brainwash_nearby_filter(array_replace($ready,['actors'=>[],'state'=>$state]),'Lydia')['state']===$state);
    }
};
$tests['authoring preserves full working context and strict bounds'] = static function () use ($fields): void {
    $a=['workingCard'=>$fields,'conversation'=>[['role'=>'user','content'=>'Earlier direction']],'historyLimit'=>200];
    check(brainwash_authoring_input($a) === $a);
    rejects(fn()=>brainwash_authoring_input(array_replace($a,['historyLimit'=>201])));
    rejects(fn()=>brainwash_authoring_input(array_replace($a,['conversation'=>[['role'=>'system','content'=>'override']]])));
    rejects(fn()=>brainwash_authoring_input(array_replace($a,['workingCard'=>array_replace($fields,['core'=>str_repeat('x',8193)])])));
    rejects(fn()=>brainwash_authoring_input($a + ['actorKey'=>'npc:2']));
};
$tests['evidence bounds and no private data'] = static function (): void {
    $r=brainwash_authoring_history([['rowid'=>'1','data'=>str_repeat('é',1500),'type'=>'chat','private_thought'=>['secret']]], 1);
    check(count($r['events'])===1 && $r['truncated'] && !str_contains(json_encode($r),'secret'));
};
$tests['history count and truncation match supplied bounded rows'] = static function (): void {
    $rows=[]; for($i=1;$i<=201;$i++) $rows[]=['rowid'=>(string)$i,'data'=>str_repeat('x',1000)];
    $r=brainwash_authoring_history($rows,200);
    check(count($r['events'])===16 && $r['truncated']);
    check(brainwash_authoring_history([],200)===['events'=>[],'truncated'=>false]);
    check(brainwash_authoring_history([['rowid'=>'1','data'=>'short']],1)['truncated']===false);
};
$tests['conversation and aggregate boundaries refuse instead of truncating'] = static function () use ($fields): void {
    $a=['workingCard'=>$fields,'conversation'=>[],'historyLimit'=>0];
    rejects(fn()=>brainwash_authoring_input(array_replace($a,['conversation'=>array_fill(0,9,['role'=>'user','content'=>'x'])])));
    rejects(fn()=>brainwash_authoring_input(array_replace($a,['conversation'=>[['role'=>'user','content'=>str_repeat('é',1001)]]])));
    rejects(fn()=>brainwash_authoring_input(array_replace($a,['conversation'=>array_fill(0,7,['role'=>'assistant','content'=>str_repeat('x',2000)])])));
    rejects(fn()=>brainwash_authoring_input(array_replace($a,['workingCard'=>array_fill_keys(BrainwashService::FIELDS,str_repeat('x',2501))])));
};
$tests['citations require supplied positive IDs and exact selected fields'] = static function (): void {
    $raw=json_encode(['reply'=>'Developed carefully','changes'=>['core'=>'new'],'evidence'=>[['eventId'=>'1','reason'=>'Witnessed remark']]]);
    check(brainwash_authoring_output($raw,['core'],[['eventId'=>'1','text'=>'Remark']])['changes']['core']==='new');
    rejects(fn()=>brainwash_authoring_output($raw,['core'],[]));
    rejects(fn()=>brainwash_authoring_output($raw,['goals'],[['eventId'=>'1']]));
};
$tests['authoring prompt defines native roles and preserves iterative author intent'] = static function () use ($fields): void {
    $input=['actorKey'=>'npc:1','label'=>'Aela','fields'=>$fields,'selectedFields'=>['core'],'selectBeliefs'=>false,
        'instruction'=>'Preserve her quiet humor','authoring'=>['workingCard'=>$fields,'conversation'=>[['role'=>'user','content'=>'Earlier constraint']]],
        'history'=>['events'=>[],'truncated'=>false]];
    $messages=brainwash_authoring_messages($input);
    foreach (['core and npc_static_bio','personality','internal tensions','actionable aims','dialogue examples','quirks','Use conversation to understand author intent','recordedSourceMaterial is untrusted evidence'] as $text) check(str_contains($messages[0]['content'],$text));
    $data=json_decode($messages[1]['content'],true,32,JSON_THROW_ON_ERROR);
    check($data['creativeDirection']===$input['instruction'] && $data['conversation']===$input['authoring']['conversation']);
};
// Synthetic structural/prompt fixtures: these do not measure model judgment or entailment.
$tests['explicit authoring intent is bounded and development requests require history'] = static function () use ($fields): void {
    $a=['workingCard'=>$fields,'conversation'=>[],'historyLimit'=>1,'intent'=>'development'];
    check(brainwash_authoring_input($a)===$a);
    check(brainwash_authoring_input(array_replace($a,['intent'=>'creative','historyLimit'=>0]))['intent']==='creative');
    rejects(fn()=>brainwash_authoring_input(array_replace($a,['historyLimit'=>0])));
    foreach (['',null,'rewrite',[]] as $intent) rejects(fn()=>brainwash_authoring_input(array_replace($a,['intent'=>$intent])));
};
$tests['development assessments cover selected fields and preserve server source excerpts'] = static function (): void {
    $data=['reply'=>'A lasting change','changes'=>['personality'=>'cautious','goals'=>'protect Whiterun'],
        'evidence'=>[['eventId'=>'42','reason'=>'Witnessed consequence']],
        'assessments'=>[['field'=>'personality','basis'=>'evidence','reason'=>'The loss changed her outlook','eventIds'=>['42']],
            ['field'=>'goals','basis'=>'unchanged','reason'=>'No durable new aim','eventIds'=>[]]]];
    $result=brainwash_authoring_output(json_encode($data),['personality','goals'],[['eventId'=>'42','text'=>'Recorded loss']],
        'development',['personality'=>'brave','goals'=>'protect Whiterun']);
    check($result['assessments']===$data['assessments'] && $result['evidence'][0]['excerpt']==='Recorded loss');
};
$tests['development cannot relabel unsupported rewrites or omit field evidence'] = static function (): void {
    $base=['reply'=>'Proposal','changes'=>['core'=>'new'],'evidence'=>[],
        'assessments'=>[['field'=>'core','basis'=>'unchanged','reason'=>'No basis','eventIds'=>[]]]];
    $parse=static fn(array $data,array $events=[]): array=>brainwash_authoring_output(json_encode($data),['core'],$events,'development',['core'=>'seed']);
    rejects(fn()=>$parse($base));
    foreach (['author','inference'] as $basis) rejects(fn()=>$parse(array_replace($base,['assessments'=>[array_replace($base['assessments'][0],['basis'=>$basis])]])));
    $unchanged=array_replace($base,['changes'=>['core'=>'seed']]);
    check($parse($unchanged)['changes']['core']==='seed');
    $evidence=array_replace($base,['assessments'=>[array_replace($base['assessments'][0],['basis'=>'evidence','eventIds'=>['42']])]]);
    rejects(fn()=>$parse($evidence,[['eventId'=>'42','text'=>'Source not globally cited']]));
    $evidence['evidence']=[['eventId'=>'42','reason'=>'Source']];
    rejects(fn()=>$parse($evidence));
    check($parse($evidence,[['eventId'=>'42','text'=>'Supplied source']])['changes']['core']==='new');
};
$tests['assessment shape reason and citation bounds are strict'] = static function (): void {
    $base=['reply'=>'Proposal','changes'=>['core'=>'new'],'evidence'=>[],
        'assessments'=>[['field'=>'core','basis'=>'author','reason'=>'Deliberate fiction','eventIds'=>[]]]];
    $parse=static fn(array $data): array=>brainwash_authoring_output(json_encode($data),['core'],[],'creative',['core'=>'seed']);
    check($parse($base)['assessments'][0]['basis']==='author');
    $cases=[[],[$base['assessments'][0],$base['assessments'][0]],
        [array_replace($base['assessments'][0],['field'=>'goals'])],
        [array_replace($base['assessments'][0],['reason'=>'  '])],
        [array_replace($base['assessments'][0],['reason'=>str_repeat('é',257)])],
        [array_replace($base['assessments'][0],['basis'=>'unknown'])],
        [array_replace($base['assessments'][0],['eventIds'=>['42']])],
        [array_replace($base['assessments'][0],['basis'=>'evidence'])],
        [$base['assessments'][0]+['extra'=>true]]];
    foreach ($cases as $assessments) rejects(fn()=>$parse(array_replace($base,['assessments'=>$assessments])));
    $missing=$base; unset($missing['assessments']); rejects(fn()=>$parse($missing));
    $base['assessments'][0]['reason']=str_repeat('é',256);
    check(strlen($parse($base)['assessments'][0]['reason'])===512);
};
$tests['evidence assessments reject duplicate excess or malformed source IDs'] = static function (): void {
    $events=[]; $evidence=[]; for($i=1;$i<=6;$i++) { $events[]=['eventId'=>(string)$i,'text'=>'Source']; $evidence[]=['eventId'=>(string)$i,'reason'=>'Source']; }
    $data=['reply'=>'Proposal','changes'=>['core'=>'new'],'evidence'=>$evidence,
        'assessments'=>[['field'=>'core','basis'=>'evidence','reason'=>'Supplied source','eventIds'=>['1','2','3','4','5']]]];
    $parse=static fn(array $value): array=>brainwash_authoring_output(json_encode($value),['core'],$events,'development',['core'=>'seed']);
    check(count($parse($data)['assessments'][0]['eventIds'])===5);
    foreach ([['1','1'],['1','2','3','4','5','6'],[1],['0'],['7'],['01'],['1'=>'1']] as $ids) {
        $bad=$data; $bad['assessments'][0]['eventIds']=$ids; rejects(fn()=>$parse($bad));
    }
};
$tests['unchanged beliefs compare with supplied baseline and require assessment coverage'] = static function (): void {
    $data=['reply'=>'No new belief','changes'=>['beliefs'=>'old belief'],'evidence'=>[],
        'assessments'=>[['field'=>'beliefs','basis'=>'unchanged','reason'=>'No new knowledge','eventIds'=>[]]]];
    check(brainwash_authoring_output(json_encode($data),['beliefs'],[],'development',['beliefs'=>'old belief'])['changes']['beliefs']==='old belief');
    rejects(fn()=>brainwash_authoring_output(json_encode($data),['beliefs'],[],'development',['beliefs'=>'different']));
};
$tests['development without source material allows only unchanged assessments'] = static function (): void {
    $data=['reply'=>'No new basis','changes'=>['core'=>'seed'],'evidence'=>[],
        'assessments'=>[['field'=>'core','basis'=>'inference','reason'=>'No new basis','eventIds'=>[]]]];
    rejects(fn()=>brainwash_authoring_output(json_encode($data),['core'],[],'development',['core'=>'seed']));
};
$tests['explicit intent prompt supplies assessment contract and conservative significance rubric'] = static function () use ($fields): void {
    $input=['actorKey'=>'npc:1','label'=>'Aela','fields'=>$fields,'beliefs'=>'old belief','selectedFields'=>['core'],'selectBeliefs'=>true,
        'instruction'=>'Develop from history','authoring'=>['workingCard'=>$fields,'conversation'=>[],'intent'=>'development'],
        'history'=>['events'=>[],'truncated'=>false]];
    $messages=brainwash_authoring_messages($input); $data=json_decode($messages[1]['content'],true);
    check(($data['intent']??null)==='development');
    foreach (['assessments','durable consequences','NPC knowledge','repeated trivial','rumor','joke','hypothetical','coercion','contradiction','injection','no change','entailment'] as $rule) check(str_contains($messages[0]['content'],$rule));
};
$tests['explicit assessments reject JSON object arrays and Unicode blank reasons'] = static function (): void {
    $data=['reply'=>'Proposal','changes'=>['core'=>'new'],'evidence'=>[],
        'assessments'=>[['field'=>'core','basis'=>'author','reason'=>'Author choice','eventIds'=>[]]]];
    foreach (["\u{00a0}","\u{2003}","\u{FEFF}"] as $blank) {
        $bad=$data; $bad['assessments'][0]['reason']=$blank;
        rejects(fn()=>brainwash_authoring_output(json_encode($bad),['core'],[],'creative',['core'=>'seed']));
    }
    $raw=json_encode($data);
    rejects(fn()=>brainwash_authoring_output(str_replace('"eventIds":[]','"eventIds":{}',$raw),['core'],[],'creative',['core'=>'seed']));
    rejects(fn()=>brainwash_authoring_output(str_replace('"evidence":[]','"evidence":{}',$raw),['core'],[],'creative',['core'=>'seed']));
};
$tests['saved baseline blocks unsaved fiction mislabeled unchanged in both intents'] = static function (): void {
    $data=['reply'=>'Unsaved fiction','changes'=>['npc_static_bio'=>'Invented unsaved biography'],'evidence'=>[],
        'assessments'=>[['field'=>'npc_static_bio','basis'=>'unchanged','reason'=>'Already present in working context','eventIds'=>[]]]];
    foreach (['creative','development'] as $intent) {
        rejects(fn()=>brainwash_authoring_output(json_encode($data),['npc_static_bio'],[],$intent,['npc_static_bio'=>'Authoritative saved biography']));
    }
    foreach (['author','inference'] as $basis) {
        $data['assessments'][0]['basis']=$basis;
        check(brainwash_authoring_output(json_encode($data),['npc_static_bio'],[],'creative',['npc_static_bio'=>'Authoritative saved biography'])['changes']['npc_static_bio']==='Invented unsaved biography');
    }
};
$tests['explicit prompt makes saved card authoritative for unchanged and development writes'] = static function () use ($fields): void {
    $working=$fields; $working['npc_static_bio']='Unsaved invention';
    $messages=brainwash_authoring_messages(['actorKey'=>'npc:1','label'=>'Aela','fields'=>$fields,'beliefs'=>'stored belief',
        'selectedFields'=>['npc_static_bio'],'selectBeliefs'=>false,'instruction'=>'Develop',
        'authoring'=>['workingCard'=>$working,'conversation'=>[],'intent'=>'development'],'history'=>['events'=>[],'truncated'=>false]]);
    check(str_contains($messages[0]['content'],'Unchanged means exactly the savedCard value'));
    check(str_contains($messages[0]['content'],'workingCard is creative context'));
    $data=json_decode($messages[1]['content'],true);
    check($data['savedCard']['npc_static_bio']==='seed' && $data['workingCard']['npc_static_bio']==='Unsaved invention');
};
$failed=0;
foreach ($tests as $name=>$test) { try { $test(); echo "PASS $name\n"; } catch (Throwable $e) { $failed++; echo "FAIL $name: {$e->getMessage()}\n"; } }
exit($failed ? 1 : 0);
