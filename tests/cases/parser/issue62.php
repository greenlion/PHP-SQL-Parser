<?php

require_once dirname(__FILE__) . "/../../../src/PHPSQLParser.php";
require_once dirname(__FILE__) . "/../../test-more.php";

$sql = "SELECT CAST((CONCAT(table1.col1,' ',time_start)) AS DATETIME) FROM table1";
$parser = new PHPSQLParser($sql,true);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue62a.serialized');
eq_array($p, $expected, 'CAST expression');


$sql = "UPDATE vtiger_tab set isentitytype=? WHERE tabid=?";
$parser = new PHPSQLParser($sql,true);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue62b.serialized');
eq_array($p, $expected, '? after operand');


$sql = "SELECT * FROM table1 IGNORE INDEX(PRIMARY)";
$parser = new PHPSQLParser($sql,false);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'issue62c.serialized');
eq_array($p, $expected, 'IGNORE INDEX within FROM clause');

?>
