<?php
require_once(dirname(__FILE__) . "/../../../src/PHPSQLParser.php");
require_once(dirname(__FILE__) . "/../../test-more.php");

$sql = "SELECT @t1, @`t2`, @t3, @t4 := @t1+@'t2'+@t3;";
$parser = new PHPSQLParser($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'variables1.serialized');
eq_array($p, $expected, 'user variables');

$sql = "SELECT (@aa:=id) AS a, (@aa+3) AS b FROM tbl_name HAVING b=5;";
$parser = new PHPSQLParser($sql);
$p = $parser->parsed;
$expected = getExpectedValue(dirname(__FILE__), 'variables2.serialized');
eq_array($p, $expected, 'user variables');

?>