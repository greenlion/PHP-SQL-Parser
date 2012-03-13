<?php
require_once(dirname(__FILE__) . "/../php-sql-parser.php");
require_once(dirname(__FILE__) . "/../test-more.php");

$parser = new PHPSQLParser();

$sql = 'SELECT colA hello From test t';
$p = $parser->parse($sql, true);


print_r($p);
echo serialize($p);


ok($p['SELECT'][0]['position'] == 7, 'position of column');
ok($p['SELECT'][0]['alias']['position'] == 12, 'position of column alias');
ok($p['FROM'][0]['position'] == 22, 'position of table');
ok($p['FROM'][0]['position'] == 22, 'position of table');


$sql = "SELECT colA hello From test\nt";
$p = $parser->parse($sql, true);


print_r($p);
echo serialize($p);


ok($p['SELECT'][0]['position'] == 7, 'special char: position of column');
ok($p['FROM'][0]['position'] == 22, 'special char: position of table');