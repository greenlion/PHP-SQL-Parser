<?php
require_once(dirname(__FILE__) . '/../../../php-sql-parser.php');
require_once(dirname(__FILE__) . '/../../test-more.php');

$sql="SELECT * FROM ((SELECT 1 AS `ID`) UNION (SELECT 2 AS `ID`)) AS `Tmp`";
$parser = new PHPSQLParser($sql);
$p = $parser->parsed;

print_r($p);

$expected = getExpectedValue(dirname(__FILE__), 'issue95.serialized');
eq_array($p, $expected, 'incomplete floating point numbers');
