<?php
require_once(dirname(__FILE__) . "/../../../src/PHPSQLParser.php");
require_once(dirname(__FILE__) . "/../../test-more.php");

$parser = new PHPSQLParser();
$sql = "SELECT SUM(1) * 100 as number";
$parser->parse($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue82.serialized');
eq_array($p, $expected, 'operator * problem');

?>