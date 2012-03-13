<?php
require_once(dirname(__FILE__) . "/../php-sql-parser.php");
require_once(dirname(__FILE__) . "/../test-more.php");

$parser = new PHPSQLParser();

$sql = 'SELECT (select colA FRom TableA) as b From test t';
$p = $parser->parse($sql);
$expected = getExpectedValue('subselect.serialized');
eq_array($p, $expected, 'sub-select with alias');
