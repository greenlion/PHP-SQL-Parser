<?php
require_once(dirname(__FILE__) . "/../../../src/PHPSQLParser.php");
require_once(dirname(__FILE__) . "/../../test-more.php");

$sql = "SELECT CAST((CONCAT(table1.col1,' ',time_start)) AS DATETIME) FROM table1";
$parser = new PHPSQLParser($sql,true);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue62.serialized');
eq_array($p, $expected, 'CAST expression');

?>