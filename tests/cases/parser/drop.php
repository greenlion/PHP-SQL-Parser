<?php
require_once(dirname(__FILE__) . "/../../../src/PHPSQLParser.php");
require_once(dirname(__FILE__) . "/../../test-more.php");

$parser = new PHPSQLParser();

$sql = "drop table if exists xyz cascade";
$p = $parser->parse($sql, true);
$expected = getExpectedValue(dirname(__FILE__), 'drop.serialized');
eq_array($p, $expected, 'drop table statement');


?>