<?php
require_once('../php-sql-parser.php');
require_once('../test-more.php');
$parser = new PHPSQLParser();

$sql = 'SELECT
1';
$p=$parser->parse($sql);

ok(count($p) == 1 && count($p['SELECT']) == 1);
ok($p['SELECT'][0]['expr_type'] == 'const');
ok($p['SELECT'][0]['base_expr'] == '1');
ok($p['SELECT'][0]['sub_tree'] == '');

$sql = 'SELECT 1+2 c1, 1+2 as c2, 1+2,  sum(a) sum_a_alias,a,a an_alias, a as another_alias,terminate
          from some_table an_alias
	where d > 5;';
$parser->parse($sql);
$p = $parser->parsed;

ok(count($p) == 3 && count($p['SELECT']) == 8);

ok($p['SELECT'][count($p['SELECT'])-1]['base_expr'] == 'terminate');
ok(count($p) == 3 && count($p['FROM']) == 1);
ok(count($p) == 3 && count($p['WHERE']) == 3);

$parser->parse('SELECT NOW( ),now(),sysdate( ),sysdate () as now');
#print_r($parser->parsed['SELECT'][3]);
ok($parser->parsed['SELECT'][3]['base_expr'] == 'sysdate ');
